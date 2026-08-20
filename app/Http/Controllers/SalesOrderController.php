<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SalesOrderPriority;
use App\Enums\SalesOrderStatus;
use App\Filters\SalesOrderFilter;
use App\Http\Requests\SalesOrders\IndexSalesOrderRequest;
use App\Http\Requests\SalesOrders\StoreSalesOrderRequest;
use App\Http\Requests\SalesOrders\UpdateSalesOrderRequest;
use App\Models\Client;
use App\Models\Product;
use App\Models\SalesOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SalesOrderController extends Controller
{
    public function index(IndexSalesOrderRequest $request): Response
    {
        $user = $request->user();
        $canManage = $user?->hasAnyRole(['admin', 'produccion']) ?? false;

        $orders = (new SalesOrderFilter($request))
            ->apply(SalesOrder::query())
            ->visibleTo($user)
            ->with($canManage ? ['client', 'creator'] : ['client'])
            ->withCount('items')
            ->latest()
            ->paginate(15)
            ->onEachSide(1)
            ->withQueryString()
            ->through(fn (SalesOrder $order) => [
                'id' => $order->id,
                'client' => $order->client,
                'creator' => $canManage ? $order->creator : null,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'priority' => $order->priority->value,
                'priority_label' => $order->priority->label(),
                'required_date' => $order->required_date?->format('Y-m-d'),
                'estimated_delivery_date' => $order->estimated_delivery_date?->format('Y-m-d'),
                'items_count' => $order->items_count,
                'created_at' => $order->created_at?->format('d/m/Y'),
            ]);

        return Inertia::render('SalesOrders/Index', [
            'orders' => $orders,
            'filters' => $request->validated(),
            'can' => [
                'create' => $user?->hasAnyRole(['admin', 'comercial']) ?? false,
                'manage' => $canManage,
            ],
            'statusOptions' => $this->enumOptions(SalesOrderStatus::cases()),
            'priorityOptions' => $this->enumOptions(SalesOrderPriority::cases()),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', SalesOrder::class);

        $clients = Client::query()
            ->active()
            ->orderBy('business_name')
            ->get(['id', 'business_name', 'nit', 'contact_name', 'phone', 'shipping_address']);

        $products = Product::query()
            ->with([
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('name'),
            ])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'variants' => $p->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'presentation_label' => $v->presentation_label,
                    'presentation_value' => $v->presentation_value,
                ]),
            ]);

        return Inertia::render('SalesOrders/Create', [
            'clients' => $clients,
            'products' => $products,
        ]);
    }

    public function store(StoreSalesOrderRequest $request): RedirectResponse
    {
        $this->authorize('create', SalesOrder::class);

        $validated = $request->validated();
        $user = auth()->user();

        $order = DB::transaction(function () use ($validated, $user): SalesOrder {
            $order = SalesOrder::create([
                'client_id' => $validated['client_id'],
                'status' => SalesOrderStatus::Pending->value,
                'priority' => $validated['priority'],
                'required_date' => $validated['required_date'],
                'notes' => $validated['notes'] ?? null,
                'shipping_address' => $validated['shipping_address'] ?? null,
                'client_business_name' => $validated['client_business_name'] ?? null,
                'client_nit' => $validated['client_nit'] ?? null,
                'client_contact_name' => $validated['client_contact_name'] ?? null,
                'client_phone' => $validated['client_phone'] ?? null,
                'created_by' => $user?->id,
            ]);

            foreach ($validated['items'] as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'quantity' => $item['quantity'],
                ]);
            }

            return $order;
        });

        return redirect()->route('sales-orders.show', $order)
            ->with('success', 'Pedido creado con éxito.');
    }

    public function show(SalesOrder $salesOrder): Response
    {
        $this->authorize('view', $salesOrder);
        $user = auth()->user();
        $canManage = $user?->hasAnyRole(['admin', 'produccion']) ?? false;

        $salesOrder->load(['client', 'creator', 'items.product', 'items.productVariant', 'quotation']);

        $statusTransitions = $salesOrder->status->nextTransitions();

        return Inertia::render('SalesOrders/Show', [
            'order' => $this->buildOrderData($salesOrder),
            'statusTransitions' => array_map(
                fn (SalesOrderStatus $status) => ['value' => $status->value, 'label' => $status->label()],
                $statusTransitions
            ),
            'can' => [
                'manage' => $canManage,
                'viewQuotation' => $salesOrder->quotation !== null && ($user?->can('view', $salesOrder->quotation) ?? false),
            ],
        ]);
    }

    public function update(UpdateSalesOrderRequest $request, SalesOrder $salesOrder): RedirectResponse
    {
        $salesOrder->update($request->validated());

        return redirect()->route('sales-orders.show', $salesOrder)
            ->with('success', 'Pedido actualizado con éxito.');
    }

    /**
     * @param  array<int, \BackedEnum>  $cases
     * @return array<int, array{value: string, label: string}>
     */
    private function enumOptions(array $cases): array
    {
        return array_map(
            fn (\BackedEnum $case) => [
                'value' => $case->value,
                'label' => method_exists($case, 'label') ? $case->label() : (string) $case->value,
            ],
            $cases
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOrderData(SalesOrder $salesOrder): array
    {
        return [
            'id' => $salesOrder->id,
            'client' => [
                'id' => $salesOrder->client_id,
                'business_name' => $salesOrder->client_business_name,
                'nit' => $salesOrder->client_nit,
                'contact_name' => $salesOrder->client_contact_name,
                'phone' => $salesOrder->client_phone,
            ],
            'status' => $salesOrder->status->value,
            'status_label' => $salesOrder->status->label(),
            'priority' => $salesOrder->priority->value,
            'priority_label' => $salesOrder->priority->label(),
            'required_date' => $salesOrder->required_date?->format('Y-m-d'),
            'estimated_delivery_date' => $salesOrder->estimated_delivery_date?->format('Y-m-d'),
            'notes' => $salesOrder->notes,
            'shipping_address' => $salesOrder->shipping_address,
            'quotation_id' => $salesOrder->quotation_id,
            'items' => $salesOrder->items->map(fn ($item) => [
                'id' => $item->id,
                'product' => $item->product,
                'product_variant' => $item->productVariant,
                'quantity' => $item->quantity,
            ]),
            'created_at' => $salesOrder->created_at?->format('d/m/Y H:i'),
            'creator' => $salesOrder->creator,
        ];
    }
}
