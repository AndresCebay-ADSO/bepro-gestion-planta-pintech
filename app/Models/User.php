<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection|Warehouse[] $warehouses
 */
#[Fillable(['name', 'email', 'password', 'is_active', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('usuarios')
            ->setDescriptionForEvent(fn(string $eventName) => "Usuario {$eventName}")
            ->logOnly(['name', 'email', 'is_active', 'last_login_at'])
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
        ];
    }

    /**
     * Scope a query to only include active users.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
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
     * Check if the user has any activity or related records in the system.
     */
    public function hasActivity(): bool
    {
        return DB::table('production_orders')->where('created_by', $this->id)->exists()
            || DB::table('formulas')->where('created_by', $this->id)->exists()
            || DB::table('transfers')->where('created_by', $this->id)->exists()
            || DB::table('inventory_movements')->where('created_by', $this->id)->exists()
            || DB::table('finished_inventory_movements')->where('created_by', $this->id)->exists()
            || DB::table('qr_codes')->where('created_by', $this->id)->exists()
            || DB::table('qr_documents')->where('uploaded_by', $this->id)->exists()
            || DB::table('product_documents')->where('uploaded_by', $this->id)->exists()
            || Activity::where('causer_type', self::class)->where('causer_id', $this->id)->exists();
    }
}
