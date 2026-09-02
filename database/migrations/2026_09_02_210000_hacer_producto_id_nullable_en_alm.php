<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `producto_id` deja de ser obligatorio en las siete tablas.
     *
     * Es lo que faltaba para que la carga inicial de un almacén pueda entrar
     * como debe: creando artículos **sueltos**, sin inventarle a Compras un
     * producto por cada renglón de un layout que nadie revisó uno por uno.
     * Mientras la columna exigiera valor, abrir un almacén obligaba a darle
     * identidad de compra a cosas que la empresa quizá nunca vuelva a comprar
     * —y a arriesgarse a pisar la de las que sí—.
     *
     * Relajar no pierde nada: los 148 renglones que ya existen conservan su
     * producto, y la comprobación de `alm:verificar-articulos` sigue leyéndolos
     * igual, porque pregunta por los que *tienen* producto y no por todos.
     *
     * La columna se va entera en la fase B. Esto sólo adelanta la parte que la
     * carga inicial necesita para poder correr ya.
     */
    private const TABLAS = [
        'alm_existencias',
        'alm_movimientos',
        'alm_ajuste_detalle',
        'alm_pedido_detalle',
        'alm_salida_detalle',
        'alm_transferencia_detalle',
        'alm_activos',
    ];

    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->unsignedBigInteger('producto_id')->nullable()->change();
            });
        }
    }

    /**
     * No se puede volver atrás si ya entró material sin producto, que es
     * justamente para lo que sirve esta migración. Se intenta, y si hay
     * renglones sueltos la base lo impide con su propio error, que dice más
     * que cualquier excepción que se lanzara aquí.
     */
    public function down(): void
    {
        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->unsignedBigInteger('producto_id')->nullable(false)->change();
            });
        }
    }
};
