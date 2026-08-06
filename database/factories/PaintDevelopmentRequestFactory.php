<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaintDevelopmentRequestStatus;
use App\Models\PaintDevelopmentRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaintDevelopmentRequest>
 */
class PaintDevelopmentRequestFactory extends Factory
{
    protected $model = PaintDevelopmentRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_number' => fake()->unique()->randomNumber(5),
            'status' => PaintDevelopmentRequestStatus::Draft,
            'client_name' => fake()->company(),
            'project_name' => fake()->words(3, true),
            'responsible' => fake()->name(),
            'city' => fake()->city(),
            'sample_due_date' => fake()->date(),
            'current_product' => fake()->optional()->word(),
            'context_payload' => [
                'sustrato' => fake()->randomElement([
                    'Acero al carbono',
                    'Acero galvanizado',
                    'Aluminio',
                    'Concreto o mortero',
                ]),
                'estado_superficie' => fake()->randomElement([
                    'Nueva / sin pintar',
                    'Pintada en buen estado',
                ]),
                'pintura_existente' => 'No',
                'preparacion' => ['Limpieza / desengrase'],
                'exposicion' => ['Interior'],
                'agua_humedad' => 'Humedad ambiental',
                'ciclos_termicos' => 'No',
            ],
            'performance_payload' => [
                'funcion' => ['Anticorrosiva'],
                'vida_util' => '5–10 años',
                'prioridad' => 'Máxima protección',
                'color' => 'RAL 7040',
                'brillo' => 'Semimate',
                'textura' => 'Lisa',
                'cubricion' => 'Dos manos',
                'retencion' => 'Alta',
                'indispensable' => 'Buena adherencia',
            ],
            'application_payload' => [
                'metodo' => ['Airless'],
                'geometria' => 'Estructuras grandes',
                'manos' => 2,
                'repinte' => '24 horas',
                'servicio' => '72 horas',
                'antidescuelgue' => 'Normal',
                'vehiculo' => 'Base agua',
                'tecnologia' => 'Epóxica',
                'componentes' => '2K — Dos componentes',
                'ajustador' => 'No',
                'sistema_capas' => 'Imprimante epóxico + acabado poliuretano',
            ],
            'specifications_payload' => [
                'secados' => 'Tacto 2h, repinte 24h, servicio 7 días',
                'estabilidad' => '12 meses a 10–30 °C',
                'presentacion' => ['Galón', '5 galones'],
                'consumo' => '300 galones/mes',
                'frecuencia' => 'Mensual',
                'meta_competidor' => 'Igualar desempeño',
                'costo_objetivo' => '$28.000 COP/kg',
                'cantidad_prueba' => '5 galones',
                'aprobador' => fake()->name(),
                'forma_aprobacion' => 'Prueba industrial en campo',
                'criterios_aprobacion' => 'Aprobación por el cliente final',
            ],
            'schema_version' => 1,
            'created_by' => 1,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => PaintDevelopmentRequestStatus::Submitted,
        ]);
    }

    public function inReview(): static
    {
        return $this->state(fn () => [
            'status' => PaintDevelopmentRequestStatus::InReview,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => PaintDevelopmentRequestStatus::Approved,
        ]);
    }
}
