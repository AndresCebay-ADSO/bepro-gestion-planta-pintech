<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings\Catalogs;

use App\Actions\Shared\DeleteUnusedRecordAction;
use App\Actions\ShrinkWraps\SaveShrinkWrapTypeAction;
use App\Enums\RawMaterialType;
use App\Filters\ShrinkWrapTypeFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogs\IndexShrinkWrapTypeRequest;
use App\Http\Requests\Catalogs\StoreShrinkWrapTypeRequest;
use App\Http\Requests\Catalogs\UpdateShrinkWrapTypeRequest;
use App\Models\RawMaterial;
use App\Models\ShrinkWrapType;
use App\Models\ShrinkWrapTypeItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tipos de termoencogido (3.8), en Configuración → Catálogos. Cada tipo es una receta de empaque secundario por
 * aplicación; el operario lo elige al registrar un termoencogido.
 */
class ShrinkWrapTypeController extends Controller
{
    public function __construct(
        private readonly SaveShrinkWrapTypeAction $saveType,
        private readonly DeleteUnusedRecordAction $deleteUnused,
    ) {}

    public function index(IndexShrinkWrapTypeRequest $request): Response
    {
        $types = (new ShrinkWrapTypeFilter($request))
            ->apply(ShrinkWrapType::query()->with($this->itemRelations()))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->onEachSide(1)
            ->withQueryString()
            ->through(fn (ShrinkWrapType $type): array => [
                ...$this->typeData($type),
                'can' => [
                    'update' => Gate::allows('update', $type),
                    'delete' => Gate::allows('delete', $type),
                ],
            ]);

        return Inertia::render('Settings/Catalogs/ShrinkWrapTypes/Index', [
            'types' => $types,
            'filters' => $request->validated(),
            'can' => [
                'create' => Gate::allows('create', ShrinkWrapType::class),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', ShrinkWrapType::class);

        return Inertia::render('Settings/Catalogs/ShrinkWrapTypes/Create', [
            'rawMaterialOptions' => $this->rawMaterialOptions(),
        ]);
    }

    public function store(StoreShrinkWrapTypeRequest $request): RedirectResponse
    {
        $this->authorize('create', ShrinkWrapType::class);

        $this->saveType->execute($request->validated());

        return redirect()
            ->route('catalogs.shrink-wrap-types.index')
            ->with('success', __('Tipo de termoencogido registrado exitosamente.'));
    }

    public function edit(ShrinkWrapType $shrinkWrapType): Response
    {
        $this->authorize('update', $shrinkWrapType);

        $shrinkWrapType->load($this->itemRelations());

        return Inertia::render('Settings/Catalogs/ShrinkWrapTypes/Edit', [
            'type' => $this->typeData($shrinkWrapType),
            'rawMaterialOptions' => $this->rawMaterialOptions(
                $shrinkWrapType->items->pluck('raw_material_id')->all(),
            ),
        ]);
    }

    public function update(UpdateShrinkWrapTypeRequest $request, ShrinkWrapType $shrinkWrapType): RedirectResponse
    {
        $this->authorize('update', $shrinkWrapType);

        $this->saveType->execute($request->validated(), $shrinkWrapType);

        return redirect()
            ->route('catalogs.shrink-wrap-types.index')
            ->with('success', __('Tipo de termoencogido actualizado exitosamente.'));
    }

    public function destroy(ShrinkWrapType $shrinkWrapType): RedirectResponse
    {
        $this->authorize('delete', $shrinkWrapType);

        // Solo si nunca se usó (docs/POLITICA_ELIMINACION.md): la clave foránea RESTRICT de los registros decide. La receta
        // se borra antes por Eloquent, en la misma transacción, para que la auditoría registre cada línea; la cascada de
        // la base lo haría sin avisar. Si el tipo resulta usado, la transacción revierte también esas líneas.
        $deleted = $this->deleteUnused->execute(
            $shrinkWrapType,
            fn (ShrinkWrapType $locked) => $locked->items()->get()->each(fn (ShrinkWrapTypeItem $item) => $item->delete()),
        );

        if (! $deleted) {
            return back()->with('error', __('El tipo de termoencogido ya se usó. Desactívalo en su lugar.'));
        }

        return redirect()
            ->route('catalogs.shrink-wrap-types.index')
            ->with('success', __('Tipo de termoencogido eliminado exitosamente.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function itemRelations(): array
    {
        return [
            'items' => fn ($query) => $query->orderBy('id'),
            'items.rawMaterial:id,code,unit_of_measure_id,is_active',
            'items.rawMaterial.unitOfMeasure:id,symbol',
        ];
    }

    /**
     * Requiere la receta cargada (itemRelations()).
     *
     * @return array<string, mixed>
     */
    private function typeData(ShrinkWrapType $type): array
    {
        return [
            'id' => $type->id,
            'name' => $type->name,
            'is_active' => $type->is_active,
            'items' => $type->items->map(fn (ShrinkWrapTypeItem $item): array => [
                'raw_material_id' => $item->raw_material_id,
                'code' => $item->rawMaterial->code,
                'unit_symbol' => $item->rawMaterial->unitOfMeasure->symbol,
                // Una materia prima desactivada se conserva en la receta, pero la pantalla avisa para reemplazarla.
                'is_active' => $item->rawMaterial->is_active,
                'quantity' => $item->quantity,
            ])->values()->all(),
        ];
    }

    /**
     * Materias primas de empaque secundario que se pueden poner en una receta: las activas, más las que ya tiene el tipo
     * que se edita (una desactivada se conserva donde estaba, pero no se ofrece para recetas nuevas).
     *
     * @param  list<int>  $keepIds
     * @return list<array{value: int, label: string, unit_symbol: string, is_active: bool}>
     */
    private function rawMaterialOptions(array $keepIds = []): array
    {
        return RawMaterial::query()
            ->ofType(RawMaterialType::SecondaryPackaging)
            ->selectable($keepIds)
            ->with('unitOfMeasure:id,symbol')
            ->orderBy('code')
            ->get(['id', 'code', 'unit_of_measure_id', 'is_active'])
            ->map(fn (RawMaterial $material): array => [
                'value' => $material->id,
                'label' => $material->code,
                'unit_symbol' => $material->unitOfMeasure->symbol,
                'is_active' => $material->is_active,
            ])
            ->values()
            ->all();
    }
}
