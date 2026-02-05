<?php

namespace App\Models\Sti;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketHistorial extends Model
{
    protected $table = 'sti_ticket_historial';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ticket_id',
        'status_id',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'status_id');
    }
}
