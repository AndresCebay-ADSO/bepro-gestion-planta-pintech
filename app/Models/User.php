<?php

namespace App\Models;

use App\Enums\SystemRole;
use App\Models\Concerns\HasAuditDescription;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property string|null $job_title
 * @property string|null $signature_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection|Warehouse[] $warehouses
 */
#[Appends(['signature_url'])]
#[Fillable(['name', 'email', 'phone', 'password', 'is_active', 'last_login_at', 'job_title', 'signature_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasAuditDescription, HasFactory, HasRoles, LogsActivity, Notifiable;

    protected string $auditLabel = 'Usuario';

    protected string $auditIdentifierAttribute = 'name';

    protected static function booted(): void
    {
        static::deleted(function (User $user) {
            // La firma se borra solo si el borrado se confirma: si la transacción se revierte, el archivo sigue ahí.
            $path = $user->signature_path;

            if ($path) {
                DB::afterCommit(fn () => Storage::disk('public')->delete($path));
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('usuarios')
            ->setDescriptionForEvent(fn (string $eventName) => $this->getAuditDescription($eventName))
            ->logOnly(['name', 'email', 'phone', 'is_active', 'last_login_at', 'job_title', 'signature_path'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'job_title' => 'string',
            'phone' => 'string',
            'signature_path' => 'string',
        ];
    }

    public function signatureUrl(): Attribute
    {
        return Attribute::get(fn () => $this->signature_path
            ? Storage::disk('public')->url($this->signature_path)
            : null);
    }

    /**
     * Scope a query to only include active users.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Usuarios con el rol SuperAdmin. Junto a isSuperAdmin(), es la única consulta por rol de la aplicación: el resto
     * decide por permisos (test "no decide por nombre de rol").
     */
    public function scopeSuperAdmins(Builder $query): void
    {
        $query->role(SystemRole::SuperAdmin->value);
    }

    /**
     * Send the password reset notification.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'warehouse_user')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function defaultWarehouse(): ?Warehouse
    {
        return $this->warehouses()
            ->wherePivot('is_default', true)
            ->first();
    }

    /**
     * Quotations created by this user.
     *
     * @return HasMany<Quotation, $this>
     */
    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class, 'created_by');
    }

    /**
     * Indica si el usuario es autor de acciones auditadas. Es la única relación sin clave foránea: el resto (órdenes,
     * cotizaciones, movimientos…) lo protegen las claves foráneas `RESTRICT` al intentar el borrado
     * (docs/POLITICA_ELIMINACION.md §4).
     */
    public function hasActivity(): bool
    {
        return Activity::where('causer_type', self::class)->where('causer_id', $this->id)->exists();
    }

    /**
     * Indica si este usuario tiene, al menos, todos los permisos de otro. Es la regla contra la escalada de
     * privilegios: nadie gestiona a un usuario ni asigna un rol con permisos que él mismo no tiene.
     *
     * @param  iterable<string>  $permissions
     */
    public function holdsAllPermissions(iterable $permissions): bool
    {
        return collect($permissions)->diff($this->getAllPermissions()->pluck('name'))->isEmpty();
    }

    /**
     * Usuario de soporte técnico con acceso total (docs/MATRIZ_RBAC.md §0).
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(SystemRole::SuperAdmin->value);
    }
}
