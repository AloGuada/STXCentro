<?php

namespace App\Services\Costos;

use App\Enums\Costos\FacturaEstatus;
use App\Enums\Costos\SolicitudPagoEstatus;
use App\Models\Costos\Factura;
use App\Models\Costos\SolicitudPago;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Puntos de control post-cadena de Costos y Contabilidad. Reúne, según los
 * permisos del usuario, las Solicitudes de Pago y Facturas que ya pasaron la
 * cadena de firmas y esperan una confirmación manual. Es la fuente única del
 * listado (pantalla "Por confirmar") y del contador (badge del sidebar), para
 * que ambos no diverjan.
 */
class PuntosDeControl
{
    public const PERMISO_SP_COSTOS = 'costos.solicitudes.confirmar-costos';

    public const PERMISO_FACTURA_COSTOS = 'costos.facturas.aprobar';

    public const PERMISO_CONTABILIDAD = 'costos.facturas.aceptar-contabilidad';

    /**
     * Filas agrupadas por paso que el usuario puede confirmar.
     *
     * @return array{costos: Collection<int, array<string, mixed>>, contabilidad: Collection<int, array<string, mixed>>}
     */
    public function paraUsuario(Usuario $usuario): array
    {
        $costos = collect();
        $contabilidad = collect();

        if ($usuario->can(self::PERMISO_SP_COSTOS)) {
            $costos = $costos->concat($this->filasSolicitudesCostos());
        }

        if ($usuario->can(self::PERMISO_FACTURA_COSTOS)) {
            $costos = $costos->concat($this->filasFacturasCostos());
        }

        if ($usuario->can(self::PERMISO_CONTABILIDAD)) {
            $contabilidad = $contabilidad
                ->concat($this->filasSolicitudesContabilidad())
                ->concat($this->filasFacturasContabilidad());
        }

        return [
            'costos' => $costos->sortBy('fecha')->values(),
            'contabilidad' => $contabilidad->sortBy('fecha')->values(),
        ];
    }

    /**
     * Total de pendientes que el usuario puede confirmar (alimenta el badge).
     */
    public function contar(Usuario $usuario): int
    {
        $total = 0;

        if ($usuario->can(self::PERMISO_SP_COSTOS)) {
            $total += $this->querySolicitudesCostos()->count();
        }

        if ($usuario->can(self::PERMISO_FACTURA_COSTOS)) {
            $total += $this->queryFacturasCostos()->count();
        }

        if ($usuario->can(self::PERMISO_CONTABILIDAD)) {
            $total += $this->querySolicitudesContabilidad()->count()
                + $this->queryFacturasContabilidad()->count();
        }

        return $total;
    }

    private function querySolicitudesCostos(): Builder
    {
        return SolicitudPago::query()
            ->where('estatus', SolicitudPagoEstatus::Aprobada)
            ->where('confirmada_costos', false);
    }

    private function querySolicitudesContabilidad(): Builder
    {
        return SolicitudPago::query()
            ->where('estatus', SolicitudPagoEstatus::Aprobada)
            ->where('confirmada_costos', true)
            ->where('confirmada_contabilidad', false)
            ->where('tipo_pago', 'credito');
    }

    private function queryFacturasCostos(): Builder
    {
        return Factura::query()
            ->where('estatus', FacturaEstatus::PendienteAprobacion)
            ->where('aprobada_costos', false)
            ->whereDoesntHave('ordenCompra', fn (Builder $q) => $q->where('tipo_pago', 'contado'));
    }

