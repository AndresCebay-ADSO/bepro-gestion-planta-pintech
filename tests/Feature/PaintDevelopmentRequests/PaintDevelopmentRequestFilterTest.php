<?php

declare(strict_types=1);

use App\Enums\PaintDevelopmentRequestStatus;
use App\Models\PaintDevelopmentRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');

    $this->comercial = User::factory()->create(['email_verified_at' => now()]);
    $this->comercial->assignRole('comercial');

    $this->requestA = PaintDevelopmentRequest::factory()->create([
        'status' => PaintDevelopmentRequestStatus::Draft->value,
        'project_name' => 'Test Project Alpha',
        'client_name' => 'Acme Corp',
        'sample_due_date' => '2026-01-10',
        'created_by' => $this->comercial->id,
    ]);

    $this->requestB = PaintDevelopmentRequest::factory()->create([
        'status' => PaintDevelopmentRequestStatus::Approved->value,
        'project_name' => 'Test Project Beta',
        'client_name' => 'Beta Industries',
        'sample_due_date' => '2026-02-20',
        'created_by' => $this->admin->id,
    ]);
});

it('renders paint development requests index for admin', function () {
    $this->actingAs($this->admin)
        ->get(route('paint-development-requests.index'))
        ->assertInertia(fn ($page) => $page
            ->component('PaintDevelopmentRequests/Index')
            ->has('requests.data', 2)
        );
});

it('shows only own requests to comercial users', function () {
    $this->actingAs($this->comercial)
        ->get(route('paint-development-requests.index'))
        ->assertInertia(fn ($page) => $page
            ->component('PaintDevelopmentRequests/Index')
            ->has('requests.data', 1)
            ->where('requests.data.0.id', $this->requestA->id)
        );
});

it('filters by status', function () {
    $this->actingAs($this->admin)
        ->get(route('paint-development-requests.index', ['status' => PaintDevelopmentRequestStatus::Draft->value]))
        ->assertInertia(fn ($page) => $page
            ->component('PaintDevelopmentRequests/Index')
            ->has('requests.data', 1)
            ->where('requests.data.0.id', $this->requestA->id)
        );
});

it('filters effectively by search term', function () {
    $this->actingAs($this->admin)
        ->get(route('paint-development-requests.index', ['search' => 'Alpha']))
        ->assertInertia(fn ($page) => $page
            ->component('PaintDevelopmentRequests/Index')
            ->has('requests.data', 1)
            ->where('requests.data.0.id', $this->requestA->id)
        );
});

it('filters by date range', function () {
    $this->actingAs($this->admin)
        ->get(route('paint-development-requests.index', [
            'date_from' => '2026-02-01',
            'date_to' => '2026-02-28',
        ]))
        ->assertInertia(fn ($page) => $page
            ->component('PaintDevelopmentRequests/Index')
            ->has('requests.data', 1)
            ->where('requests.data.0.id', $this->requestB->id)
        );
});

it('returns validated filters in inertia props', function () {
    $this->actingAs($this->admin)
        ->get(route('paint-development-requests.index', ['search' => 'alpha']))
        ->assertInertia(fn ($page) => $page
            ->where('filters.search', 'alpha')
        );
});
