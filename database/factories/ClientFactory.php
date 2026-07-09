<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'business_name' => $this->faker->company(),
            'nit' => $this->faker->numerify('###########'),
            'contact_name' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
        ];
    }
}
