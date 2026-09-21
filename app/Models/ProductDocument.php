<?php

namespace App\Models;

use App\Enums\QrDocumentType;
use App\Models\Concerns\HasAuditDescription;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

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
    use HasAuditDescription, LogsActivity;

    protected string $auditLabel = 'Documento de producto';

    protected string $auditIdentifierAttribute = 'file_name';

    /**
     * El archivo se borra solo si el borrado del registro se confirma: si la transacción se revierte (por ejemplo, el
     * producto tenía historial), el documento sigue intacto (docs/POLITICA_ELIMINACION.md §3.4).
     */
    protected static function booted(): void
    {
        static::deleted(function (ProductDocument $document): void {
            $path = $document->file_path;

            DB::afterCommit(fn () => Storage::disk('local')->delete($path));
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('productos')
            ->setDescriptionForEvent(fn (string $eventName) => $this->getAuditDescription($eventName))
            ->logOnly(['product_id', 'document_type', 'file_name', 'version', 'is_current'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

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
