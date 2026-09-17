<?php

namespace App\Models\Qal;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Un PDF subido a una sección del dosier, en el disco privado.
 *
 * `compatible` es falso cuando el motor de unión actual no lo puede abrir: se
 * guarda igual —el documento es del cliente—, pero queda fuera de la descarga
 * unida y la pantalla lo avisa.
 *
 * @use HasFactory<\Database\Factories\Qal\DossierArchivoFactory>
 */
class DossierArchivo extends Model
{
    use HasFactory;

    public const DISCO = 'local';

    protected $table = 'qal_dossier_archivos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'seccion_id',
        'orden',
        'nombre_original',
        'path',
        'size',
        'paginas',
        'compatible',
        'capturista_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'size' => 'integer',
            'paginas' => 'integer',
            'compatible' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<DossierSeccion, $this>
     */
    public function seccion(): BelongsTo
    {
        return $this->belongsTo(DossierSeccion::class, 'seccion_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function capturista(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'capturista_id');
    }

    public function rutaAbsoluta(): string
    {
        return Storage::disk(self::DISCO)->path($this->path);
    }
}
