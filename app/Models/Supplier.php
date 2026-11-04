<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TextFormat;
use App\Models\Concerns\HasAuditDescription;
use App\Models\Concerns\SelectableWhenActive;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Proveedor de materia prima, en Configuración → Catálogos. Se elige al registrar la entrada que crea un lote.
 *
 * @property int $id
 * @property string $name
 * @property string|null $nit
 * @property string|null $contact_name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $notes
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection|InventoryBatch[] $inventoryBatches
 * @property-read int|null $inventory_batches_count
 */
#[Fillable([
    'name',
    'nit',
    'contact_name',
    'phone',
    'email',
    'notes',
    'is_active',
])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasAuditDescription, HasFactory, LogsActivity, SelectableWhenActive;

    public const NAME_MAX_LENGTH = 150;

    public const NOTES_MAX_LENGTH = 2000;

    protected string $auditLabel = 'Proveedor';

    protected string $auditIdentifierAttribute = 'name';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('proveedores')
            ->setDescriptionForEvent(fn (string $eventName) => $this->getAuditDescription($eventName))
            ->logOnly(['name', 'nit', 'contact_name', 'phone', 'email', 'notes', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'name' => TextFormat::Name->cast(),
            'nit' => TextFormat::Name->cast(),
            'is_active' => 'boolean',
        ];
    }

    /**
     * Lotes de este proveedor. Con alguno, ya no se elimina: se desactiva.
     */
    public function inventoryBatches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class, 'supplier_id');
    }
}
