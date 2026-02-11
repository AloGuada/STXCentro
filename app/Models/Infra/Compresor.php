<?php

namespace App\Models\Infra;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Compresor extends Model
{
    /** @use HasFactory<\Database\Factories\Infra\CompresorFactory> */
    use HasFactory;

    protected $table = 'infra_compresores';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'compresor_1_status',
        'compresor_1_presion_aire',
        'compresor_1_tiempo_trabajo',
        'compresor_1_tiempo_marcha',
        'compresor_1_kwhr',
        'compresor_2_status',
        'compresor_2_presion_aire',
        'compresor_2_tiempo_trabajo',
        'compresor_2_tiempo_marcha',
        'compresor_2_kwhr',
        'compresor_3_status',
        'compresor_3_presion_aire',
        'compresor_3_tiempo_trabajo',
        'compresor_3_tiempo_marcha',
        'compresor_3_kwhr',
        'observaciones',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'compresor_1_status' => 'boolean',
            'compresor_2_status' => 'boolean',
            'compresor_3_status' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
