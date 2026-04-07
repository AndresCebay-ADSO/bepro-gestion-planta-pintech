<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class QrDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'qr_documents';

    protected $fillable = [
        'qr_code_id',
        'document_type',
        'file_name',
        'file_path',
        'version',
        'is_current',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
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
