<?php

namespace Database\Factories;

use App\Enums\SalesOrderPriority;
use App\Enums\SalesOrderStatus;
use App\Models\Client;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesOrder>
 */
class SalesOrderFactory extends Factory
{
    protected $model = SalesOrder::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'priority' => $this->faker->randomElement(SalesOrderPriority::cases())->value,
            'status' => $this->faker->randomElement(SalesOrderStatus::cases())->value,
            'required_date' => $this->faker->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d'),
            'estimated_delivery_date' => $this->faker->optional(0.5)->dateTimeBetween('+5 days', '+45 days')?->format('Y-m-d'),
            'notes' => $this->faker->optional(0.3)->sentence(),
            'shipping_address' => $this->faker->optional(0.5)->address(),
            'client_business_name' => $this->faker->company(),
            'client_nit' => $this->faker->numerify('###########'),
            'client_contact_name' => $this->faker->name(),
            'client_phone' => $this->faker->phoneNumber(),
            'created_by' => User::factory(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SalesOrderStatus::Pending->value,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SalesOrderStatus::InProgress->value,
        ]);
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SalesOrderStatus::Ready->value,
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SalesOrderStatus::Delivered->value,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SalesOrderStatus::Cancelled->value,
        ]);
    }
}
