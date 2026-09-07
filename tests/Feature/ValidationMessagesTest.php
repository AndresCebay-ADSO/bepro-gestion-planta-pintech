<?php

declare(strict_types=1);

use App\Http\Requests\Formulas\StoreFormulaRequest;
use App\Http\Requests\PaintDevelopmentRequests\StorePaintDevelopmentRequest;
use App\Http\Requests\Production\CompleteProductionOrderRequest;
use App\Http\Requests\Production\StoreProductionOrderRequest;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Quotations\StoreQuotationRequest;
use App\Http\Requests\SalesOrders\StoreSalesOrderRequest;
use App\Http\Requests\Warehouses\AssignUsersRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

test('unique validation rule formats message in spanish with attribute translation', function () {
    User::factory()->create(['email' => 'duplicado@pintech.co']);

    $validator = Validator::make(
        ['email' => 'duplicado@pintech.co'],
        ['email' => ['unique:users,email']]
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('email'))
        ->toBe('El campo correo electrónico ya se encuentra registrado.');
});

test('global dictionary translates standard erp attributes into spanish', function () {
    $validator = Validator::make(
        [
            'name' => '',
            'warehouse_id' => '',
            'category_id' => '',
            'quantity' => '',
            'unit_price' => '',
        ],
        [
            'name' => ['required'],
            'warehouse_id' => ['required'],
            'category_id' => ['required'],
            'quantity' => ['required'],
            'unit_price' => ['required'],
        ]
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('name'))->toBe('El campo nombre es obligatorio.');
    expect($validator->errors()->first('warehouse_id'))->toBe('El campo almacén o bodega es obligatorio.');
    expect($validator->errors()->first('category_id'))->toBe('El campo categoría es obligatorio.');
    expect($validator->errors()->first('quantity'))->toBe('El campo cantidad es obligatorio.');
    expect($validator->errors()->first('unit_price'))->toBe('El campo precio unitario es obligatorio.');
});

test('global dictionary translates synchronized erp attributes into spanish', function () {
    $validator = Validator::make(
        [
            'type' => '',
            'supplier' => '',
            'job_title' => '',
            'expiry_date' => '',
            'movement_date' => '',
            'destination_warehouse_id' => '',
        ],
        [
            'type' => ['required'],
            'supplier' => ['required'],
            'job_title' => ['required'],
            'expiry_date' => ['required'],
            'movement_date' => ['required'],
            'destination_warehouse_id' => ['required'],
        ]
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('type'))->toBe('El campo tipo es obligatorio.');
    expect($validator->errors()->first('supplier'))->toBe('El campo proveedor es obligatorio.');
    expect($validator->errors()->first('job_title'))->toBe('El campo cargo es obligatorio.');
    expect($validator->errors()->first('expiry_date'))->toBe('El campo fecha de vencimiento es obligatorio.');
    expect($validator->errors()->first('movement_date'))->toBe('El campo fecha de movimiento es obligatorio.');
    expect($validator->errors()->first('destination_warehouse_id'))->toBe('El campo almacén de destino es obligatorio.');
});

test('global dictionary translates newly added erp foreign keys into spanish', function () {
    $validator = Validator::make(
        [
            'batch_id' => '',
            'product_variant_id' => '',
            'user_id' => '',
            'quantity_gallons' => '',
            'finished_product_batch_id' => '',
        ],
        [
            'batch_id' => ['required'],
            'product_variant_id' => ['required'],
            'user_id' => ['required'],
            'quantity_gallons' => ['required'],
            'finished_product_batch_id' => ['required'],
        ]
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('batch_id'))->toBe('El campo lote es obligatorio.');
    expect($validator->errors()->first('product_variant_id'))->toBe('El campo presentación es obligatorio.');
    expect($validator->errors()->first('user_id'))->toBe('El campo usuario es obligatorio.');
    expect($validator->errors()->first('quantity_gallons'))->toBe('El campo cantidad en galones es obligatorio.');
    expect($validator->errors()->first('finished_product_batch_id'))->toBe('El campo lote de producto terminado es obligatorio.');
});

test('quotation request returns clear spanish custom messages for items', function () {
    $request = new StoreQuotationRequest;

    $validator = Validator::make(
        ['items' => []],
        $request->rules(),
        $request->messages(),
        $request->attributes()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('items'))
        ->toBe('Debes agregar al menos un producto a la cotización.');
});

test('sales order request returns clear spanish custom messages for items', function () {
    $request = new StoreSalesOrderRequest;

    $validator = Validator::make(
        ['items' => []],
        $request->rules(),
        $request->messages(),
        $request->attributes()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('items'))
        ->toBe('Debes agregar al menos un producto a la orden de venta.');
});

test('complete production order request returns clear spanish custom messages for ingredients', function () {
    $request = new CompleteProductionOrderRequest;

    $validator = Validator::make(
        ['ingredients' => []],
        $request->rules(),
        $request->messages(),
        $request->attributes()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('ingredients'))
        ->toBe('Debes registrar el consumo de ingredientes de la orden.');
});

test('warehouse assign users request returns clear spanish custom messages for users', function () {
    $request = new AssignUsersRequest;

    $validator = Validator::make(
        ['users' => []],
        $request->rules(),
        $request->messages(),
        $request->attributes()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('users'))
        ->toBe('Debes seleccionar al menos un usuario para asignar al almacén.');
});

