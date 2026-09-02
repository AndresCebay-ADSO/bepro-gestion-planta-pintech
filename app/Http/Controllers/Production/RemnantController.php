<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Enums\RemnantStatus;
use App\Filters\RemnantFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\IndexRemnantRequest;
use App\Models\ProductionRemnant;
use App\Models\Warehouse;
use App\Support\EnumOptions;
use Inertia\Inertia;
use Inertia\Response;

class RemnantController extends Controller
{
    /**
     * Display a listing of the available production remnants.
     */
    public function index(IndexRemnantRequest $request): Response
    {
        $remnants = (new RemnantFilter($request))
            ->apply(ProductionRemnant::query())
            ->with([
                'sourceOrder:id,order_number',
                'product:id,name,code',
                'warehouse:id,name',
            ])
            ->orderByDesc('created_at')
            ->paginate(15)
            ->onEachSide(1)
            ->withQueryString()
            ->through(fn (ProductionRemnant $remnant) => [
                'id' => $remnant->id,
                'source_order_id' => $remnant->source_order_id,
                'source_order_number' => $remnant->sourceOrder->order_number,
                'product_id' => $remnant->product_id,
                'product_name' => $remnant->product->name,
                'product_code' => $remnant->product->code,
                'warehouse_id' => $remnant->warehouse_id,
                'warehouse_name' => $remnant->warehouse->name,
                'available_quantity_gallons' => (float) $remnant->available_quantity_gallons,
                'available_quantity_kg' => (float) $remnant->available_quantity_kg,
                'density_kg_per_gallon' => (float) $remnant->density_kg_per_gallon,
                'cost_per_gallon' => $remnant->cost_per_gallon !== null ? (float) $remnant->cost_per_gallon : null,
                'status' => $remnant->status->value,
                'status_label' => $remnant->status->label(),
                'created_at' => $remnant->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Production/Remnants/Index', [
            'remnants' => $remnants,
            'filters' => $request->validated(),
            'statusOptions' => EnumOptions::for(RemnantStatus::cases()),
            'warehouseOptions' => Warehouse::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
                ->map(fn (Warehouse $warehouse) => [
                    'value' => (string) $warehouse->id,
                    'label' => $warehouse->name,
                ])
                ->all(),
        ]);
    }
}
