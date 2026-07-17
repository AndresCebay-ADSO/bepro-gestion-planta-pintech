<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\QuotationStatus;
use App\Enums\QuotationValidity;
use App\Models\Client;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(4, 100000, 5000000);
        $ivaAmount = round($subtotal * 0.19, 4);

        return [
            'client_id' => Client::factory(),
            'client_business_name' => $this->faker->company(),
            'client_nit' => $this->faker->numerify('##########'),
            'client_contact_name' => $this->faker->name(),
            'client_phone' => $this->faker->phoneNumber(),
            'quotation_number' => $this->faker->unique()->numberBetween(1, 99999),
            'technology' => $this->faker->randomElement(['Alquídico', 'Epóxico', 'Poliuretano']),
            'line' => $this->faker->randomElement(['Core Series', 'Industrial Series']),
            'thickness_mils' => $this->faker->optional()->numerify('##'),
            'application_method' => $this->faker->optional()->word(),
            'quotation_date' => now()->toDateString(),
            'validity_days' => QuotationValidity::ThirtyDays->value,
            'payment_method' => $this->faker->randomElement(PaymentMethod::cases())->value,
            'delivery_time' => $this->faker->randomElement(['2 Días Hab.', '5 Días Hab.']),
            'area' => $this->faker->optional()->word(),
            'notes' => $this->faker->optional()->sentence(),
            'subtotal' => $subtotal,
            'iva_percentage' => 19,
            'iva_amount' => $ivaAmount,
            'total' => $subtotal + $ivaAmount,
            'status' => QuotationStatus::Draft->value,
            'created_by' => User::factory(),
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => ['status' => QuotationStatus::Sent->value]);
    }
}
