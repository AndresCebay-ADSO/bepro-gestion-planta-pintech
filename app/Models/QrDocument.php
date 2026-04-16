<?php

namespace App\Models;

use App\Enums\QrDocumentType;
use Database\Factories\QrDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $qr_code_id
 * @property QrDocumentType $document_type
 * @property string $file_name
 * @property string $file_path
 * @property int $version
 * @property bool $is_current
 * @property int $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read QrCode $qrCode
 * @property-read User $uploadedBy
 */
#[Fillable([
    'qr_code_id',
    'document_type',
    'file_name',
    'file_path',
    'version',
    'is_current',
    'uploaded_by',
])]
class QrDocument extends Model
{
    /** @use HasFactory<QrDocumentFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'document_type' => QrDocumentType::class,
            'version' => 'integer',
            'is_current' => 'boolean',
        ];
    }

    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(QrCode::class, 'qr_code_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
