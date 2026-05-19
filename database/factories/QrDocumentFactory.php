<?php

namespace Database\Factories;

use App\Enums\QrDocumentType;
use App\Models\QrCode;
use App\Models\QrDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QrDocument>
 */
class QrDocumentFactory extends Factory
{
    protected $model = QrDocument::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'qr_code_id' => QrCode::factory(),
            'document_type' => QrDocumentType::QualityCertificate,
            'file_name' => 'certificado-calidad.pdf',
            'file_path' => 'quality-certificates/test/certificado-calidad-v1.pdf',
            'file_size' => $this->faker->numberBetween(50000, 500000),
            'mime_type' => 'application/pdf',
            'version' => 1,
            'is_current' => true,
            'uploaded_by' => User::factory(),
        ];
    }

    public function certificate(): static
    {
        return $this->state(['document_type' => QrDocumentType::QualityCertificate]);
    }

    public function previous(): static
    {
        return $this->state(['is_current' => false]);
    }
}
