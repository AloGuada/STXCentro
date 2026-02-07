<?php

namespace App\Models\Sti;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketComentario extends Model
{
    protected $table = 'sti_ticket_comentarios';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ticket_id',
        'comentario',
        'autor',
        'tipo',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }
}
