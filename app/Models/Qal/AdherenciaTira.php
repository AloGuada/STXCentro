<?php

namespace App\Models\Qal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La clasificación de una tira de la prueba de adherencia (5A…0A, 5B…0B).
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
