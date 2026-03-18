<?php

namespace App\Models\Cal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Flecha extends Model
{
    use HasFactory;

    protected $table = 'cal_flechas';

    protected $fillable = [
        'reporte_id',
        'inicio_x',
        'inicio_y',
        'fin_x',
        'fin_y',
        'esdoble',
        'tipo',
        'show_number',
        'pagina',
    ];

    protected function casts(): array
    {
        return [
            'inicio_x' => 'decimal:4',
            'inicio_y' => 'decimal:4',
            'fin_x' => 'decimal:4',
            'fin_y' => 'decimal:4',
            'esdoble' => 'boolean',
            'show_number' => 'boolean',
        ];
    }

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(Reporte::class, 'reporte_id');
    }
}