test('warehouse assign users request rejects duplicate or inactive users', function () {
    $activeUser = User::factory()->create(['is_active' => true]);
    $inactiveUser = User::factory()->create(['is_active' => false]);

    $request = new AssignUsersRequest;

    $validatorDuplicate = Validator::make(
        [
            'users' => [
                ['user_id' => $activeUser->id],
                ['user_id' => $activeUser->id],
            ],
        ],
        $request->rules(),
        $request->messages(),
        $request->attributes()
    );

    expect($validatorDuplicate->fails())->toBeTrue();
    expect($validatorDuplicate->errors()->first('users.0.user_id'))
        ->toBe('No puedes asignar el mismo usuario más de una vez.');

    $validatorInactive = Validator::make(
        [
            'users' => [
                ['user_id' => $inactiveUser->id],
            ],
        ],
        $request->rules(),
        $request->messages(),
        $request->attributes()
    );

    expect($validatorInactive->fails())->toBeTrue();
    expect($validatorInactive->errors()->first('users.0.user_id'))
        ->toBe('El usuario seleccionado no existe o no se encuentra activo.');
});

test('store production order request rejects packaging variants belonging to a different product or duplicates', function () {
    $productA = Product::factory()->create(['is_active' => true]);
    $productB = Product::factory()->create(['is_active' => true]);
    $variantA = ProductVariant::factory()->create(['product_id' => $productA->id, 'is_active' => true]);
    $variantB = ProductVariant::factory()->create(['product_id' => $productB->id, 'is_active' => true]);

    $requestWithForeignVariant = new StoreProductionOrderRequest;
    $requestWithForeignVariant->merge([
        'product_id' => $productA->id,
        'packaging' => [
            ['product_variant_id' => $variantB->id, 'planned_units' => 10],
        ],
    ]);

    $validatorForeign = Validator::make(
        $requestWithForeignVariant->all(),
        $requestWithForeignVariant->rules(),
        $requestWithForeignVariant->messages(),
        $requestWithForeignVariant->attributes()
    );

    expect($validatorForeign->fails())->toBeTrue();
    expect($validatorForeign->errors()->first('packaging.0.product_variant_id'))
        ->toBe('La presentación seleccionada no es válida, no pertenece al producto o no se encuentra disponible.');

    $requestWithDuplicates = new StoreProductionOrderRequest;
    $requestWithDuplicates->merge([
        'product_id' => $productA->id,
        'packaging' => [
            ['product_variant_id' => $variantA->id, 'planned_units' => 10],
            ['product_variant_id' => $variantA->id, 'planned_units' => 20],
        ],
    ]);

    $validatorDuplicates = Validator::make(
        $requestWithDuplicates->all(),
        $requestWithDuplicates->rules(),
        $requestWithDuplicates->messages(),
        $requestWithDuplicates->attributes()
    );

    expect($validatorDuplicates->fails())->toBeTrue();
    expect($validatorDuplicates->errors()->first('packaging.0.product_variant_id'))
        ->toBe('No puedes repetir la misma presentación de empaque en la planificación.');
});

test('store product request does not trigger assertQualityRange error when inputs are non numeric', function () {
    $request = new StoreProductRequest;
    $request->merge([
        'quality_viscosity_lower' => 'no_es_numero',
        'quality_viscosity_upper' => 'tampoco_es_numero',
    ]);

    $validator = Validator::make(
        $request->all(),
        $request->rules()
    );

    foreach ($request->after() as $afterCallback) {
        $afterCallback($validator);
    }

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('quality_viscosity_lower'))->toBeTrue();
    expect($validator->errors()->has('quality_viscosity_upper'))->toBeTrue();
    expect($validator->errors()->first('quality_viscosity_upper'))
        ->not->toContain('el valor superior debe ser mayor o igual al inferior');
});

test('paint development request translates nested payload attributes into spanish', function () {
    $request = new StorePaintDevelopmentRequest;

    $validator = Validator::make(
        ['context_payload' => ['sustrato' => '']],
        ['context_payload.sustrato' => ['required']],
        $request->messages(),
        $request->attributes()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('context_payload.sustrato'))
        ->toBe('El campo sustrato es obligatorio.');
});

test('store formula request rejects inactive or soft deleted unit of measure with clear spanish message', function () {
    $rawMaterial = RawMaterial::factory()->create(['is_active' => true]);
    $inactiveUom = UnitOfMeasure::factory()->create(['is_active' => false]);
    $deletedUom = UnitOfMeasure::factory()->create(['is_active' => true]);
    $deletedUom->delete();

    $request = new StoreFormulaRequest;

    $validatorInactive = Validator::make(
        [
            'details' => [
                [
                    'raw_material_id' => $rawMaterial->id,
                    'quantity' => 10,
                    'unit_of_measure_id' => $inactiveUom->id,
                ],
            ],
        ],
        $request->rules(),
        $request->messages(),
        $request->attributes()
    );

    expect($validatorInactive->fails())->toBeTrue();
    expect($validatorInactive->errors()->first('details.0.unit_of_measure_id'))
        ->toBe('La unidad de medida seleccionada no es válida o no se encuentra activa.');

    $validatorDeleted = Validator::make(
        [
            'details' => [
                [
                    'raw_material_id' => $rawMaterial->id,
                    'quantity' => 10,
                    'unit_of_measure_id' => $deletedUom->id,
                ],
            ],
        ],
        $request->rules(),
        $request->messages(),
        $request->attributes()
    );

    expect($validatorDeleted->fails())->toBeTrue();
    expect($validatorDeleted->errors()->first('details.0.unit_of_measure_id'))
        ->toBe('La unidad de medida seleccionada no es válida o no se encuentra activa.');
});

test('warehouse assign users request authorization returns false safely when no warehouse is bound', function () {
    $request = new AssignUsersRequest;

    expect($request->authorize())->toBeFalse();
});
