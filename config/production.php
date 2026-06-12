<?php

declare(strict_types=1);

return [
    'yield_tolerance' => 0.01,
    'auto_update_variant_price' => env('AUTO_UPDATE_VARIANT_PRICE', true),
    // Policies: conservative_max | weighted_average | last_lot
    // conservative_max is default to protect margin with volatile purchases.
    'raw_material_reference_price_policy' => env('RAW_MATERIAL_REFERENCE_PRICE_POLICY', 'conservative_max'),
    'lot_start_number' => (int) env('PRODUCTION_ORDER_LOT_START', 1),
];
