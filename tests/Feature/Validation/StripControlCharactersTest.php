<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;

/*
 * Auditoría 3.6, PR 10 (INV-08, OPC-08): un NUL recortaba el texto en PostgreSQL sin avisar. El middleware limpia los
 * caracteres de control de todo lo que entra, salvo contraseñas y archivos.
 */

beforeEach(function () {
    Route::middleware('web')->match(['get', 'post'], '/__echo-input', fn (Request $request) => response()->json($request->all()));
});

it('removes NUL and other control characters from form fields', function () {
    $response = $this->post('/__echo-input', ['notes' => "a\0b\x07c\x7Fd"]);

    expect($response->json('notes'))->toBe('abcd');
});

it('keeps tab, line feed and carriage return', function () {
    $response = $this->post('/__echo-input', ['notes' => "uno\tdos\nlínea\r\ntres"]);

    expect($response->json('notes'))->toBe("uno\tdos\nlínea\r\ntres");
});

it('cleans nested arrays, JSON bodies and the query string', function () {
    $nested = $this->post('/__echo-input', ['items' => [['lot' => "L\0-1"], 'x\0y']]);
    $json = $this->postJson('/__echo-input', ['notes' => "j\0son", 'deep' => ['a' => ["b\x01"]]]);
    $query = $this->get('/__echo-input?search=ab%00c');

    expect($nested->json('items.0.lot'))->toBe('L-1')
        ->and($json->json('notes'))->toBe('json')
        ->and($json->json('deep.a.0'))->toBe('b')
        ->and($query->json('search'))->toBe('abc');
});

it('leaves passwords untouched', function (string $field) {
    $response = $this->post('/__echo-input', [$field => "cla\0ve\x07"]);

    expect($response->json($field))->toBe("cla\0ve\x07");
})->with(['password', 'password_confirmation', 'current_password']);

it('keeps accents, ñ, emoji and invalid UTF-8 bytes as they came', function () {
    $response = $this->post('/__echo-input', ['name' => 'Pintura Epóxica ÑANDÚ 🎨']);
    expect($response->json('name'))->toBe('Pintura Epóxica ÑANDÚ 🎨');

    // Un UTF-8 inválido no debe convertirse en null ni borrarse.
    $captured = null;
    Route::middleware('web')->post('/__raw-input', function (Request $request) use (&$captured) {
        $captured = $request->input('name');

        return 'ok';
    });
    $this->post('/__raw-input', ['name' => "ab\xC3\x28c"])->assertOk();

    expect($captured)->toBe("ab\xC3\x28c");
});

it('does not touch uploaded files', function (): void {
    $captured = null;
    Route::middleware('web')->post('/__upload-input', function (Request $request) use (&$captured) {
        $file = $request->file('document');
        $captured = ['name' => $file->getClientOriginalName(), 'content' => file_get_contents($file->getRealPath())];

        return 'ok';
    });

    $this->post('/__upload-input', [
        'document' => UploadedFile::fake()->createWithContent("ficha\x07.pdf", "%PDF-1.4\n\0datos"),
        'notes' => "n\0ota",
    ])->assertOk();

    expect($captured['name'])->toBe("ficha\x07.pdf")
        ->and($captured['content'])->toBe("%PDF-1.4\n\0datos");
});

it('stores a client name without the control characters that PostgreSQL would cut', function () {
    actingAsRole(SystemRole::Admin, ['email_verified_at' => now()]);

    $this->post(route('clients.store'), ['business_name' => "Cliente\0 Prueba"])->assertRedirect();

    expect(Client::query()->where('business_name', 'Cliente Prueba')->exists())->toBeTrue();
});
