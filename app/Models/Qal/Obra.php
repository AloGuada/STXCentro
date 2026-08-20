<?php

namespace App\Models\Qal;

use App\Models\Obra as ObraDelPortal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * La obra vista desde Calidad.
 *
 * Es entidad propia y no un alias de `obras`: Calidad tiene que poder dar de
 * alta lo suyo sin esperar a que la obra esté completa en el portal, y las
 * etapas ya cuelgan de aquí. Pero deja de ser una isla — con `obra_id` puesto,
 * el cliente, el contrato y el lugar **se leen de la obra del portal** en vez de
 * volver a teclearse.
 */
class Obra extends Model
{
    use HasFactory;

    protected $table = 'qal_obras';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_id',
        'no',
        'descripcion',
        'responsable_calidad',
        'pnd_nota',
        'pz_total',
        'activa',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
            'pz_total' => 'integer',
        ];
    }

    public function etapas(): HasMany
    {
        return $this->hasMany(Etapa::class, 'obra_id');
    }

    /**
     * La obra del portal. De ahí salen cliente, contrato y lugar; aquí no se
     * copian, porque duplicarlos es garantizar que dejen de coincidir.
     */
    public function obra(): BelongsTo
    {
        return $this->belongsTo(ObraDelPortal::class, 'obra_id');
    }

    /**
     * @return HasMany<ObraPndPlan, $this>
     */
    public function pndPlan(): HasMany
    {
        return $this->hasMany(ObraPndPlan::class, 'qal_obra_id');
    }

    /**
     * Los informes de laboratorio de la obra. Contra el plan de arriba se mide
     * el avance de PND.
     *
     * @return HasMany<PndReporte, $this>
     */
    public function pndReportes(): HasMany
    {
        return $this->hasMany(PndReporte::class, 'qal_obra_id');
    }
}
