<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class SalesOrderSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        if (SalesOrder::query()->exists()) {
            return;
        }

        $creator = User::query()->role('comercial')->first()
            ?? User::query()->role('admin')->first();

        if ($creator === null) {
            return;
        }

        $demoClients = [
            ['business_name' => 'Demo Cliente Alpha', 'nit' => '900100001'],
            ['business_name' => 'Demo Cliente Beta', 'nit' => '900100002'],
            ['business_name' => 'Demo Cliente Gamma', 'nit' => '900100003'],
        ];

        foreach ($demoClients as $clientData) {
            $client = Client::updateOrCreate(
                ['nit' => $clientData['nit']],
                [
                    'business_name' => $clientData['business_name'],
                    'contact_name' => 'Contacto Demo',
                    'phone' => '3000000000',
                ]
            );

            SalesOrder::factory()
                ->count(2)
                ->create([
                    'client_id' => $client->id,
                    'created_by' => $creator->id,
                ])
                ->each(function (SalesOrder $order): void {
                    SalesOrderItem::factory()
                        ->count(rand(1, 3))
                        ->create(['sales_order_id' => $order->id]);
                });
        }
    }
}
