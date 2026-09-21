<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Shared\DeleteUnusedRecordAction;
use App\Enums\Permission;
use App\Filters\ClientFilter;
use App\Http\Requests\Clients\IndexClientRequest;
use App\Http\Requests\Clients\StoreClientRequest;
use App\Http\Requests\Clients\UpdateClientRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function __construct(private readonly DeleteUnusedRecordAction $deleteUnused) {}

    public function index(IndexClientRequest $request): Response
    {
        $user = $request->user();

        $clients = (new ClientFilter($request))
            ->apply(Client::query())
            ->orderBy('business_name')
            ->paginate(15)
            ->onEachSide(1)
            ->withQueryString();

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'filters' => $request->validated(),
            'can' => [
                'edit' => $user?->can(Permission::ClientsEdit->value) ?? false,
                'delete' => $user?->can(Permission::ClientsDelete->value) ?? false,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Client::class);

        return Inertia::render('Clients/Create');
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $this->authorize('create', Client::class);

        Client::create($request->validated());

        return redirect()->route('clients.index')
            ->with('success', 'Cliente creado con éxito.');
    }

    public function edit(Request $request, Client $client): Response
    {
        $this->authorize('update', $client);

        return Inertia::render('Clients/Edit', [
            'client' => $client,
            'can' => [
                'deactivate' => $request->user()?->can('deactivate', $client) ?? false,
            ],
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $this->authorize('update', $client);

        $validated = $request->validated();

        // Desactivar no se bloquea: sus cotizaciones y pedidos abiertos siguen su curso; solo deja de aparecer en los
        // documentos nuevos (docs/POLITICA_ELIMINACION.md §3.1).
        $activeChanged = array_key_exists('is_active', $validated)
            && (bool) $validated['is_active'] !== $client->is_active;

        if ($activeChanged && ! ($request->user()?->can('deactivate', $client) ?? false)) {
            abort(403, __('No tienes autorización para activar o desactivar clientes.'));
        }

        $client->update($validated);

        return redirect()->route('clients.index')
            ->with('success', 'Cliente actualizado con éxito.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->authorize('delete', $client);

        if (! $this->deleteUnused->execute($client)) {
            return back()->with('error', __('El cliente tiene cotizaciones o pedidos. Desactívalo en su lugar.'));
        }

        return redirect()->route('clients.index')
            ->with('success', 'Cliente eliminado con éxito.');
    }
}
