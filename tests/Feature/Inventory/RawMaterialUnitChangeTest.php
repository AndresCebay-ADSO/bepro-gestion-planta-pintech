<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/*
 * La unidad de una materia prima con historial o en uso no cambia (auditoría 3.6, RM-04): sus fórmulas dejarían de poder
 * costearse (kg → L) y sus saldos cambiarían de sentido aun entre unidades compatibles (kg → g).
 */

beforeEach(function () {
    actingAsRole(SystemRole::Admin);

    $this->kg = UnitOfMeasure::factory()->create(['to_kg_conversion' => 1]);
    $this->liter = UnitOfMeasure::factory()->create(['to_liter_conversion' => 1]);
    $this->material = RawMaterial::factory()->create(['unit_of_measure_id' => $this->kg->id, 'tracks_inventory' => true]);

    $this->update = fn (int $unitId) => $this->put(route('raw-materials.update', $this->material), [
        'code' => $this->material->code,
        'category_id' => $this->material->category_id,
        'unit_of_measure_id' => $unitId,
        'minimum_stock' => 10,
        'alert_days_before_expiry' => 30,
        'is_active' => true,
    ]);
});

it('no deja cambiar la unidad de una materia prima que ya está en una fórmula', function () {
    FormulaDetail::create([
        'formula_id' => Formula::factory()->create()->id,
        'raw_material_id' => $this->material->id,
        'quantity' => 1,
        'unit_of_measure_id' => $this->kg->id,
        'step_order' => 1,
    ]);

    ($this->update)($this->liter->id)
        ->assertSessionHasErrors(['unit_of_measure_id' => 'No se puede cambiar la unidad: la materia prima ya tiene lotes, movimientos o fórmulas en la unidad actual.']);

    expect($this->material->fresh()->unit_of_measure_id)->toBe($this->kg->id);
});

it('deja cambiar la unidad de una materia prima sin uso', function () {
    ($this->update)($this->liter->id)->assertSessionHasNoErrors();

    expect($this->material->fresh()->unit_of_measure_id)->toBe($this->liter->id);
});

it('deja guardar una materia prima en uso si no cambia la unidad', function () {
    FormulaDetail::create([
        'formula_id' => Formula::factory()->create()->id,
        'raw_material_id' => $this->material->id,
        'quantity' => 1,
        'unit_of_measure_id' => $this->kg->id,
        'step_order' => 1,
    ]);

    ($this->update)($this->kg->id)->assertSessionHasNoErrors();
});

it('avisa en la pantalla de edición si la unidad está bloqueada', function () {
    $this->get(route('raw-materials.edit', $this->material))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('unitLocked', false));

    FormulaDetail::create([
        'formula_id' => Formula::factory()->create()->id,
        'raw_material_id' => $this->material->id,
        'quantity' => 1,
        'unit_of_measure_id' => $this->kg->id,
        'step_order' => 1,
    ]);

    $this->get(route('raw-materials.edit', $this->material))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('unitLocked', true));
});
