<?php

declare(strict_types=1);

use App\Actions\Formulas\CreateFormulaAction;
use App\Actions\Formulas\UpdateFormulaAction;
use App\Enums\SystemRole;
use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/*
 * Fórmula de referencia de precio (auditoría 3.6, PR 9, D-FOR1): varias activas por producto, una sola de referencia.
 */

describe('regla del modelo', function () {
    it('deja de referencia la primera fórmula activa del producto, y no las siguientes', function () {
        $product = Product::factory()->create();

        $first = Formula::factory()->for($product)->create();
        $second = Formula::factory()->for($product)->create();

        expect($first->is_price_reference)->toBeTrue()
            ->and($second->fresh()->is_price_reference)->toBeFalse()
            ->and($second->version)->toBe(2);
    });

    it('numera las versiones de un producto aunque se creen varias a la vez con la factory', function () {
        $product = Product::factory()->create();

        Formula::factory()->for($product)->count(3)->create();

        expect(Formula::query()->where('product_id', $product->id)->orderBy('version')->pluck('version')->all())
            ->toBe([1, 2, 3]);
    });

    it('también cuando el create omite is_active (default de la base)', function () {
        $formula = Formula::create([
            'product_id' => Product::factory()->create()->id,
            'version' => 1,
            'created_by' => User::factory()->create()->id,
        ]);

        expect($formula->fresh()->is_price_reference)->toBeTrue();
    });

    it('no deja de referencia una fórmula inactiva, y sí al reactivarla si el producto no tiene otra', function () {
        $formula = Formula::factory()->inactive()->create();

        expect($formula->is_price_reference)->toBeFalse();

        $formula->update(['is_active' => true]);

        expect($formula->fresh()->is_price_reference)->toBeTrue();
    });

    it('no le quita la referencia a otra al reactivar una fórmula', function () {
        $product = Product::factory()->create();
        $reference = Formula::factory()->for($product)->create();
        $other = Formula::factory()->for($product)->inactive()->create();

        $other->update(['is_active' => true]);

        expect($reference->fresh()->is_price_reference)->toBeTrue()
            ->and($other->fresh()->is_price_reference)->toBeFalse();
    });

    it('guarda el nombre con los espacios colapsados, y vacío como null', function () {
        $named = Formula::factory()->create(['name' => '  RAL   7035 ']);
        $blank = Formula::factory()->create(['name' => '   ']);

        expect($named->fresh()->name)->toBe('RAL 7035')
            ->and($blank->fresh()->name)->toBeNull();
    });
});

describe('base de datos', function () {
    it('rechaza una segunda fórmula de referencia del mismo producto', function () {
        $formula = Formula::factory()->create();

        expect(fn () => DB::table('formulas')->insert([
            'product_id' => $formula->product_id,
            'version' => 2,
            'is_active' => true,
            'is_price_reference' => true,
            'created_by' => $formula->created_by,
        ]))->toThrow(QueryException::class);
    });

    it('rechaza una versión repetida del mismo producto', function () {
        $formula = Formula::factory()->create();

        expect(fn () => DB::table('formulas')->insert([
            'product_id' => $formula->product_id,
            'version' => $formula->version,
            'is_active' => false,
            'created_by' => $formula->created_by,
        ]))->toThrow(QueryException::class);
    });

    it('rechaza una fórmula de referencia inactiva', function () {
        $formula = Formula::factory()->create();

        expect(fn () => DB::table('formulas')->where('id', $formula->id)->update(['is_active' => false]))
            ->toThrow(QueryException::class);
    })->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'SQLite no admite agregar un CHECK a una tabla creada.');
});

