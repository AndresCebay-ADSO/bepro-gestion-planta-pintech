<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\Formula;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Una línea de fórmula en una unidad que no se convierte a la de su materia prima se rechaza al guardar (auditoría 3.6,
 * FOR-02). Antes se guardaba, y la fórmula fallaba después al costearla o al crear la OP.
 */

beforeEach(function () {
    actingAsRole(SystemRole::Admin);

    $this->kg = UnitOfMeasure::factory()->create(['code' => 'kg', 'symbol' => 'kg', 'to_kg_conversion' => 1]);
    $this->gram = UnitOfMeasure::factory()->create(['code' => 'g', 'symbol' => 'g', 'to_kg_conversion' => 0.001]);
    $this->liter = UnitOfMeasure::factory()->create(['code' => 'l', 'symbol' => 'L', 'to_liter_conversion' => 1]);

    $this->resin = RawMaterial::factory()->create(['unit_of_measure_id' => $this->kg->id]);
    $this->solvent = RawMaterial::factory()->create(['unit_of_measure_id' => $this->liter->id]);
    $this->product = Product::factory()->create();

    $this->payload = fn (array $lines): array => [
        'product_id' => $this->product->id,
        'details' => array_map(fn (array $line): array => [
            'raw_material_id' => $line[0]->id,
            'quantity' => '1',
            'unit_of_measure_id' => $line[1]->id,
        ], $lines),
    ];
});

it('rechaza una línea en kg de una materia prima que se compra por litro, con el error en su unidad', function () {
    $this->post(route('formulas.store'), ($this->payload)([[$this->resin, $this->kg], [$this->solvent, $this->kg]]))
        ->assertSessionHasErrors(['details.1.unit_of_measure_id' => 'Esta unidad no se puede convertir a la de la materia prima (L).'])
        ->assertSessionDoesntHaveErrors(['details.0.unit_of_measure_id']);

    expect(Formula::query()->exists())->toBeFalse();
});

it('acepta una unidad convertible a la de la materia prima', function () {
    $this->post(route('formulas.store'), ($this->payload)([[$this->resin, $this->gram], [$this->solvent, $this->liter]]))
        ->assertSessionHasNoErrors();

    expect(Formula::query()->count())->toBe(1);
});

it('también al editar', function () {
    $formula = Formula::factory()->for($this->product)->create();

    $this->put(route('formulas.update', $formula), [
        ...($this->payload)([[$this->solvent, $this->gram]]),
        'is_active' => true,
    ])->assertSessionHasErrors(['details.0.unit_of_measure_id']);
});

it('no compara la unidad de una línea cuya materia prima ya tiene otro error', function () {
    $inactive = RawMaterial::factory()->create(['unit_of_measure_id' => $this->liter->id, 'is_active' => false]);

    $this->post(route('formulas.store'), ($this->payload)([[$inactive, $this->kg]]))
        ->assertSessionHasErrors(['details.0.raw_material_id'])
        ->assertSessionDoesntHaveErrors(['details.0.unit_of_measure_id']);
});
