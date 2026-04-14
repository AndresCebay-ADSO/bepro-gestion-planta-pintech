<?php

namespace {
    use App\Models\Warehouse;
    use App\Models\ProductionOrder;
    use App\Models\Transfer;
    use Illuminate\Support\Facades\DB;

    echo "=== INICIANDO VERIFICACIÓN TÉCNICA DE PINTECH OS ===\n\n";

    // 1. Verificar tipos de bodega (Inglés)
    echo "1. VERIFICACIÓN DE BODEGAS:\n";
    $warehouses = Warehouse::all();
    foreach ($warehouses as $w) {
        echo "   - Bodega: {$w->name} | Ciudad: {$w->city} | Tipo Interno (DB): {$w->type}\n";
    }
    echo "\n";

    // 2. Probar restricción de producción
    echo "2. PROBANDO BLOQUEO DE PRODUCCIÓN EN BODEGA (STORAGE):\n";
    $storage = Warehouse::where('type', 'storage')->first();
    try {
        ProductionOrder::create([
            'warehouse_id' => $storage->id,
            'order_number' => 'PO-' . time(),
            'status' => 'pending',
            'start_date' => now(),
            'created_by' => 1
        ]);
        echo "   ❌ ERROR: ¡El sistema permitió crear producción en Neiva!\n";
    } catch (\Exception $e) {
        echo "   ✅ ÉXITO: Bloqueado correctamente. Mensaje: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 3. Probar restricción de traslados ilegales
    echo "3. PROBANDO BLOQUEO DE TRASLADO ILEGAL (STORAGE -> FACTORY):\n";
    $factory = Warehouse::where('type', 'factory')->first();
    try {
        Transfer::create([
            'origin_warehouse_id' => $storage->id,
            'destination_warehouse_id' => $factory->id,
            'product_id' => 1,
            'quantity' => 10,
            'status' => 'pending',
            'created_by' => 1
        ]);
        echo "   ❌ ERROR: ¡El sistema permitió traslado de Neiva a Cali!\n";
    } catch (\Exception $e) {
        echo "   ✅ ÉXITO: Bloqueado correctamente. Mensaje: " . $e->getMessage() . "\n";
    }

    echo "\n=== VERIFICACIÓN FINALIZADA ===\n";
}
