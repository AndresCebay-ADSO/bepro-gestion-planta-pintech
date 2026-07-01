<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\ProductionRemnant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RemnantController extends Controller
{
    /**
     * Display a listing of the available production remnants.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ProductionRemnant::class);

        $query = ProductionRemnant::query()
            ->with([
                'sourceOrder:id,order_number',
                'product:id,name,code',
                'warehouse:id,name',
            ])
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->whereHas('product', fn ($sq) => $sq
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('sourceOrder', fn ($sq) => $sq->where('order_number', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('status', $request->input('status'));
            })
            ->when($request->filled('warehouse_id'), function ($q) use ($request) {
                $q->where('warehouse_id', $request->input('warehouse_id'));
            })
            ->orderByDesc('created_at');

        $remnants = $query->paginate(15)->withQueryString();

        return Inertia::render('Production/Remnants/Index', [
            'remnants' => collect($remnants->items())->map(function (ProductionRemnant $remnant) {
                return [
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
                ];
            }),
            'meta' => [
                'current_page' => $remnants->currentPage(),
                'last_page' => $remnants->lastPage(),
                'total' => $remnants->total(),
            ],
            'filters' => $request->only(['search', 'status', 'warehouse_id']),
        ]);
    }
}