describe('pantalla', function () {
    beforeEach(function () {
        actingAsRole(SystemRole::Admin);

        $this->kg = UnitOfMeasure::factory()->create(['to_kg_conversion' => 1]);
        $this->resin = RawMaterial::factory()->create(['unit_of_measure_id' => $this->kg->id, 'current_price' => 1000]);
        $this->product = Product::factory()->create(['current_cost' => null]);

        // Crea una fórmula desde la pantalla con una línea de resina; el costo de granel es cantidad × $1.000.
        $this->storeFormula = fn (string $quantity, array $extra = []) => $this->post(route('formulas.store'), [
            'product_id' => $this->product->id,
            'details' => [['raw_material_id' => $this->resin->id, 'quantity' => $quantity, 'unit_of_measure_id' => $this->kg->id]],
            ...$extra,
        ]);
        $this->latestFormula = fn (): Formula => Formula::query()->where('product_id', $this->product->id)->latest('id')->firstOrFail();
    });

    it('deja de referencia y costea la primera fórmula; la segunda no cambia el costo', function () {
        ($this->storeFormula)('2')->assertSessionHas('success', 'Fórmula v1 creada. Es la referencia de precio del producto.');
        $first = ($this->latestFormula)();

        ($this->storeFormula)('5')->assertSessionHas('success', 'Fórmula v2 creada y activa. El precio del producto sigue saliendo de la v1.');
        $second = ($this->latestFormula)();

        expect($first->is_price_reference)->toBeTrue()
            ->and($second->is_price_reference)->toBeFalse()
            ->and($second->is_active)->toBeTrue()
            ->and($this->product->fresh()->current_cost)->toBe('2000.0000');
    });

    it('guarda el nombre normalizado y rechaza uno de más de 60 caracteres', function () {
        ($this->storeFormula)('1', ['name' => '  RAL   7035  '])->assertSessionHasNoErrors();
        expect(($this->latestFormula)()->name)->toBe('RAL 7035');

        ($this->storeFormula)('1', ['name' => str_repeat('A', 61)])->assertSessionHasErrors('name');
    });

    it('no crea fórmulas de un producto inactivo (D-FOR3)', function () {
        $this->product->update(['is_active' => false]);

        ($this->storeFormula)('1')->assertSessionHasErrors(['product_id' => 'Selecciona un producto activo.']);
        expect(Formula::query()->exists())->toBeFalse();
    });

    it('no crea la fórmula si el producto se desactivó después de validar', function () {
        $this->product->update(['is_active' => false]);

        expect(fn () => app(CreateFormulaAction::class)->execute([
            'product_id' => $this->product->id,
            'details' => [['raw_material_id' => $this->resin->id, 'quantity' => '1', 'unit_of_measure_id' => $this->kg->id]],
        ], (int) auth()->id()))->toThrow(ValidationException::class);
    });

    // El camino que rompía el CHECK en PostgreSQL: activar otra versión apagaba también la de referencia.
    it('activa una segunda versión sin tocar la de referencia', function () {
        $reference = Formula::factory()->for($this->product)->create();
        $other = Formula::factory()->for($this->product)->inactive()->create();

        $this->post(route('formulas.activate', $other))->assertRedirect(route('formulas.show', $other))->assertSessionHas('success');

        expect($other->fresh()->is_active)->toBeTrue()
            ->and($other->fresh()->is_price_reference)->toBeFalse()
            ->and($reference->fresh()->is_active)->toBeTrue()
            ->and($reference->fresh()->is_price_reference)->toBeTrue();
    });

    it('al activar una fórmula de un producto sin referencia la deja de referencia y lo costea', function () {
        ($this->storeFormula)('3');
        $formula = ($this->latestFormula)();
        // Un producto sin referencia (carga directa sin eventos): la fórmula inactiva y sin la marca.
        DB::table('formulas')->where('id', $formula->id)->update(['is_active' => false, 'is_price_reference' => false]);
        $this->product->update(['current_cost' => null]);

        $this->post(route('formulas.activate', $formula))
            ->assertSessionHas('success', 'Fórmula v1 activada. Es la referencia de precio del producto.');

        expect($formula->fresh()->is_price_reference)->toBeTrue()
            ->and($this->product->fresh()->current_cost)->toBe('3000.0000');
    });

    it('no desactiva la fórmula de referencia', function () {
        $reference = Formula::factory()->for($this->product)->create();

        $this->post(route('formulas.deactivate', $reference))
            ->assertSessionHas('error', 'Esta fórmula define el precio del producto: marca otra como referencia de precio antes de desactivarla.');

        expect($reference->fresh()->is_active)->toBeTrue();
    });

    it('desactiva una fórmula que no es la de referencia', function () {
        Formula::factory()->for($this->product)->create();
        $other = Formula::factory()->for($this->product)->create();

        $this->post(route('formulas.deactivate', $other))->assertSessionHas('success', 'Fórmula v2 desactivada.');

        expect($other->fresh()->is_active)->toBeFalse();
    });

    it('cambia la referencia de precio y costea con la nueva', function () {
        ($this->storeFormula)('2');
        $first = ($this->latestFormula)();
        ($this->storeFormula)('5');
        $second = ($this->latestFormula)();

        $this->post(route('formulas.price-reference', $second))
            ->assertSessionHas('success', 'El precio del producto ahora sale de la fórmula v2.');

        expect($first->fresh()->is_price_reference)->toBeFalse()
            ->and($second->fresh()->is_price_reference)->toBeTrue()
            ->and(Formula::query()->where('product_id', $this->product->id)->priceReference()->count())->toBe(1)
            ->and($this->product->fresh()->current_cost)->toBe('5000.0000');
    });

    it('no usa para el precio una fórmula inactiva', function () {
        $reference = Formula::factory()->for($this->product)->create();
        $inactive = Formula::factory()->for($this->product)->inactive()->create();

        $this->post(route('formulas.price-reference', $inactive))
            ->assertSessionHas('error', 'Activa la fórmula antes de usarla para el precio.');

        expect($reference->fresh()->is_price_reference)->toBeTrue();
    });

    it('avisa si la fórmula ya era la de referencia', function () {
        $reference = Formula::factory()->for($this->product)->create();

        $this->post(route('formulas.price-reference', $reference))
            ->assertSessionHas('success', 'La fórmula v1 ya era la referencia de precio.');
    });

    it('no elimina la fórmula de referencia', function () {
        actingAsRole(SystemRole::SuperAdmin); // formulas.delete
        $reference = Formula::factory()->for($this->product)->create();

        $this->delete(route('formulas.destroy', $reference))
            ->assertSessionHas('error', 'Esta fórmula define el precio del producto: marca otra como referencia de precio antes de eliminarla.');

        expect($reference->fresh())->not->toBeNull();
    });

    // P9-13: con el producto inactivo, sus fórmulas solo se desactivan o se eliminan.
    it('con el producto inactivo no activa, no edita ni cambia la referencia de sus fórmulas', function () {
        $reference = Formula::factory()->for($this->product)->create();
        $inactive = Formula::factory()->for($this->product)->inactive()->create();
        $this->product->update(['is_active' => false]);
        $message = 'El producto está inactivo: sus fórmulas solo se pueden desactivar o eliminar. Reactiva el producto para cambiarlas.';

        $this->post(route('formulas.activate', $inactive))->assertSessionHas('error', $message);
        $this->post(route('formulas.price-reference', $inactive))->assertSessionHas('error', $message);
        $this->get(route('formulas.edit', $reference))->assertRedirect(route('formulas.show', $reference))->assertSessionHas('error', $message);
        $this->put(route('formulas.update', $reference), [
            'details' => [['raw_material_id' => $this->resin->id, 'quantity' => '9', 'unit_of_measure_id' => $this->kg->id]],
        ])->assertSessionHas('error', $message);

        expect($inactive->fresh()->is_active)->toBeFalse()
            ->and($reference->fresh()->is_price_reference)->toBeTrue()
            ->and($reference->details()->count())->toBe(0);
    });

    it('con el producto inactivo sí desactiva una fórmula que no es la de referencia', function () {
        Formula::factory()->for($this->product)->create();
        $other = Formula::factory()->for($this->product)->create();
        $this->product->update(['is_active' => false]);

        $this->post(route('formulas.deactivate', $other))->assertSessionHas('success');

        expect($other->fresh()->is_active)->toBeFalse();
    });

    // Carrera: la petición leyó la fórmula sin referencia y sin OP, y otra la cambió mientras esperaba el bloqueo.
    it('al editar decide con la fórmula ya guardada: recalcula si mientras tanto quedó de referencia', function () {
        Formula::factory()->for($this->product)->create();
        $stale = Formula::factory()->for($this->product)->create();
        // Otra petición la marcó de referencia después de que esta la leyó.
        DB::table('formulas')->where('product_id', $this->product->id)->update(['is_price_reference' => false]);
        DB::table('formulas')->where('id', $stale->id)->update(['is_price_reference' => true]);

        app(UpdateFormulaAction::class)->execute($stale, [
            'details' => [['raw_material_id' => $this->resin->id, 'quantity' => '7', 'unit_of_measure_id' => $this->kg->id]],
        ]);

        expect($this->product->fresh()->current_cost)->toBe('7000.0000');
    });

    it('al editar no toca una fórmula que una OP empezó a usar mientras tanto', function () {
        $stale = Formula::factory()->for($this->product)->create();
        ProductionOrder::factory()->create(['product_id' => $this->product->id, 'formula_id' => $stale->id]);

        expect(fn () => app(UpdateFormulaAction::class)->execute($stale, [
            'details' => [['raw_material_id' => $this->resin->id, 'quantity' => '7', 'unit_of_measure_id' => $this->kg->id]],
        ]))->toThrow(DomainException::class, 'Esta fórmula ya fue usada en órdenes de producción y no se puede editar.');
    });

    it('la edición cambia nombre y líneas, pero no el estado', function () {
        ($this->storeFormula)('2');
        $formula = ($this->latestFormula)();

        $this->put(route('formulas.update', $formula), [
            'name' => 'Base',
            'is_active' => false,
            'details' => [['raw_material_id' => $this->resin->id, 'quantity' => '4', 'unit_of_measure_id' => $this->kg->id]],
        ])->assertSessionHasNoErrors();

        expect($formula->fresh()->name)->toBe('Base')
            ->and($formula->fresh()->is_active)->toBeTrue()
            ->and($this->product->fresh()->current_cost)->toBe('4000.0000');
    });

    it('envía a las pantallas cuál es la de referencia y su nombre', function () {
        $reference = Formula::factory()->for($this->product)->create(['name' => 'Base']);
        $other = Formula::factory()->for($this->product)->create(['name' => 'RAL 7035']);

        $this->get(route('formulas.show', $reference))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('formula.is_price_reference', true)
                ->where('formula.name', 'Base')
                ->where('can.activate', true));

        $this->get(route('products.show', $this->product))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('product.formulas', fn ($formulas) => collect($formulas)->firstWhere('id', $reference->id)['is_price_reference'] === true
                    && collect($formulas)->firstWhere('id', $other->id)['name'] === 'RAL 7035'));

        // En el selector de la OP, todas las activas, la de referencia primero.
        $this->get(route('production-orders.create'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('products', fn ($products) => collect(collect($products)->firstWhere('id', $this->product->id)['formulas'])->pluck('id')->all() === [$reference->id, $other->id]));
    });
});
