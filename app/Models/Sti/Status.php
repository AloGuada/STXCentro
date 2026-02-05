<?php

namespace App\Models\Sti;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Status extends Model
{
    /** @use HasFactory<\Database\Factories\Sti\StatusFactory> */
    use HasFactory;

    protected $table = 'sti_status';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'descripcion',
        'orden',
        'detiene_tiempo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'detiene_tiempo' => 'boolean',
        ];
    }

    public function historial(): HasMany
    {
        return $this->hasMany(TicketHistorial::class, 'status_id');
    }
}
