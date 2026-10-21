<?php

declare(strict_types=1);

use App\Models\Formula;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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
