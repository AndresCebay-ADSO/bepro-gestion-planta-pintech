<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProductionOrder;
use Dompdf\FontMetrics;
use Dompdf\FrameReflower\Text;
use Illuminate\Support\Str;

/**
 * Ajusta el texto de la estampita a su sitio. DomPDF no tiene line-clamp y, si un bloque no cabe, lo pasa entero a otra
 * página, así que el ajuste se mide aquí con las métricas de fuente de la misma instancia que dibuja el PDF, no contando
 * caracteres. La plantilla toma de aquí la fuente; los anchos salen de LabelFormat.
 *
 * - Nombre (producto + color): dos líneas en negrita; 8 pt si cabe, si no 7 pt, y solo entonces recorta el nombre del
 *   producto con «…», nunca el color: el color es lo que distingue esa estampita de la del producto base.
 * - Presentación y código: «Galón · 12345678» en una línea; si no cabe, presentación arriba y código abajo. Recorta
 *   primero la presentación y el código solo si ni solo en su línea cabe: el código identifica el producto.
 */
class LabelTextFitterService
{
    public const FONT_FAMILY = 'helvetica';

    /** Tamaños en pt, del preferido al mínimo. */
    private const NAME_FONT_SIZES = [8, 7];

    private const NAME_MAX_LINES = 2;

    /** Más legible en dos líneas a 7 pt que apretado en una a 6 pt: 6 pt solo si a 7 no caben ni partidas. */
    private const PRESENTATION_FONT_SIZES = [7, 6];

    private const PRESENTATION_SEPARATOR = ' · ';

    /** El color se conserva entero hasta este largo; más allá se recorta también. */
    private const COLOR_MAX_LENGTH = 25;

    /** Holgura en pt para que el corte de línea de DomPDF no caiga justo en el borde. */
    private const WIDTH_SLACK_PT = 2;

    private const ELLIPSIS = '…';

    /**
     * @param  FontMetrics  $metrics  las de la instancia de DomPDF que va a dibujar el PDF (`getDomPDF()->getFontMetrics()`).
     * @return array{name: string, size: int}
     */
    public function fitName(string $productName, ?string $color, float $widthPt, FontMetrics $metrics): array
    {
        $color = filled($color) ? Str::limit((string) $color, self::COLOR_MAX_LENGTH, self::ELLIPSIS) : null;
        $maxWidth = $widthPt - self::WIDTH_SLACK_PT;
        $name = ProductionOrder::nameWithColor($productName, $color);

        foreach (self::NAME_FONT_SIZES as $size) {
            if ($this->lineCount($name, $size, $maxWidth, $metrics) <= self::NAME_MAX_LINES) {
                return ['name' => $name, 'size' => $size];
            }
        }

        $minimumSize = self::NAME_FONT_SIZES[array_key_last(self::NAME_FONT_SIZES)];
        $product = $productName;

        while ($this->lineCount($name, $minimumSize, $maxWidth, $metrics) > self::NAME_MAX_LINES && mb_strlen($product) > 1) {
            $product = rtrim(mb_substr($product, 0, -1));
            $name = ProductionOrder::nameWithColor($product.self::ELLIPSIS, $color);
        }

        return ['name' => $name, 'size' => $minimumSize];
    }

    /**
     * Cada línea va en un solo renglón (la plantilla no la deja partirse), así que basta medir su ancho.
     *
     * @param  FontMetrics  $metrics  las de la instancia de DomPDF que va a dibujar el PDF.
     * @return array{lines: list<string>, size: int}
     */
    public function fitPresentation(?string $presentationLabel, string $code, float $widthPt, FontMetrics $metrics): array
    {
        $maxWidth = $widthPt - self::WIDTH_SLACK_PT;
        $fits = fn (string $text, int $size): bool => $this->width($text, $size, 'normal', $metrics) <= $maxWidth;
        $minimumSize = self::PRESENTATION_FONT_SIZES[array_key_last(self::PRESENTATION_FONT_SIZES)];
        $preferredSize = self::PRESENTATION_FONT_SIZES[0];

        // Sin presentación va solo el código.
        if (blank($presentationLabel)) {
            foreach (self::PRESENTATION_FONT_SIZES as $size) {
                if ($fits($code, $size)) {
                    return ['lines' => [$code], 'size' => $size];
                }
            }

            return ['lines' => [$this->truncateToWidth($code, $minimumSize, $maxWidth, $metrics)], 'size' => $minimumSize];
        }

        $presentationLabel = (string) $presentationLabel;
        $oneLine = $presentationLabel.self::PRESENTATION_SEPARATOR.$code;

        if ($fits($oneLine, $preferredSize)) {
            return ['lines' => [$oneLine], 'size' => $preferredSize];
        }

        foreach (self::PRESENTATION_FONT_SIZES as $size) {
            if ($fits($presentationLabel, $size) && $fits($code, $size)) {
                return ['lines' => [$presentationLabel, $code], 'size' => $size];
            }
        }

        return [
            'lines' => [
                $this->truncateToWidth($presentationLabel, $minimumSize, $maxWidth, $metrics),
                $this->truncateToWidth($code, $minimumSize, $maxWidth, $metrics),
            ],
            'size' => $minimumSize,
        ];
    }

    /**
     * Lo deja igual si cabe en una línea; si no, lo acorta y termina en «…».
     */
    private function truncateToWidth(string $text, int $size, float $maxWidth, FontMetrics $metrics): string
    {
        if ($this->width($text, $size, 'normal', $metrics) <= $maxWidth) {
            return $text;
        }

        while (mb_strlen($text) > 1 && $this->width($text.self::ELLIPSIS, $size, 'normal', $metrics) > $maxWidth) {
            $text = rtrim(mb_substr($text, 0, -1));
        }

        return $text.self::ELLIPSIS;
    }

    /**
     * Líneas que ocupa el nombre. Parte donde parte DomPDF (su mismo patrón: espacios y después de un guion), y una
     * palabra más ancha que la línea cuenta las líneas que ocupa partida (la plantilla usa overflow-wrap).
     */
    private function lineCount(string $text, int $size, float $maxWidth, FontMetrics $metrics): int
    {
        // Los espacios al final de una línea no ocupan ancho.
        $width = fn (string $value): float => $this->width(rtrim($value), $size, 'bold', $metrics);

        // Cada palabra con el separador que la sigue: «AP-», «301 », «RAL ».
        $parts = preg_split(Text::$_wordbreak_pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        $lines = 1;
        $current = '';

        for ($i = 0, $count = count($parts); $i < $count; $i += 2) {
            $segment = $parts[$i].($parts[$i + 1] ?? '');

            if ($segment === '' || $width($current.$segment) <= $maxWidth) {
                $current .= $segment;

                continue;
            }

            if ($current !== '') {
                $lines++;
            }

            $lines += max(0, (int) ceil($width($segment) / $maxWidth) - 1);
            $current = $segment;
        }

        return $lines;
    }

    private function width(string $text, int $size, string $weight, FontMetrics $metrics): float
    {
        return $metrics->getTextWidth($text, $metrics->getFont(self::FONT_FAMILY, $weight), $size);
    }
}
