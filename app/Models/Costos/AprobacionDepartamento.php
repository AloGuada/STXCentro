<?php

namespace App\Models\Costos;

use App\Models\Departamento;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @use HasFactory<\Database\Factories\Costos\AprobacionDepartamentoFactory>
 */
class AprobacionDepartamento extends Model
{
    use HasFactory;

    protected $table = 'costos_aprobacion_departamento';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'departamento_id',
        'permiso_id',
        'aprobador_id',
    ];

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    public function permiso(): BelongsTo
    {
        return $this->belongsTo(Permiso::class);
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'aprobador_id');
    }
}
