<?php

namespace App\Models\Qal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un defecto marcado en una inspección, con cuántas veces apareció.
 */
class InspeccionDefecto extends Model
{
    public $timestamps = false;

    protected $table = 'qal_inspeccion_defectos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'inspeccion_id',
        'defecto_id',
        'cantidad',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Inspeccion, $this>
     */
    public function inspeccion(): BelongsTo
    {
        return $this->belongsTo(Inspeccion::class, 'inspeccion_id');
    }

    /**
     * @return BelongsTo<Defecto, $this>
     */
    public function defecto(): BelongsTo
    {
        return $this->belongsTo(Defecto::class, 'defecto_id');
    }
}
