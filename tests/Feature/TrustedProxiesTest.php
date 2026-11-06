<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Detrás del túnel de Cloudflare la app recibe la visita del contenedor de Nginx, con cabeceras X-Forwarded-*.
// Se confía en la IP y en el protocolo; el dominio y el puerto, que puede inventar cualquier visitante, no. El dominio
// sale de la cabecera Host, como la entrega Cloudflare (sin puerto).

beforeEach(function (): void {
    Route::get('/__proxy-probe', fn (Request $request) => [
        'secure' => $request->isSecure(),
        'ip' => $request->ip(),
        'host' => $request->getHost(),
        'port' => $request->getPort(),
        'url' => url('/reset-password/token'),
    ]);
});

it('recognizes HTTPS and the visitor IP sent through the tunnel', function (): void {
    $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
        ->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-For' => '203.0.113.7',
        ])
        ->getJson('http://erp.example.test/__proxy-probe')
        ->assertOk()
        ->assertJson([
            'secure' => true,
            'ip' => '203.0.113.7',
            'port' => 443,
        ]);
});

it('ignores a forwarded host, port or prefix sent by the visitor', function (): void {
    $response = $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
        ->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'attacker.example',
            'X-Forwarded-Port' => '8443',
            'X-Forwarded-Prefix' => '/attacker',
        ])
        ->getJson('http://erp.example.test/__proxy-probe')
        ->assertOk();

    expect($response->json('host'))->toBe('erp.example.test')
        ->and($response->json('port'))->toBe(443)
        ->and($response->json('url'))->toBe('https://erp.example.test/reset-password/token');
});
