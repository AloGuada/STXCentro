<?php

namespace App\Models\Costos;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @use HasFactory<\Database\Factories\Costos\AnticipoAplicacionFactory>
 */
class AnticipoAplicacion extends Model
{
    use HasFactory;

    protected $table = 'costos_anticipo_aplicaciones';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'anticipo_id',
        'factura_id',
        'monto',
        'fecha',
        'usuario_id',
        'notas',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha' => 'date',
        ];
    }

    public function anticipo(): BelongsTo
    {
        return $this->belongsTo(Anticipo::class, 'anticipo_id');
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class, 'factura_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
