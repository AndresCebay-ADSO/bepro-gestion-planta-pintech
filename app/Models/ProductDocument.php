<?php

namespace App\Models;

use App\Enums\QrDocumentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property QrDocumentType $document_type
 * @property string $file_name
 * @property string $file_path
 * @property int $file_size
 * @property string $mime_type
 * @property int $version
 * @property bool $is_current
 * @property int $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Product $product
 * @property-read User $uploadedBy
 */
#[Fillable([
    'product_id',
    'document_type',
    'file_name',
    'file_path',
    'file_size',
    'mime_type',
    'version',
    'is_current',
    'uploaded_by',
])]
class ProductDocument extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'document_type' => QrDocumentType::class,
            'file_size' => 'integer',
            'version' => 'integer',
            'is_current' => 'boolean',
        ];
    }

    public function scopeCurrent(Builder $query): void
    {
        $query->where('is_current', true);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
