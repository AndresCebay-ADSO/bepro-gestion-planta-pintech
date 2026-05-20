<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Production\CancelProductionOrderAction;
use App\Actions\Production\CompleteProductionOrderAction;
use App\Actions\Production\CreateProductionOrderAction;
use App\Actions\Production\PreviewProductionOrderCostsAction;
use App\Models\Formula;
use App\Models\ProductionOrder;
use App\Services\Inventory\FifoStockAllocatorService;

class ProductionOrderService
{
    public function __construct(
        private readonly CreateProductionOrderAction $createProductionOrder,
        private readonly CompleteProductionOrderAction $completeProductionOrder,
        private readonly CancelProductionOrderAction $cancelProductionOrder,
        private readonly PreviewProductionOrderCostsAction $previewProductionOrderCosts,
        private readonly FifoStockAllocatorService $fifoStockAllocator
    ) {}

    public function estimateMaterialUnitCostForPlanning(int $rawMaterialId, int $warehouseId, float $requiredQuantity): float
    {
        return $this->fifoStockAllocator->estimateMaterialUnitCostForPlanning(
            rawMaterialId: $rawMaterialId,
            warehouseId: $warehouseId,
            requiredQuantity: $requiredQuantity
        );
    }

    /**
     * @param  array<int, float|int>  $requirementsByMaterialId
     * @return array<int, float>
     */
    public function estimateMaterialUnitCostsForPlanning(int $warehouseId, array $requirementsByMaterialId): array
    {
        return $this->fifoStockAllocator->estimateMaterialUnitCostsForPlanning(
            warehouseId: $warehouseId,
            requirementsByMaterialId: $requirementsByMaterialId
        );
    }

    /**
     * @param  array<int, array{id:int,actual_quantity:float|int}>  $ingredients
     * @param  array<int, array{id:int,actual_units:float|int}>  $packaging
     * @return array{
     *   ingredients: array<int, array{id:int,unit_cost:float,total_cost:float,actual_quantity:float}>,
     *   packaging: array<int, array{id:int,cost_price:float,total_cost:float,equivalent:float,actual_units:float}>,
     *   total_bulk_cost: float,
     *   total_finished_cost: float,
     *   total_equivalent: float
     * }
     */
    public function previewOrderCosts(ProductionOrder $order, array $ingredients, array $packaging): array
    {
        return $this->previewProductionOrderCosts->execute(
            order: $order,
            ingredients: $ingredients,
            packaging: $packaging
        );
    }

    public function validateStockForOrder(Formula $formula, float $quantity, int $warehouseId): void
    {
        $this->fifoStockAllocator->validateStockForOrder($formula, $quantity, $warehouseId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createOrder(array $data): ProductionOrder
    {
        $userId = auth()->id() ?? throw new \RuntimeException('No authenticated user');

        return $this->createProductionOrder->execute($data, (int) $userId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function completeOrder(ProductionOrder $order, array $data): ProductionOrder
    {
        $userId = auth()->id() ?? throw new \RuntimeException('No authenticated user');

        return $this->completeProductionOrder->execute($order, $data, (int) $userId);
    }

    public function cancelOrder(ProductionOrder $order, ?string $reason = null): ProductionOrder
    {
        return $this->cancelProductionOrder->execute($order, $reason);
    }
}
