<?php

namespace App\Models\Qal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un defecto de una unidad rechazada de la muestra del sublote.
 */
class SubloteDefecto extends Model
{
    public $timestamps = false;

    protected $table = 'qal_sublote_defectos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sublote_id',
        'unidad',
        'defecto_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unidad' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Sublote, $this>
     */
    public function sublote(): BelongsTo
    {
        return $this->belongsTo(Sublote::class, 'sublote_id');
    }

    /**
     * @return BelongsTo<Defecto, $this>
     */
    public function defecto(): BelongsTo
    {
        return $this->belongsTo(Defecto::class, 'defecto_id');
    }
}
