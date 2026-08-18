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
    public function entradas(): Response
    {
        return Inertia::render('admin/almacen/entradas/index');
    }

    public function entradaCreate(): Response
    {
        return Inertia::render('admin/almacen/entradas/create');
    }

    public function devoluciones(): Response
    {
        return Inertia::render('admin/almacen/devoluciones/index');
    }

    public function devolucionCreate(): Response
    {
        return Inertia::render('admin/almacen/devoluciones/create');
    }

    public function prestamos(): Response
    {
        return Inertia::render('admin/almacen/prestamos/index');
    }

    public function prestamoCreate(): Response
    {
        return Inertia::render('admin/almacen/prestamos/create');
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
