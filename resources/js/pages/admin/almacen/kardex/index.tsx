import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { ALMACENES_DEMO, MOVIMIENTOS_DEMO, PRODUCTOS_DEMO } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import type { AlmMovimientoTipo } from '@/types/models';
import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Insumos', href: '/admin/almacen/existencias' },
    { title: 'Kardex', href: '/admin/almacen/kardex' },
];

const ETIQUETA_TIPO: Record<AlmMovimientoTipo, string> = {
    entrada: 'Entrada',
    salida: 'Salida',
    transferencia_entrada: 'Transf. entrada',
    transferencia_salida: 'Transf. salida',
    ajuste: 'Ajuste',
    devolucion: 'Devolución',
};

const CLASE_TIPO: Record<AlmMovimientoTipo, string> = {
    entrada: 'badge-success',
    salida: 'badge-error',
    transferencia_entrada: 'badge-info',
    transferencia_salida: 'badge-info badge-outline',
    ajuste: 'badge-warning',
    devolucion: 'badge-ghost',
};

const cantidad = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

/**
 * El libro de movimientos: cada renglón dice qué pasó y con qué saldo quedó el
 * producto en ese almacén. Es sólo lectura — corregir un error es capturar el
 * movimiento contrario, no borrar el renglón.
 */
export default function KardexIndex() {
    const params = new URLSearchParams(typeof window === 'undefined' ? '' : window.location.search);

    const [almacen, setAlmacen] = useState(params.get('almacen') ?? '');
    const [producto, setProducto] = useState(params.get('producto') ?? '');
    const [tipo, setTipo] = useState('');
    const [desde, setDesde] = useState('');
    const [hasta, setHasta] = useState('');

    const filas = useMemo(
        () =>
            MOVIMIENTOS_DEMO.filter(
                (m) =>
                    (!almacen || m.almacen === almacen) &&
                    (!producto || m.producto === producto) &&
                    (!tipo || m.tipo === tipo) &&
                    (!desde || m.fecha >= desde) &&
                    (!hasta || m.fecha <= `${hasta} 23:59`),
            ),
        [almacen, producto, tipo, desde, hasta],
    );

    const entradas = filas.filter((m) => m.cantidad > 0).reduce((s, m) => s + m.cantidad, 0);
    const salidas = filas.filter((m) => m.cantidad < 0).reduce((s, m) => s + m.cantidad, 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Kardex" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Kardex</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Todo lo que entró y salió, con el saldo que dejó cada movimiento. El saldo sólo es legible con
                        un almacén y un producto elegidos.
                    </p>
                </div>

                <div className="alert alert-warning mb-4">
                    <span>Vista de maqueta: los datos son de ejemplo, todavía no hay backend.</span>
                </div>

                <div className="mb-4 flex flex-wrap items-end gap-3">
                    <div className="w-52">
                        <label className="label label-text text-xs">Almacén</label>
                        <Select value={almacen} onValueChange={setAlmacen}>
                            <SelectItem value="">Todos</SelectItem>
                            {ALMACENES_DEMO.map((a) => (
                                <SelectItem key={a.id} value={a.clave}>
                                    {a.clave} — {a.nombre}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>

                    <div className="w-64">
                        <label className="label label-text text-xs">Producto</label>
                        <Select value={producto} onValueChange={setProducto}>
                            <SelectItem value="">Todos</SelectItem>
                            {PRODUCTOS_DEMO.map((p) => (
                                <SelectItem key={p.id} value={p.codigo}>
                                    {p.codigo} — {p.descripcion}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>

                    <div className="w-44">
                        <label className="label label-text text-xs">Tipo</label>
                        <Select value={tipo} onValueChange={setTipo}>
                            <SelectItem value="">Todos</SelectItem>
                            {Object.entries(ETIQUETA_TIPO).map(([valor, etiqueta]) => (
                                <SelectItem key={valor} value={valor}>
                                    {etiqueta}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>

                    <div className="w-40">
                        <label className="label label-text text-xs">Desde</label>
                        <Input type="date" value={desde} onChange={(e) => setDesde(e.target.value)} />
                    </div>

                    <div className="w-40">
                        <label className="label label-text text-xs">Hasta</label>
                        <Input type="date" value={hasta} onChange={(e) => setHasta(e.target.value)} />
                    </div>
                </div>

                <div className="rounded-box border-base-300 overflow-hidden border">
                    <table className="table table-sm">
                        <thead className="bg-base-200">
                            <tr>
                                <th>Fecha</th>
                                <th>Almacén</th>
                                <th>Producto</th>
                                <th>Tipo</th>
                                <th>Referencia</th>
                                <th className="text-right">Cantidad</th>
                                <th className="text-right">Saldo</th>
                                <th>Capturó</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filas.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="text-base-content/50 py-6 text-center">
                                        No hay movimientos con esos filtros
                                    </td>
                                </tr>
                            ) : (
                                filas.map((m) => (
                                    <tr key={m.id} className="hover">
                                        <td className="font-mono text-xs whitespace-nowrap">{m.fecha}</td>
                                        <td>
                                            <span className="badge badge-sm badge-ghost font-mono">{m.almacen}</span>
                                        </td>
                                        <td className="font-mono text-xs">{m.producto}</td>
                                        <td>
                                            <span className={`badge badge-sm ${CLASE_TIPO[m.tipo]}`}>
                                                {ETIQUETA_TIPO[m.tipo]}
                                            </span>
                                        </td>
                                        <td className="font-mono text-xs" title={m.observaciones ?? undefined}>
                                            {m.referencia}
                                        </td>
                                        <td
                                            className={`text-right font-mono ${m.cantidad < 0 ? 'text-error' : 'text-success'}`}
                                        >
                                            {m.cantidad > 0 ? '+' : ''}
                                            {cantidad(m.cantidad)}
                                        </td>
                                        <td className="text-right font-mono font-medium">{cantidad(m.saldo_nuevo)}</td>
                                        <td className="text-sm">{m.usuario}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>

                    {filas.length > 0 && (
                        <div className="border-base-300 bg-base-200 flex flex-wrap items-center justify-between gap-2 border-t px-4 py-2 text-sm">
                            <span>{filas.length} movimiento(s)</span>
                            <span className="font-mono">
                                <span className="text-success">+{cantidad(entradas)}</span>
                                {' / '}
                                <span className="text-error">{cantidad(salidas)}</span>
                            </span>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
