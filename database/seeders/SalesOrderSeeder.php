<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SalesOrderPriority;
use App\Enums\SalesOrderStatus;
use App\Enums\SystemRole;
use App\Models\Client;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use App\Support\DeploymentEnvironment;
use Illuminate\Database\Seeder;

/**
 * Pedidos de demostración: tres clientes ficticios con dos pedidos cada uno. Datos fijos, sin factories: las factories
 * usan Faker, que es una dependencia de desarrollo y no está en la imagen del servidor, y estos datos también se siembran
 * en staging.
 */
class SalesOrderSeeder extends Seeder
{
    public function run(): void
    {
        if (! DeploymentEnvironment::allowsDemoData()) {
            return;
        }

        if (SalesOrder::query()->exists()) {
            return;
        }

        // En staging no hay usuarios sembrados: el primero es el superadministrador creado con `users:create-admin`.
        $creator = User::query()->role(SystemRole::Commercial->value)->first()
            ?? User::query()->role(SystemRole::Admin->value)->first()
            ?? User::query()->role(SystemRole::SuperAdmin->value)->first();

        if ($creator === null) {
            $this->command?->warn('SalesOrderSeeder omitido: no hay un usuario que figure como creador. Crea el primero con: php artisan users:create-admin');

            return;
        }

        $variants = ProductVariant::query()->where('is_active', true)->orderBy('id')->limit(6)->get();

        if ($variants->isEmpty()) {
            $this->command?->warn('SalesOrderSeeder omitido: no hay presentaciones. Corre antes ProductVariantSeeder.');

            return;
        }

        $demoClients = [
            ['business_name' => 'Demo Cliente Alpha', 'nit' => '900100001'],
            ['business_name' => 'Demo Cliente Beta', 'nit' => '900100002'],
            ['business_name' => 'Demo Cliente Gamma', 'nit' => '900100003'],
        ];

        // Dos pedidos por cliente: uno pendiente y otro en curso, con una a tres líneas.
        $orders = [
            ['status' => SalesOrderStatus::Pending, 'priority' => SalesOrderPriority::Medium, 'days' => 10, 'lines' => [[0, 12], [1, 4]]],
            ['status' => SalesOrderStatus::InProgress, 'priority' => SalesOrderPriority::High, 'days' => 5, 'lines' => [[2, 20], [3, 6], [4, 2]]],
        ];

        foreach ($demoClients as $clientIndex => $clientData) {
            $client = Client::firstOrCreate(
                ['nit' => $clientData['nit']],
                [
                    'business_name' => $clientData['business_name'],
                    'contact_name' => 'Contacto Demo',
                    'phone' => '3000000000',
                ]
            );

            foreach ($orders as $order) {
                $salesOrder = SalesOrder::create([
                    'client_id' => $client->id,
                    'status' => $order['status']->value,
                    'priority' => $order['priority']->value,
                    'required_date' => now()->addDays($order['days'] + $clientIndex)->toDateString(),
                    'client_business_name' => $client->business_name,
                    'client_nit' => $client->nit,
                    'client_contact_name' => $client->contact_name,
                    'client_phone' => $client->phone,
                    'created_by' => $creator->id,
                ]);

                foreach ($order['lines'] as [$variantIndex, $quantity]) {
                    $variant = $variants[($variantIndex + $clientIndex) % $variants->count()];

                    SalesOrderItem::create([
                        'sales_order_id' => $salesOrder->id,
                        'product_id' => $variant->product_id,
                        'product_variant_id' => $variant->id,
                        'quantity' => $quantity,
                    ]);
                }
            }
        }
    }
}
