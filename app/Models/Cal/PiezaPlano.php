<?php

namespace App\Models\Cal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PiezaPlano extends Model
{
    use HasFactory;

    protected $table = 'cal_piezas_planos';

    protected $fillable = [
        'pieza_id',
        'pdf_path',
        'plano_normal',
        'dwg_path',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
        ];
    }

    public function pieza(): BelongsTo
    {
        return $this->belongsTo(Pieza::class, 'pieza_id');
    }

    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class, 'plano_id');
    }
}
