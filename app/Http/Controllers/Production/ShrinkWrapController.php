<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Actions\ShrinkWraps\RegisterShrinkWrapAction;
use App\Enums\Permission;
use App\Filters\ShrinkWrapFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\IndexShrinkWrapRequest;
use App\Http\Requests\Production\StoreShrinkWrapRequest;
use App\Models\ProductionOrder;
use App\Models\ShrinkWrap;
use App\Models\ShrinkWrapItem;
use App\Models\ShrinkWrapType;
use App\Models\ShrinkWrapTypeItem;
use App\Services\TimezoneService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Registros de termoencogido (3.8), en Producción. El operario elige una OP completada, un tipo y cuántas veces lo
 * aplicó; al guardar se descuenta el empaque secundario. Inmutables: sin editar ni eliminar.
 */
class ShrinkWrapController extends Controller
{
    public function __construct(
        private readonly RegisterShrinkWrapAction $registerShrinkWrap,
        private readonly TimezoneService $timezone,
    ) {}

    public function index(IndexShrinkWrapRequest $request): Response
    {
        $canViewCosts = $request->user()?->can(Permission::CostsView->value) ?? false;

        $shrinkWraps = (new ShrinkWrapFilter($request))
            ->apply(ShrinkWrap::query())
            ->with([
                'productionOrder:id,order_number,lot_number,color,product_id',
                'productionOrder.product:id,name,code',
                'shrinkWrapType:id,name',
                'warehouse:id,name',
                'createdBy:id,name',
            ])
            // `latest('id')` desempata: sin él la paginación puede repetir o saltarse registros.
            ->latest('wrapped_at')
            ->latest('id')
            ->paginate(15)
            ->onEachSide(1)
            ->withQueryString()
            ->through(fn (ShrinkWrap $shrinkWrap): array => [
                ...$this->shrinkWrapData($shrinkWrap, $canViewCosts),
                'can' => ['view' => Gate::allows('view', $shrinkWrap)],
            ]);

        return Inertia::render('Production/ShrinkWraps/Index', [
            'shrinkWraps' => $shrinkWraps,
            'filters' => $request->validated(),
            'typeOptions' => ShrinkWrapType::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (ShrinkWrapType $type): array => ['value' => (string) $type->id, 'label' => $type->name])
                ->all(),
            'can' => [
                'create' => Gate::allows('create', ShrinkWrap::class),
                'viewCosts' => $canViewCosts,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', ShrinkWrap::class);

        return Inertia::render('Production/ShrinkWraps/Create', [
            'orderOptions' => ProductionOrder::query()
                ->shrinkWrappable()
                ->with(['product:id,name,code', 'warehouse:id,name'])
                ->latest('completion_date')
                ->latest('id')
                ->get(['id', 'order_number', 'lot_number', 'color', 'product_id', 'warehouse_id', 'completion_date'])
                ->map(fn (ProductionOrder $order): array => [
                    'value' => $order->id,
                    'order_number' => $order->order_number,
                    'lot_number' => $order->lot_number,
                    'product_name' => $order->productDisplayName(),
                    'warehouse_name' => $order->warehouse?->name,
                    'completion_date' => $order->completion_date?->toDateString(),
                ])
                ->all(),
            // La receta va con cada tipo para mostrar qué se descuenta por aplicación; el total lo calcula el servidor.
            'typeOptions' => ShrinkWrapType::query()
                ->active()
                ->with(['items.rawMaterial:id,code,unit_of_measure_id', 'items.rawMaterial.unitOfMeasure:id,symbol'])
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (ShrinkWrapType $type): array => [
                    'value' => $type->id,
                    'label' => $type->name,
                    'items' => $type->items->map(fn (ShrinkWrapTypeItem $item): array => [
                        'code' => $item->rawMaterial->code,
                        'unit_symbol' => $item->rawMaterial->unitOfMeasure->symbol,
                        'quantity' => $item->quantity,
                    ])->values()->all(),
                ])
                ->all(),
            'today' => $this->timezone->todayInPlant(),
        ]);
    }

    public function store(StoreShrinkWrapRequest $request): RedirectResponse
    {
        $this->authorize('create', ShrinkWrap::class);

        $shrinkWrap = $this->registerShrinkWrap->execute($request->validated(), (int) $request->user()->id);

        return redirect()
            ->route('production.shrink-wraps.show', $shrinkWrap)
            ->with('success', __('Termoencogido registrado: se descontó el empaque secundario.'));
    }

    public function show(Request $request, ShrinkWrap $shrinkWrap): Response
    {
        $this->authorize('view', $shrinkWrap);

        $canViewCosts = $request->user()?->can(Permission::CostsView->value) ?? false;

        $shrinkWrap->load([
            'productionOrder:id,order_number,lot_number,color,product_id',
            'productionOrder.product:id,name,code',
            'shrinkWrapType:id,name',
            'warehouse:id,name',
            'createdBy:id,name',
            'items' => fn ($query) => $query->orderBy('id'),
            'items.rawMaterial:id,code,unit_of_measure_id',
            'items.rawMaterial.unitOfMeasure:id,symbol',
        ]);

        return Inertia::render('Production/ShrinkWraps/Show', [
            'shrinkWrap' => [
                ...$this->shrinkWrapData($shrinkWrap, $canViewCosts),
                // Día en que se descontó del inventario: el del registro, que puede ser posterior al termoencogido.
                'registered_on' => $this->timezone->toPlantTime($shrinkWrap->created_at)?->toDateString(),
                'notes' => $shrinkWrap->notes,
                'items' => $shrinkWrap->items->map(fn (ShrinkWrapItem $item): array => [
                    'id' => $item->id,
                    'code' => $item->rawMaterial->code,
                    'unit_symbol' => $item->rawMaterial->unitOfMeasure->symbol,
                    'quantity_per_application' => $item->quantity_per_application,
                    'quantity' => $item->quantity,
                    'total_cost' => $canViewCosts ? $item->total_cost : null,
                ])->values()->all(),
            ],
            'can' => [
                'viewCosts' => $canViewCosts,
                'viewOrder' => $request->user()?->can(Permission::ProductionOrdersView->value) ?? false,
            ],
        ]);
    }

    /**
     * Requiere cargadas la OP con su producto, el tipo, la bodega y el usuario.
     *
     * @return array<string, mixed>
     */
    private function shrinkWrapData(ShrinkWrap $shrinkWrap, bool $canViewCosts): array
    {
        return [
            'id' => $shrinkWrap->id,
            'wrapped_at' => $shrinkWrap->wrapped_at->toDateString(),
            'applications' => $shrinkWrap->applications,
            'order' => [
                'id' => $shrinkWrap->productionOrder->id,
                'order_number' => $shrinkWrap->productionOrder->order_number,
                'lot_number' => $shrinkWrap->productionOrder->lot_number,
                'product_name' => $shrinkWrap->productionOrder->productDisplayName(),
            ],
            'type_name' => $shrinkWrap->shrinkWrapType->name,
            'warehouse_name' => $shrinkWrap->warehouse->name,
            'created_by_name' => $shrinkWrap->createdBy->name,
            'total_cost' => $canViewCosts ? $shrinkWrap->total_cost : null,
        ];
    }
}