    private function queryFacturasContabilidad(): Builder
    {
        return Factura::query()
            ->where('estatus', FacturaEstatus::PendientePago)
            ->where('aprobada_costos', true)
            ->where('aceptada_contabilidad', false)
            ->whereDoesntHave('ordenCompra', fn (Builder $q) => $q->where('tipo_pago', 'contado'));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function filasSolicitudesCostos(): Collection
    {
        return $this->querySolicitudesCostos()
            ->with('proveedor:id,razon_social,nombre_comercial')
            ->get()
            ->map(fn (SolicitudPago $sp) => $this->filaSolicitud($sp, 'costos', 'admin.costos.solicitudes-pago.confirmar-costos'));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function filasSolicitudesContabilidad(): Collection
    {
        return $this->querySolicitudesContabilidad()
            ->with('proveedor:id,razon_social,nombre_comercial')
            ->get()
            ->map(fn (SolicitudPago $sp) => $this->filaSolicitud($sp, 'contabilidad', 'admin.costos.solicitudes-pago.confirmar-contabilidad'));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function filasFacturasCostos(): Collection
    {
        return $this->queryFacturasCostos()
            ->with('proveedor:id,razon_social,nombre_comercial')
            ->get()
            ->map(fn (Factura $factura) => $this->filaFactura($factura, 'costos', 'admin.costos.facturas.aprobar-costos'));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function filasFacturasContabilidad(): Collection
    {
        return $this->queryFacturasContabilidad()
            ->with('proveedor:id,razon_social,nombre_comercial')
            ->get()
            ->map(fn (Factura $factura) => $this->filaFactura($factura, 'contabilidad', 'admin.costos.facturas.aceptar-contabilidad'));
    }

    /**
     * @return array<string, mixed>
     */
    private function filaSolicitud(SolicitudPago $sp, string $paso, string $accionRuta): array
    {
        return [
            'tipo' => 'solicitud_pago',
            'paso' => $paso,
            'id' => $sp->id,
            'folio' => $sp->folio,
            'proveedor' => $sp->proveedor?->razon_social,
            'proveedor_comercial' => $sp->proveedor?->nombre_comercial,
            'concepto' => $sp->concepto,
            'monto' => (float) $sp->monto_total,
            'moneda' => $sp->tipo_moneda ?? 'mxn',
            'fecha' => $sp->fecha_pago_solicitada?->toDateString(),
            'accion_url' => route($accionRuta, $sp),
            'detalle_href' => route('admin.costos.solicitudes-pago.show', $sp),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filaFactura(Factura $factura, string $paso, string $accionRuta): array
    {
        return [
            'tipo' => 'factura',
            'paso' => $paso,
            'id' => $factura->id,
            'folio' => $factura->folio,
            'proveedor' => $factura->proveedor?->razon_social,
            'proveedor_comercial' => $factura->proveedor?->nombre_comercial,
            'concepto' => $factura->folio_fiscal,
            'monto' => (float) $factura->total,
            'moneda' => $factura->moneda ?? 'mxn',
            'fecha' => $factura->fecha_factura?->toDateString(),
            'accion_url' => route($accionRuta, $factura),
            'detalle_href' => route('admin.costos.facturas.show', $factura),
        ];
    }

    /**
     * Filtra las filas por un texto libre contra las mismas columnas que se ven
     * en pantalla, sin distinguir mayúsculas ni acentos. La espeja
     * `filtrarPuntosControl` del front (resources/js/pages/admin/costos/
     * confirmaciones/index.tsx) para que el reporte salga idéntico a la tabla.
     *
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return Collection<int, array<string, mixed>>
     */
    public function filtrar(Collection $filas, ?string $busqueda): Collection
    {
        $termino = $this->normalizar((string) $busqueda);

        if ($termino === '') {
            return $filas->values();
        }

        return $filas
            ->filter(fn (array $fila): bool => str_contains($this->textoBuscable($fila), $termino))
            ->values();
    }

    /**
     * Todo lo que la fila muestra, concatenado y normalizado: se busca contra lo
     * que el usuario ve, incluyendo el monto con y sin separadores de miles.
     *
     * @param  array<string, mixed>  $fila
     */
    private function textoBuscable(array $fila): string
    {
        $monto = (float) ($fila['monto'] ?? 0);

        return $this->normalizar(implode(' ', [
            $fila['tipo'] === 'solicitud_pago' ? 'Solicitud de pago' : 'Factura',
            $fila['folio'] ?? '',
            $fila['proveedor'] ?? '',
            $fila['proveedor_comercial'] ?? '',
            $fila['concepto'] ?? '',
            number_format($monto, 2, '.', ''),
            number_format($monto, 2),
            strtoupper((string) ($fila['moneda'] ?? '')),
            $fila['fecha'] ? date('d/m/Y', strtotime((string) $fila['fecha'])) : '',
        ]));
    }

    /**
     * Minúsculas y sin acentos: "Cañón" y "canon" tienen que encontrarse.
     */
    private function normalizar(string $texto): string
    {
        return Str::lower(Str::ascii(trim($texto)));
    }
}
