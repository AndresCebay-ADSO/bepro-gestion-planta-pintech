<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionCost extends Model
{
    use HasFactory;

    protected $table = 'production_costs';

    protected $fillable = [
        'product_id',
        'formula_id',
        'cost',
        'variation_percentage',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:4',
            'variation_percentage' => 'decimal:4',
            'calculated_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(Formula::class, 'formula_id');
    }
}
