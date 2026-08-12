<?php

namespace App\Models\Qal;

use App\Enums\Qal\FaseTransformacion;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * En qué transformación trabaja un inspector.
 *
 * No decide qué puede hacer —eso lo llevan los roles—, sino qué formulario se le
 * abre por defecto en la tablet. Vive aparte de `usuarios` porque es una
 * preferencia de un solo módulo.
 *
 * @use HasFactory<\Database\Factories\Qal\InspectorFactory>
 */
class Inspector extends Model
{
    use HasFactory;

    protected $table = 'qal_inspectores';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'fase',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fase' => FaseTransformacion::class,
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
