<?php

namespace App\Http\Controllers\Admin\Alm;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Plomería mínima para poder abrir las maquetas del módulo.
 *
 * Estas pantallas todavía no tienen backend: se dibujan con los datos de
 * ejemplo de `resources/js/lib/alm/demo.ts` para revisar diseño y flujo. Cuando
 * cada pantalla gane su controlador de verdad, este archivo desaparece.
 */
class VistasController extends Controller
{
    public function existencias(): Response
    {
        return Inertia::render('admin/almacen/existencias/index');
    }

    public function kardex(): Response
    {
        return Inertia::render('admin/almacen/kardex/index');
    }

    public function entradas(): Response
    {
        return Inertia::render('admin/almacen/entradas/index');
    }

    public function entradaCreate(): Response
    {
        return Inertia::render('admin/almacen/entradas/create');
    }

    public function salidas(): Response
    {
        return Inertia::render('admin/almacen/salidas/index');
    }

    public function salidaCreate(): Response
    {
        return Inertia::render('admin/almacen/salidas/create');
    }

    public function transferencias(): Response
    {
        return Inertia::render('admin/almacen/transferencias/index');
    }

    public function transferenciaCreate(): Response
    {
        return Inertia::render('admin/almacen/transferencias/create');
    }

    /**
     * Segundo tiempo de la transferencia: el destino confirma qué llegó. Es el
     * mismo documento, no uno nuevo, así que vive en el show y no en un create.
     */
    public function transferenciaShow(int $transferencia): Response
    {
        return Inertia::render('admin/almacen/transferencias/show', ['transferenciaId' => $transferencia]);
    }

    public function devoluciones(): Response
    {
        return Inertia::render('admin/almacen/devoluciones/index');
    }

    public function devolucionCreate(): Response
    {
        return Inertia::render('admin/almacen/devoluciones/create');
    }

    public function ajustes(): Response
    {
        return Inertia::render('admin/almacen/ajustes/index');
    }

    public function ajusteCreate(): Response
    {
        return Inertia::render('admin/almacen/ajustes/create');
    }

    public function pedidos(): Response
    {
        return Inertia::render('admin/almacen/pedidos/index');
    }

    public function pedidoCreate(): Response
    {
        return Inertia::render('admin/almacen/pedidos/create');
    }

    public function prestamos(): Response
    {
        return Inertia::render('admin/almacen/prestamos/index');
    }

    public function prestamoCreate(): Response
    {
        return Inertia::render('admin/almacen/prestamos/create');
    }

    public function activos(): Response
    {
        return Inertia::render('admin/almacen/activos/index');
    }

    public function activoCreate(): Response
    {
        return Inertia::render('admin/almacen/activos/create');
    }

    public function conteos(): Response
    {
        return Inertia::render('admin/almacen/conteos/index');
    }

    public function conteoCreate(): Response
    {
        return Inertia::render('admin/almacen/conteos/create');
    }

    public function conteoShow(int $conteo): Response
    {
        return Inertia::render('admin/almacen/conteos/show', ['conteoId' => $conteo]);
    }

    public function etiquetas(): Response
    {
        return Inertia::render('admin/almacen/etiquetas/index');
    }

    public function aprobaciones(): Response
    {
        return Inertia::render('admin/almacen/aprobaciones/index');
    }
}
