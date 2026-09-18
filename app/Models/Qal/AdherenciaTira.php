<?php

namespace App\Models\Qal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una tira de la prueba de adherencia: con qué método se cortó y qué
 * clasificación dio (5A…0A con el método A, 5B…0B con el B).
 *
 * El método es de la tira y no de la prueba porque en ASTM D3359 lo decide el
 * espesor de la película, y las tres tiras no siempre caen sobre el mismo.
 */
class AdherenciaTira extends Model
{
    public $timestamps = false;

    protected $table = 'qal_adherencia_tiras';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'adherencia_id',
        'orden',
        'metodo',
        'clasificacion',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Adherencia, $this>
     */
    public function adherencia(): BelongsTo
    {
        return $this->belongsTo(Adherencia::class, 'adherencia_id');
    }
}
