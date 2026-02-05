<?php

namespace App\Models\Sti;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CostoMantenimiento extends Model
{
    protected $table = 'sti_costos_mantenimientos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'descripcion',
        'cantidad',
        'costeable_id',
        'costeable_type',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
        ];
    }

    public function costeable(): MorphTo
    {
        return $this->morphTo();
    }
}
