<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\FormRequestFixtures;

uses(RefreshDatabase::class);

/*
 * Auditoría 3.6, PR 10 (USR-01, INV-12): un campo sin atributo en español sale en los mensajes con el nombre interno
 * («El campo created by está prohibido», «El campo signature debe ser una imagen»). Este test recorre las reglas de todos
 * los FormRequest y exige que cada campo tenga su nombre, en el propio request (`attributes()`) o en
 * `lang/es/validation.php`. Laravel busca el nombre por la llave completa (`items.*.quantity`), no por el último
 * segmento.
 */

test('cada campo de cada FormRequest tiene su nombre en español', function (): void {
    $parameters = FormRequestFixtures::parameters();
    $global = trans('validation.attributes', [], 'es');
    $missing = [];

    foreach (FormRequestFixtures::classes(includeIndex: true) as $class) {
        $request = FormRequestFixtures::make($class, $parameters);
        $own = method_exists($request, 'attributes') ? $request->attributes() : [];

        foreach (array_keys(app()->call([$request, 'rules'])) as $field) {
            if (array_key_exists($field, $own) || array_key_exists($field, $global)) {
                continue;
            }

            $missing[class_basename($class)][] = $field;
        }
    }

    $report = collect($missing)->map(fn (array $fields, string $class): string => $class.': '.implode(', ', $fields))->implode("\n");

    expect($missing)->toBe([], "Campos sin atributo en español:\n".$report);
});

test('la firma que no es una imagen recibe un solo mensaje que dice qué archivo se espera', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'signature' => UploadedFile::fake()->create('firma.pdf', 10, 'application/pdf'),
    ]);

    $response->assertSessionHasErrors(['signature' => 'La firma debe ser una imagen PNG o JPG.']);
    expect(session('errors')->get('signature'))->toHaveCount(1);
});

test('un valor inválido en «quitar firma» sale con su nombre en español', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'remove_signature' => 'quizás',
    ]);

    $response->assertSessionHasErrors(['remove_signature' => 'El campo quitar firma debe ser verdadero o falso.']);
});
