<?php

namespace App\Models\Qal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una lectura del calibre: medición por lectura.
 */
class PinturaLectura extends Model
{
    public $timestamps = false;

    protected $table = 'qal_pintura_lecturas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pintura_id',
        'medicion',
        'lectura',
        'valor_mils',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'medicion' => 'integer',
            'lectura' => 'integer',
            'valor_mils' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Pintura, $this>
     */
    public function pintura(): BelongsTo
    {
        return $this->belongsTo(Pintura::class, 'pintura_id');
    }
}
