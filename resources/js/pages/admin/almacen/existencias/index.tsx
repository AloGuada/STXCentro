import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { ALMACENES_DEMO, EXISTENCIAS_DEMO, rutaUbicacion, ubicacionesDe } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { HistoryIcon } from 'lucide-react';
import { useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Almacén', href: '/admin/almacen/existencias' },
    { title: 'Existencias', href: '/admin/almacen/existencias' },
];

const moneda = (n: number) => n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
const cantidad = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

/**
 * La pantalla de diario: qué hay y cuánto en cada almacén. Sin filtro de almacén
 * se ve el consolidado de la empresa; con filtro, el inventario de esa bodega.
 */
export default function ExistenciasIndex() {
    const [almacen, setAlmacen] = useState('');
    const [ubicacion, setUbicacion] = useState('');
    const [busqueda, setBusqueda] = useState('');
    const [soloConSaldo, setSoloConSaldo] = useState(false);

    // Filtrar por lugar sólo tiene sentido dentro de un almacén: el "Rack A-1"
    // de AG no es el de FAK.
    const ubicaciones = almacen ? ubicacionesDe(almacen) : [];

    const filas = useMemo(() => {
        const texto = busqueda.toLowerCase();

        return EXISTENCIAS_DEMO.filter(
            (e) =>
                (!almacen || e.almacen === almacen) &&
                (!ubicacion || String(e.ubicacion_id) === ubicacion) &&
                (!soloConSaldo || e.cantidad > 0) &&
                (!texto ||
                    e.producto.toLowerCase().includes(texto) ||
                    e.descripcion.toLowerCase().includes(texto)),
        );
    }, [almacen, ubicacion, busqueda, soloConSaldo]);

    const valorTotal = filas.reduce((suma, e) => suma + e.cantidad * e.costo_promedio, 0);
    const sinSaldo = filas.filter((e) => e.cantidad <= 0).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Existencias" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Existencias</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Saldo actual por almacén. Sale del kardex: aquí nada se edita a mano, se mueve con entradas,
                        salidas y transferencias.
                    </p>
                </div>

                <div className="alert alert-warning mb-4">
                    <span>Vista de maqueta: los datos son de ejemplo, todavía no hay backend.</span>
                </div>

                <div className="mb-4 flex flex-wrap items-end gap-3">
                    <div className="w-56">
                        <label className="label label-text text-xs">Almacén</label>
                        <Select
                            value={almacen}
                            onValueChange={(v) => {
                                setAlmacen(v);
                                setUbicacion('');
                            }}
                        >
                            <SelectItem value="">Todos los almacenes</SelectItem>
                            {ALMACENES_DEMO.map((a) => (
                                <SelectItem key={a.id} value={a.clave}>
                                    {a.clave} — {a.nombre}
                                    {a.obra ? ` (${a.obra})` : ''}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>

                    <div className="w-56">
                        <label className="label label-text text-xs">Ubicación</label>
                        <Select value={ubicacion} onValueChange={setUbicacion} disabled={!almacen}>
                            <SelectItem value="">{almacen ? 'Todo el almacén' : 'Elige un almacén'}</SelectItem>
                            {ubicaciones
                                .filter((u) => u.activa)
                                .map((u) => (
                                    <SelectItem key={u.id} value={String(u.id)}>
                                        {rutaUbicacion(u.id)}
                                    </SelectItem>
                                ))}
                        </Select>
                    </div>

                    <div className="w-72">
                        <label className="label label-text text-xs">Producto</label>
                        <Input
                            value={busqueda}
                            onChange={(e) => setBusqueda(e.target.value)}
                            placeholder="Buscar por código o descripción..."
                        />
                    </div>

                    <label className="mb-2 flex cursor-pointer items-center gap-2">
                        <input
                            type="checkbox"
                            className="checkbox checkbox-sm"
                            checked={soloConSaldo}
                            onChange={(e) => setSoloConSaldo(e.target.checked)}
                        />
                        <span className="text-sm">Sólo con saldo</span>
                    </label>
                </div>

                <div className="rounded-box border-base-300 overflow-hidden border">
                    <table className="table table-sm">
                        <thead className="bg-base-200">
                            <tr>
                                <th>Almacén</th>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>Ubicación</th>
                                <th className="text-right">Existencia</th>
                                <th className="text-right">Costo prom.</th>
                                <th className="text-right">Valor</th>
                                <th className="w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {filas.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="text-base-content/50 py-6 text-center">
                                        No hay existencias con esos filtros
                                    </td>
                                </tr>
                            ) : (
                                filas.map((e) => (
                                    <tr key={`${e.almacen}-${e.producto}`} className="hover">
                                        <td>
                                            <span className="badge badge-sm badge-ghost font-mono">{e.almacen}</span>
                                        </td>
                                        <td className="font-mono text-xs">{e.producto}</td>
                                        <td className="font-medium">{e.descripcion}</td>
                                        <td className="text-base-content/60 text-sm">
                                            {rutaUbicacion(e.ubicacion_id) ?? (
                                                <span className="text-base-content/40" title="Nadie le ha asignado lugar">
                                                    Sin acomodar
                                                </span>
                                            )}
                                        </td>
                                        <td className="text-right font-mono">
                                            <span className={e.cantidad <= 0 ? 'text-error font-semibold' : ''}>
                                                {cantidad(e.cantidad)}
                                            </span>
                                            <span className="text-base-content/40"> {e.unidad}</span>
                                        </td>
                                        <td className="text-right font-mono">{moneda(e.costo_promedio)}</td>
                                        <td className="text-right font-mono">
                                            {moneda(e.cantidad * e.costo_promedio)}
                                        </td>
                                        <td>
                                            <Link
                                                href={`/admin/almacen/kardex?almacen=${e.almacen}&producto=${e.producto}`}
                                                className="btn btn-ghost btn-xs"
                                                title="Ver kardex de este producto"
                                            >
                                                <HistoryIcon className="size-4" />
                                            </Link>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>

                    {filas.length > 0 && (
                        <div className="border-base-300 bg-base-200 flex flex-wrap items-center justify-between gap-2 border-t px-4 py-2 text-sm">
                            <span>
                                {filas.length} producto(s)
                                {sinSaldo > 0 && (
                                    <span className="text-error"> · {sinSaldo} en ceros</span>
                                )}
                            </span>
                            <span className="font-mono">Valor del inventario: {moneda(valorTotal)}</span>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
