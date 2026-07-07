<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\SignatureOptimizerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('optimizer resizes oversized signature image', function () {
    Storage::fake('public');

    $service = app(SignatureOptimizerService::class);

    // Crear imagen grande (800x400) en memoria
    $image = imagecreatetruecolor(800, 400);
    $white = imagecolorallocate($image, 255, 255, 255);
    imagefill($image, 0, 0, $white);
    $black = imagecolorallocate($image, 0, 0, 0);
    imageline($image, 0, 0, 800, 400, $black);

    $tempPath = tempnam(sys_get_temp_dir(), 'test_sig_');
    imagepng($image, $tempPath);
    imagedestroy($image);

    $file = new UploadedFile(
        $tempPath,
        'signature.png',
        'image/png',
        null,
        true
    );

    $optimized = $service->optimize($file);

    $optimizedPath = $optimized->getRealPath();
    $optimizedImage = imagecreatefrompng($optimizedPath);
    $width = imagesx($optimizedImage);
    $height = imagesy($optimizedImage);
    imagedestroy($optimizedImage);

    expect($width)->toBeLessThanOrEqual(400)
        ->and($height)->toBeLessThanOrEqual(200);
});

test('optimizer does not resize already small signature', function () {
    Storage::fake('public');

    $service = app(SignatureOptimizerService::class);

    // Crear imagen pequena (200x100)
    $image = imagecreatetruecolor(200, 100);
    $white = imagecolorallocate($image, 255, 255, 255);
    imagefill($image, 0, 0, $white);

    $tempPath = tempnam(sys_get_temp_dir(), 'test_sig_');
    imagepng($image, $tempPath);
    imagedestroy($image);

    $file = new UploadedFile(
        $tempPath,
        'signature.png',
        'image/png',
        null,
        true
    );

    $optimized = $service->optimize($file);

    expect($optimized)->toBe($file);
});

test('optimized signature does not cause memory error on certificate generation', function () {
    Storage::fake('local');
    Storage::fake('public');

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'job_title' => 'Analista de Calidad',
        'signature_path' => 'signatures/test.png',
    ]);

    // Crear firma grande (1000x500) y guardarla como si la hubiera subido el usuario
    $image = imagecreatetruecolor(1000, 500);
    $white = imagecolorallocate($image, 255, 255, 255);
    imagefill($image, 0, 0, $white);

    ob_start();
    imagepng($image);
    $content = ob_get_clean();
    imagedestroy($image);

    Storage::disk('public')->put('signatures/test.png', $content);

    $service = app(SignatureOptimizerService::class);
    $optimized = $service->optimize(
        new UploadedFile(
            Storage::disk('public')->path('signatures/test.png'),
            'signature.png',
            'image/png',
            null,
            true
        )
    );

    Storage::disk('public')->put('signatures/test.png', file_get_contents($optimized->getRealPath()));

    // Ahora la firma esta optimizada: verificar que getimagesize no reporta dimensiones excesivas
    $path = Storage::disk('public')->path('signatures/test.png');
    $info = getimagesize($path);

    expect($info[0])->toBeLessThanOrEqual(400)
        ->and($info[1])->toBeLessThanOrEqual(200);
});
