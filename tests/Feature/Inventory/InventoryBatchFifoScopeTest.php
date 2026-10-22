<?php

declare(strict_types=1);

use App\Models\InventoryBatch;
use App\Models\RawMaterial;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryBatchService;

/*
 * Auditoría 3.6, PR 10 (INV-04): el orden FIFO de los lotes de MP (con saldo, el que primero entró, y el id para
 * desempatar) vive en un solo lugar del modelo. Lo usan el consumo de la OP y el cajón de movimientos.
 */

beforeEach(function (): void {
    $this->warehouse = Warehouse::factory()->factory()->create();
    $this->rawMaterial = RawMaterial::factory()->create();
    $batch = fn (string $lot, string $entry, string $remaining) => InventoryBatch::factory()->create([
        'raw_material_id' => $this->rawMaterial->id, 'warehouse_id' => $this->warehouse->id, 'lot_number' => $lot,
        'entry_date' => $entry, 'initial_quantity' => '10', 'remaining_quantity' => $remaining,
    ]);

    $batch('NUEVO', '2026-10-15', '5');
    $batch('AGOTADO', '2026-09-01', '0');
    $batch('EMPATE-1', '2026-09-10', '5');
    $batch('EMPATE-2', '2026-09-10', '5');
    $batch('VIEJO', '2026-09-01', '5');
});

it('lists the lots with stock from the oldest entry, breaking ties by id', function (): void {
    expect(InventoryBatch::query()->available()->fifoOrder()->pluck('lot_number')->all())
        ->toBe(['VIEJO', 'EMPATE-1', 'EMPATE-2', 'NUEVO']);
});

it('is the order the FIFO consumption uses', function (): void {
    $service = app(InventoryBatchService::class);

    expect($service->availableForRawMaterial($this->rawMaterial->id, $this->warehouse->id)->pluck('lot_number')->all())
        ->toBe(['VIEJO', 'EMPATE-1', 'EMPATE-2', 'NUEVO'])
        ->and($service->availableForMaterials($this->warehouse->id, [$this->rawMaterial->id], ['id', 'lot_number'])->pluck('lot_number')->all())
        ->toBe(['VIEJO', 'EMPATE-1', 'EMPATE-2', 'NUEVO']);
});
