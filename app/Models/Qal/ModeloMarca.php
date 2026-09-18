<?php

namespace App\Models\Qal;

use App\Models\Concepto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Una marca del modelo 3D: su geometría (.glb), su ficha (.json) y sus
 * cordones.
 *
 * @use HasFactory<\Database\Factories\Qal\ModeloMarcaFactory>
 */
class ModeloMarca extends Model
{
    use HasFactory;

    protected $table = 'qal_modelo_marcas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'modelo_id',
        'marca',
        'archivo',
        'concepto_id',
        'nombre',
        'piezas',
        'peso_kg',
        'ensambles',
        'soldaduras',
        'juntas',
        'orificios',
        'bbox_mm',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'piezas' => 'integer',
            'peso_kg' => 'decimal:2',
            'ensambles' => 'integer',
            'soldaduras' => 'integer',
            'juntas' => 'integer',
            'orificios' => 'integer',
            'bbox_mm' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Modelo, $this>
     */
    public function modelo(): BelongsTo
    {
        return $this->belongsTo(Modelo::class, 'modelo_id');
    }

    /**
     * La marca de Producción, cuando es única en el catálogo vigente.
     *
     * @return BelongsTo<Concepto, $this>
     */
    public function concepto(): BelongsTo
    {
        return $this->belongsTo(Concepto::class, 'concepto_id');
    }

    /**
     * @return HasMany<ModeloCordon, $this>
     */
    public function cordones(): HasMany
    {
        return $this->hasMany(ModeloCordon::class, 'modelo_marca_id')->orderBy('numero');
    }

    public function glbUrl(): string
    {
        return $this->urlDelArchivo('glb');
    }

    public function fichaUrl(): string
    {
        return $this->urlDelArchivo('json');
    }

    /**
     * La url del archivo de la marca, **relativa al host**.
     *
     * Quien la pide es el navegador, y ese navegador puede ser la tablet de
     * planta entrando por la IP de la red: con la url absoluta que arma
     * `APP_URL` la tablet pedía el .glb a su propio `localhost` y el visor sólo
     * podía decir que no pudo cargar la geometría.
     */
    private function urlDelArchivo(string $extension): string
    {
        $url = Storage::disk('public')->url("qal/modelos/{$this->modelo_id}/marks/{$this->archivo}.{$extension}");

        return parse_url($url, PHP_URL_PATH) ?: $url;
    }
}
