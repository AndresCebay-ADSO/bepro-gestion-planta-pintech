<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity as ConcernsLogsActivity;
use Spatie\Activitylog\Support\LogOptions as SupportLogOptions;

class Formula extends Model
{
    use ConcernsLogsActivity, HasFactory, SoftDeletes;

    public function getActivitylogOptions(): SupportLogOptions
    {
        return SupportLogOptions::defaults()
            ->logOnly(['product_id', 'version', 'is_active', 'notes'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected $table = 'formulas';

    protected $fillable = [
        'product_id',
        'version',
        'is_active',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(FormulaDetail::class, 'formula_id');
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class, 'formula_id');
    }

    public function productionCosts(): HasMany
    {
        return $this->hasMany(ProductionCost::class, 'formula_id');
    }
}
