<?php

namespace App\Models\Sti;

use App\Models\Departamento;
use App\Models\Media;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Ticket extends Model
{
    /** @use HasFactory<\Database\Factories\Sti\TicketFactory> */
    use HasFactory;

    protected $table = 'sti_tickets';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre_solicitante',
        'comentario',
        'tecnico_id',
        'equipo_id',
        'departamento_id',
        'firma_completado',
        'calificacion',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'calificacion' => 'integer',
        ];
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(Tecnico::class, 'tecnico_id');
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento_id');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(TicketHistorial::class, 'ticket_id')->orderBy('created_at', 'desc');
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function tags(): MorphMany
    {
        return $this->morphMany(Tag::class, 'statusable');
    }

    public function costos(): MorphMany
    {
        return $this->morphMany(CostoMantenimiento::class, 'costeable');
    }

    public function comentarios(): HasMany
    {
        return $this->hasMany(TicketComentario::class, 'ticket_id')->orderBy('created_at', 'asc');
    }
}
