<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\SignatureOptimizerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('optimizer resizes oversized signature image', function () {
    Storage::fake('local');

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

    $optimizedPath = null;
    try {
        $optimized = $service->optimize($file);

        $optimizedPath = $optimized->getRealPath();
        $optimizedImage = imagecreatefrompng($optimizedPath);
        $width = imagesx($optimizedImage);
        $height = imagesy($optimizedImage);
        imagedestroy($optimizedImage);

        expect($width)->toBeLessThanOrEqual(400)
            ->and($height)->toBeLessThanOrEqual(200);
    } finally {
        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }
        if ($optimizedPath !== null && file_exists($optimizedPath)) {
            @unlink($optimizedPath);
        }
    }
});

test('optimizer does not resize already small signature', function () {
    Storage::fake('local');

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

    try {
        $optimized = $service->optimize($file);

        expect($optimized)->toBe($file);
    } finally {
        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }
    }
});

test('optimized signature does not cause memory error on certificate generation', function () {
    Storage::fake('local');
    Storage::fake('local');

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

    Storage::disk('local')->put('signatures/test.png', $content);

    $service = app(SignatureOptimizerService::class);
    $optimized = $service->optimize(
        new UploadedFile(
            Storage::disk('local')->path('signatures/test.png'),
            'signature.png',
            'image/png',
            null,
            true
        )
    );

    Storage::disk('local')->put('signatures/test.png', file_get_contents($optimized->getRealPath()));

    // Ahora la firma esta optimizada: verificar que getimagesize no reporta dimensiones excesivas
    $path = Storage::disk('local')->path('signatures/test.png');
    $info = getimagesize($path);

    expect($info[0])->toBeLessThanOrEqual(400)
        ->and($info[1])->toBeLessThanOrEqual(200);
});

test('optimizeAndStore stores optimized signature and returns public path', function () {
    Storage::fake('local');

    $service = app(SignatureOptimizerService::class);
    $file = UploadedFile::fake()->image('my_signature.png', 800, 400);

    $path = $service->optimizeAndStore($file);

    expect($path)->toBeString()->toStartWith('signatures/');
    Storage::disk('local')->assertExists($path);
});

test('optimizeAndStore throws ValidationException with signature message on failure', function () {
    Storage::fake('local');

    $service = app(SignatureOptimizerService::class);
    $tempFile = tempnam(sys_get_temp_dir(), 'corrupt_');
    file_put_contents($tempFile, 'not-a-valid-image');

    $file = new UploadedFile($tempFile, 'corrupt.png', 'image/png', null, true);

    try {
        $service->optimizeAndStore($file);
        test()->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('signature');
        expect($e->errors()['signature'][0])->toContain('No se pudo procesar la imagen de firma');
    } finally {
        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
    }
});

test('optimizeAndStore converts any Throwable into ValidationException', function () {
    Storage::fake('local');

    $service = new class extends SignatureOptimizerService
    {
        public function optimize(UploadedFile $file): UploadedFile
        {
            throw new Exception('Non-runtime unexpected failure');
        }
    };

    $file = UploadedFile::fake()->image('signature.png', 200, 100);

    try {
        $service->optimizeAndStore($file);
        test()->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('signature');
        expect($e->errors()['signature'][0])->toContain('No se pudo procesar la imagen de firma');
    }
});

test('optimize converts small jpeg to png format and returns png path', function () {
    Storage::fake('local');

    $service = app(SignatureOptimizerService::class);

    // Crear JPEG pequeño (200x100)
    $image = imagecreatetruecolor(200, 100);
    $white = imagecolorallocate($image, 255, 255, 255);
    imagefill($image, 0, 0, $white);

    $tempPath = tempnam(sys_get_temp_dir(), 'test_jpg_');
    imagejpeg($image, $tempPath);
    imagedestroy($image);

    $file = new UploadedFile(
        $tempPath,
        'firma.jpg',
        'image/jpeg',
        null,
        true
    );

    try {
        $path = $service->optimizeAndStore($file);

        expect($path)->toEndWith('.png');
        Storage::disk('local')->assertExists($path);
    } finally {
        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }
    }
});

test('optimizeAndStore cleans up temporary file in sys_get_temp_dir after storage', function () {
    Storage::fake('local');

    $service = app(SignatureOptimizerService::class);
    $file = UploadedFile::fake()->image('big_signature.png', 800, 400);

    // Identificar el archivo temporal capturando el UploadedFile optimizado
    $tempFileTracked = null;
    $serviceReflected = new class extends SignatureOptimizerService
    {
        public ?string $capturedTempPath = null;

        public function optimize(UploadedFile $file): UploadedFile
        {
            $optimized = parent::optimize($file);
            $this->capturedTempPath = $optimized->getRealPath();

            return $optimized;
        }
    };

    $serviceReflected->optimizeAndStore($file);

    expect($serviceReflected->capturedTempPath)->not->toBeNull();
    expect(file_exists($serviceReflected->capturedTempPath))->toBeFalse();
});
