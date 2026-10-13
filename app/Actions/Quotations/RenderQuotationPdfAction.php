<?php

declare(strict_types=1);

namespace App\Actions\Quotations;

use App\Models\Quotation;
use App\Support\ImageDataUri;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

class RenderQuotationPdfAction
{
    public const LAYOUT_STACKED = 'stacked';

    public const LAYOUT_SIDE_BY_SIDE = 'side_by_side';

    public function __construct(
        private readonly BuildQuotationPdfDataAction $buildQuotationPdfData,
    ) {}

    /**
     * El asesor firma debajo de las notas. Si así el PDF ocupa más hojas que con notas y firma lado a lado, se usa
     * lado a lado: DomPDF no sabe de antemano dónde cae la firma, así que se mide renderizando. La segunda pasada solo
     * ocurre cuando la primera no cupo en una hoja.
     */
    public function execute(Quotation $quotation): DomPdf
    {
        $viewData = [
            'quotation' => $this->buildQuotationPdfData->execute($quotation),
            'beproLogoBase64' => ImageDataUri::fromFile(public_path(config('quotation.pdf_logos.header'))),
            'pintechLogoBase64' => ImageDataUri::fromFile(public_path(config('quotation.pdf_logos.footer'))),
        ];

        $stacked = $this->render($viewData, self::LAYOUT_STACKED);

        if ($this->pageCount($stacked) === 1) {
            return $stacked;
        }

        $sideBySide = $this->render($viewData, self::LAYOUT_SIDE_BY_SIDE);

        return $this->pageCount($sideBySide) < $this->pageCount($stacked) ? $sideBySide : $stacked;
    }

    /**
     * @param  array<string, mixed>  $viewData
     */
    private function render(array $viewData, string $closingLayout): DomPdf
    {
        /** @var DomPdf $pdf */
        $pdf = Pdf::loadView('pdf.quotation', [...$viewData, 'closingLayout' => $closingLayout]);
        $pdf->setPaper('letter');
        $pdf->render();

        return $pdf;
    }

    private function pageCount(DomPdf $pdf): int
    {
        return $pdf->getDomPDF()->getCanvas()->get_page_count();
    }
}
