<?php

namespace App\Models\Alm;

use App\Enums\Alm\DocumentoAlm;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Una raya de firma del formato impreso de un documento de Almacén.
 *
 * El renglón siempre tiene rótulo. Arriba de la raya se imprime, en este
 * orden: los usuarios elegidos, si los hay; si no, el `nombre` escrito a mano;
 * y si tampoco, nada, para firmarse sobre la hoja.
 *
 * @use HasFactory<\Database\Factories\Alm\FirmaDocumentoFactory>
 */
class FirmaDocumento extends Model
{
    use HasFactory;

    protected $table = 'alm_firmas_documento';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'almacen_id',
        'documento',
        'orden',
        'rotulo',
        'nombre',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'documento' => DocumentoAlm::class,
            'orden' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Almacen, $this>
     */
    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_id');
    }

    /**
     * Los usuarios que pueden firmar este renglón. Sin ninguno, la raya cae
     * en el `nombre` escrito, o se queda en blanco.
     *
     * @return BelongsToMany<Usuario, $this>
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(
            Usuario::class,
            'alm_firma_documento_usuarios',
            'firma_documento_id',
            'usuario_id',
        );
    }
}
