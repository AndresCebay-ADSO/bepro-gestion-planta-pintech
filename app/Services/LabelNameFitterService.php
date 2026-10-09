<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProductionOrder;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Illuminate\Support\Str;

/**
 * Ajusta el nombre de la estampita (producto + color) a dos líneas: 8 pt si cabe, si no 7 pt, y solo entonces recorta el
 * nombre del producto con «…», nunca el color: el color es lo que distingue esa estampita de la del producto base.
 * DomPDF no tiene line-clamp, así que el ajuste se mide aquí con sus propias métricas de fuente (las mismas con que
 * dibuja), no contando caracteres. La fuente y los tamaños deben coincidir con la plantilla.
 */
class LabelNameFitterService
{
    private const FONT_FAMILY = 'helvetica';

    private const FONT_WEIGHT = 'bold';

    /** Tamaños en pt, del preferido al mínimo. */
    private const FONT_SIZES = [8, 7];

    private const MAX_LINES = 2;

    /** El color se conserva entero hasta este largo; más allá se recorta también. */
    private const COLOR_MAX_LENGTH = 25;

    /** Holgura en pt para que el corte de línea de DomPDF no caiga justo en el borde. */
    private const WIDTH_SLACK_PT = 2;

    private ?FontMetrics $fontMetrics = null;

    /**
     * @return array{name: string, size: int}
     */
    public function fit(string $productName, ?string $color, float $widthPt): array
    {
        $color = filled($color) ? Str::limit((string) $color, self::COLOR_MAX_LENGTH, '…') : null;
        $maxWidth = $widthPt - self::WIDTH_SLACK_PT;
        $name = ProductionOrder::nameWithColor($productName, $color);

        foreach (self::FONT_SIZES as $size) {
            if ($this->lineCount($name, $size, $maxWidth) <= self::MAX_LINES) {
                return ['name' => $name, 'size' => $size];
            }
        }

        $minimumSize = self::FONT_SIZES[array_key_last(self::FONT_SIZES)];
        $product = $productName;

        while ($this->lineCount($name, $minimumSize, $maxWidth) > self::MAX_LINES && mb_strlen($product) > 1) {
            $product = rtrim(mb_substr($product, 0, -1));
            $name = ProductionOrder::nameWithColor($product.'…', $color);
        }

        return ['name' => $name, 'size' => $minimumSize];
    }

    /**
     * Líneas que ocupa el texto partiendo por espacios, como DomPDF.
     */
    private function lineCount(string $text, int $size, float $maxWidth): int
    {
        $metrics = $this->fontMetrics();
        $font = $metrics->getFont(self::FONT_FAMILY, self::FONT_WEIGHT);
        $lines = 1;
        $current = '';

        foreach (explode(' ', $text) as $word) {
            $candidate = $current === '' ? $word : "{$current} {$word}";

            if ($current === '' || $metrics->getTextWidth($candidate, $font, $size) <= $maxWidth) {
                $current = $candidate;

                continue;
            }

            $lines++;
            $current = $word;
        }

        return $lines;
    }

    private function fontMetrics(): FontMetrics
    {
        return $this->fontMetrics ??= (new Dompdf)->getFontMetrics();
    }
}
