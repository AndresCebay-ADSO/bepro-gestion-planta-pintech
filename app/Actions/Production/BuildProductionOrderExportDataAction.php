<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Models\ProductionOrder;

class BuildProductionOrderExportDataAction
{
    public function __construct(
        private readonly BuildProductionOrderShowDataAction $buildShowData,
        private readonly BuildProductionOrderPdfMaterialsAction $buildPdfMaterials
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(ProductionOrder $productionOrder, bool $includeCosts = true): array
    {
        $orderData = $this->buildShowData->execute($productionOrder, $includeCosts);
        $orderData['pdf_materials'] = $this->buildPdfMaterials->execute($productionOrder);

        return $orderData;
    }
}
