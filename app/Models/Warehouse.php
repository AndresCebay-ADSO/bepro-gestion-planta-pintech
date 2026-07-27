<?php

namespace App\Models;

use App\Enums\WarehouseType;
use App\Models\Concerns\HasAuditDescription;
use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $name
 * @property string $city
 * @property string|null $address
 * @property WarehouseType $type
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection|FinishedInventory[] $finishedInventories
 * @property-read Collection|ProductionOrder[] $productionOrders
 * @property-read Collection|FinishedInventoryMovement[] $finishedInventoryMovements
 * @property-read Collection|FinishedProductBatchStock[] $finishedProductBatchStocks
 * @property-read Collection|User[] $users
 */
#[Fillable([
    'name',
    'city',
    'address',
    'type',
    'is_active',
])]
class Warehouse extends Model
{
    /** @use HasFactory<WarehouseFactory> */
    use HasAuditDescription, HasFactory, LogsActivity, SoftDeletes;

    protected string $auditLabel = 'Bodega';

    protected string $auditIdentifierAttribute = 'name';

    protected bool $auditFeminine = true;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('bodegas')
            ->setDescriptionForEvent(fn (string $eventName) => $this->getAuditDescription($eventName))
            ->logOnly(['name', 'city', 'address', 'type', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'type' => WarehouseType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include active warehouses.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function isFactory(): bool
    {
        return $this->type === WarehouseType::Factory;
    }

    public function canProduce(): bool
    {
        return $this->isFactory();
    }

    public function finishedInventories(): HasMany
    {
        return $this->hasMany(FinishedInventory::class, 'warehouse_id');
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class, 'warehouse_id');
    }

    public function finishedInventoryMovements(): HasMany
    {
        return $this->hasMany(FinishedInventoryMovement::class, 'warehouse_id');
    }

    public function finishedProductBatchStocks(): HasMany
    {
        return $this->hasMany(FinishedProductBatchStock::class, 'warehouse_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'warehouse_user')
            ->withPivot('is_default')
            ->withTimestamps();
    }
}
