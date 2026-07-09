<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Clients\StoreClientRequest;
use App\Http\Requests\Clients\UpdateClientRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Client::class);

        $search = strtolower((string) request('search', ''));
        $user = auth()->user();

        $clients = Client::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(business_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(nit) LIKE ?', ["%{$search}%"]);
                });
            })
            ->orderBy('business_name')
            ->paginate(15)
            ->onEachSide(1)
            ->withQueryString();

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'filters' => ['search' => request('search')],
            'can' => ['edit' => $user?->hasRole('admin') ?? false],
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

    public function edit(Client $client): Response
    {
        $this->authorize('update', $client);

        return Inertia::render('Clients/Edit', [
            'client' => $client,
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $this->authorize('update', $client);

        $client->update($request->validated());

        return redirect()->route('clients.index')
            ->with('success', 'Cliente actualizado con éxito.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->authorize('delete', $client);

        $client->delete();

        return redirect()->route('clients.index')
            ->with('success', 'Cliente eliminado con éxito.');
    }
}
