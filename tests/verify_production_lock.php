<?php

use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// Boot Laravel
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo "--- Iniciando verificación de Bloqueo de Producción ---\n";

function createDependencies()
{
    $id = substr(uniqid(), -5);
    $category = ProductCategory::create(['name' => 'Verify '.$id]);
    $uom = UnitOfMeasure::create([
        'code' => 'kg_'.$id,
        'name' => 'Kilo '.$id,
        'symbol' => 'kg',
    ]);
    $product = Product::create([
        'code' => 'V-'.$id,
        'name' => 'Verify Product',
        'category_id' => $category->id,
        'unit_of_measure_id' => $uom->id,
        'current_cost' => 10,
        'profit_margin' => 50,
        'current_price' => 15,
        'price_threshold' => 5,
    ]);
    $user = User::create([
        'name' => 'Verify User',
        'email' => 'verify'.uniqid().'@example.com',
        'password' => Hash::make('password'),
    ]);

    $formula = Formula::create([
        'product_id' => $product->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    return [$product, $user, $formula];
}

try {
    DB::beginTransaction();

    // 1. Probar con BODEGA (Debe fallar)
    $bodega = Warehouse::create([
        'name' => 'Bodega Test '.uniqid(),
        'city' => 'Neiva',
        'type' => 'bodega',
    ]);

    echo '[1/2] Probando creación en BODEGA... ';
    try {
        [$product, $user, $formula] = createDependencies();
        ProductionOrder::create([
            'order_number' => 'FAIL-'.substr(uniqid(), -5),
            'product_id' => $product->id,
            'formula_id' => $formula->id,
            'warehouse_id' => $bodega->id,
            'quantity' => 100,
            'status' => 'pendiente',
            'planned_date' => now(),
            'created_by' => $user->id,
        ]);
        echo "ERROR: La orden se creó (esto no debería pasar).\n";
        exit(1);
    } catch (InvalidArgumentException $e) {
        if ($e->getMessage() === 'Solo se pueden asociar órdenes de producción a bodegas tipo Fábrica.') {
            echo "EXITO: Excepción capturada correctamente.\n";
        } else {
            echo 'ERROR: Excepción capturada pero con mensaje incorrecto: '.$e->getMessage()."\n";
            exit(1);
        }
    }

    // 2. Probar con FABRICA (Debe funcionar)
    $fabrica = Warehouse::create([
        'name' => 'Fábrica Test '.uniqid(),
        'city' => 'Cali',
        'type' => 'fabrica',
    ]);

    echo '[2/2] Probando creación en FÁBRICA... ';
    [$product, $user, $formula] = createDependencies();
    $order = ProductionOrder::create([
        'order_number' => 'OK-'.substr(uniqid(), -5),
        'product_id' => $product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $fabrica->id,
        'quantity' => 100,
        'status' => 'pendiente',
        'planned_date' => now(),
        'created_by' => $user->id,
    ]);

    if ($order->exists) {
        echo "EXITO: La orden se creó correctamente.\n";
    } else {
        echo "ERROR: La orden no se creó.\n";
        exit(1);
    }

    DB::rollBack();
    echo "--- Verificación completada con éxito ---\n";

} catch (Exception $e) {
    echo 'ERROR INESPERADO: '.$e->getMessage()."\n";
    echo $e->getTraceAsString()."\n";
    DB::rollBack();
    exit(1);
}
