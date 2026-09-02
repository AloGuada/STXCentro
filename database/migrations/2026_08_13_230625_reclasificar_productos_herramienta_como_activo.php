<?php

use App\Enums\Alm\ProductoTipo;
use App\Models\Costos\Producto;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * `herramienta` dejó de ser un tipo de artículo: se comporta igual que el
     * activo —sale y regresa— y separarlos sólo obligaba a decidir en el alta
     * de qué lado cae una pulidora.
     *
     * Se limpia el dato porque el modelo castea la columna al enum: un renglón
     * con el valor viejo revienta al leerlo, no al escribirlo, así que se
     * notaría hasta que alguien abriera el catálogo.
     */
    public function up(): void
    {
        Producto::query()
            ->where('tipo', 'herramienta')
            ->update(['tipo' => ProductoTipo::Activo->value]);
    }

    /**
     * No se puede deshacer: ya no hay forma de saber cuáles activos venían de
     * ser herramienta, y tampoco importa — el comportamiento es el mismo.
     */
    public function down(): void {}
};
