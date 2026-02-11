<?php

namespace App\Models\Prod;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Extra extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\ExtraFactory> */
    use HasFactory;

    protected $table = 'prod_extras';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'liquidacion_id',
        'descripcion',
        'monto',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
        ];
    }

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(Liquidacion::class, 'liquidacion_id');
    }
}
