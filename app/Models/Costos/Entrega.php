<?php

namespace App\Models\Costos;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @use HasFactory<\Database\Factories\Costos\EntregaFactory>
 */
class Entrega extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'costos_entregas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'orden_compra_id',
        'recibido_por',
        'fecha_entrega',
        'observaciones',
        'tipo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_entrega' => 'date',
        ];
    }

    public function media(): MorphOne
    {
        return $this->morphOne(\App\Models\Media::class, 'mediable');
    }

    public function ordenCompra(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class, 'orden_compra_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(EntregaDetalle::class, 'entrega_id');
    }

    public function recibidoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'recibido_por');
    }

    public function activities(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->activitiesAsSubject();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('costos')
            ->logOnly(['orden_compra_id', 'fecha_entrega', 'tipo'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => "Entrega ({$this->tipo}): {$event}");
    }
}
