<?php

namespace App\Models\Alm;

use App\Enums\Alm\ClasificacionAbc;
use App\Enums\Alm\ProductoTipo;
use App\Models\Costos\Producto;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que Almacén guarda: un artículo con su área, su clasificación y su lugar
 * en la bodega.
 *
 * Es el catálogo propio del módulo, y su relación con Compras es una referencia
 * anulable, no una identidad. Un artículo puede existir sin producto —material
 * real que todavía no tiene identidad de compra, típicamente el que entró por la
 * carga inicial de un almacén— y ligarlo después es llenar una columna que
 * también se puede volver a vaciar.
 *
 * **Existir aquí es llevar kardex.** No hay bandera que lo diga: un servicio o
 * un flete simplemente no tiene artículo, y lo que Compras teclea al vuelo
 * tampoco lo tiene hasta que alguien lo clasifica. Como booleano esa respuesta
 * se podía desincronizar del hecho que describía, y se desincronizó.
 *
 * @use HasFactory<\Database\Factories\Alm\ArticuloFactory>
 */
class Articulo extends Model
{
    use HasFactory;

    protected $table = 'alm_articulos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'producto_id',
        'codigo',
        'codigo_barras',
        'descripcion',
        'unidad',
        'idsteelex',
        'area_id',
        'tipo',
        'se_controla_por_pieza',
        'requiere_verificacion',
        'stock_minimo',
        'clasificacion_abc',
        'imagen',
        'activo',
        'creado_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'tipo' => ProductoTipo::class,
            'se_controla_por_pieza' => 'boolean',
            'requiere_verificacion' => 'boolean',
            'stock_minimo' => 'decimal:3',
            'clasificacion_abc' => ClasificacionAbc::class,
        ];
    }

    /**
     * El producto de Compras con el que se cotiza y se compra este artículo.
     * Null es un estado legítimo, no un dato faltante: es material que existe en
     * la bodega y todavía no se empareja con nada del catálogo de Compras.
     *
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    /**
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    /**
     * Lo que todavía no se empareja con Compras. Es la bandeja de la pantalla de
     * ligado: material real esperando a que alguien diga con qué producto es el
     * mismo, o que confirme que es uno nuevo.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSinLigar(Builder $query): Builder
    {
        return $query->whereNull('producto_id');
    }

    /**
     * Lo que lleva identidad individual: cada pieza con su número de serie. El
     * saldo por cantidad no cambia —una pieza suma 1—, pero aquí sí se sabe
     * quién trae cuál.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePorPieza(Builder $query): Builder
    {
        return $query->where('se_controla_por_pieza', true);
    }
}
