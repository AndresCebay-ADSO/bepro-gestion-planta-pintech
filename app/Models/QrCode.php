<?php

namespace App\Models;

use Database\Factories\QrCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property string $token
 * @property string $url
 * @property bool $is_active
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Product $product
 * @property-read User $createdBy
 * @property-read Collection|QrDocument[] $documents
 */
#[Fillable([
    'product_id',
    'token',
    'url',
    'is_active',
    'created_by',
])]
class QrCode extends Model
{
    /** @use HasFactory<QrCodeFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include active QR codes.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(QrDocument::class, 'qr_code_id');
    }
}
