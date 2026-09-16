import { Input } from '@/components/ui/input';
import { SearchSelect } from '@/components/ui/search-select';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { etiquetaDeAlmacen } from '@/lib/alm/almacenes';
import type { BreadcrumbItem } from '@/types';
import type { AlmAlmacenOpcion, AlmMovimientoTipo, AlmOpcion, AlmProductoOpcion, PaginatedData } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { UndoDotIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Kardex', href: '/admin/almacen/kardex' },
];

const CLASE_TIPO: Record<AlmMovimientoTipo, string> = {
    entrada: 'badge-success',
    salida: 'badge-error',
    transferencia_entrada: 'badge-info',
    transferencia_salida: 'badge-info badge-outline',
    ajuste: 'badge-warning',
    // Cambia de dueño, no de bodega: la pareja de asientos suma cero, así que no
    // se pinta ni como entrada ni como salida.
    reasignacion: 'badge-neutral',
};

const cantidad = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

type MovimientoFila = {
    id: number;
    fecha: string | null;
    almacen: string | null;
    codigo: string | null;
    descripcion: string | null;
    unidad: string | null;
    tipo: AlmMovimientoTipo;
    tipo_etiqueta: string;
    /** De quién era el material. Vacío = libre, sin dueño. */
    obra: string | null;
    cantidad: number;
    /** Se lee del asiento, no se recalcula: es el punto entero del ledger. */
    saldo_despues: number;
    costo_unitario: number | null;
    referencia: string | null;
    es_reverso: boolean;
    observaciones: string | null;
    usuario: string | null;
};

type Props = {
    movimientos: PaginatedData<MovimientoFila>;
    filters: {
        almacen_id?: string;
        articulo_id?: string;
        obra_id?: string;
        tipo?: string;
        desde?: string;
        hasta?: string;
        referencia?: string;
    };
    /** Sobre el filtro completo, no sobre la página. */
    totales: { movimientos: number; entradas: number; salidas: number };
    almacenes: AlmAlmacenOpcion[];
    productos: AlmProductoOpcion[];
    obras: { id: number; no: string }[];
    tipos: AlmOpcion[];
};

/**
 * El libro de movimientos: cada renglón dice qué pasó y con qué saldo quedó el
 * artículo en ese almacén. Es sólo lectura — corregir un error es capturar el
 * movimiento contrario, no borrar el renglón.
 */
export default function KardexIndex({ movimientos, filters, totales, almacenes, productos, obras, tipos }: Props) {
    const filtrar = (cambio: Record<string, string | undefined>) =>
        router.get('/admin/almacen/kardex', { ...filters, ...cambio, page: undefined }, { preserveState: true });

    // El saldo corriente sólo se puede leer de arriba abajo cuando la columna
    // habla de un solo artículo en un solo almacén; si no, cada renglón trae el
    // saldo de otra cosa y la columna parece contradecirse.
    const saldoLegible = Boolean(filters.almacen_id) && Boolean(filters.articulo_id);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Kardex" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Kardex</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Todo lo que entró y salió, con el saldo que dejó cada movimiento. El saldo sólo se lee de
                        corrido con un almacén y un artículo elegidos.
                    </p>
                </div>

                <div className="mb-4 flex flex-wrap items-end gap-3">
                    <div className="w-52">
                        <label className="label label-text text-xs">Almacén</label>
                        <SearchSelect
                            value={filters.almacen_id ?? ''}
                            onValueChange={(v) => filtrar({ almacen_id: v || undefined })}
                            placeholder="Todos"
                            options={[
                                { value: '', label: 'Todos' },
                                ...almacenes.map((a) => ({ value: String(a.id), label: `${etiquetaDeAlmacen(a)} — ${a.nombre}` })),
                            ]}
                        />
                    </div>

                    <div className="w-64">
                        <label className="label label-text text-xs">Artículo</label>
                        <SearchSelect
                            value={filters.articulo_id ?? ''}
                            onValueChange={(v) => filtrar({ articulo_id: v || undefined })}
                            placeholder="Todos"
                            maxOptions={30}
                            options={[
                                { value: '', label: 'Todos' },
                                ...productos.map((p) => ({ value: String(p.id), label: `${p.codigo} — ${p.descripcion}` })),
                            ]}
                        />
                    </div>

                    <div className="w-44">
                        <label className="label label-text text-xs">Obra</label>
                        <SearchSelect
                            value={filters.obra_id ?? ''}
                            onValueChange={(v) => filtrar({ obra_id: v || undefined })}
                            placeholder="Todas"
                            options={[
                                { value: '', label: 'Todas' },
                                { value: 'libre', label: 'Sin asignar' },
                                ...obras.map((o) => ({ value: String(o.id), label: o.no })),
                            ]}
                        />
                    </div>

                    <div className="w-44">
                        <label className="label label-text text-xs">Tipo</label>
                        <Select
                            value={filters.tipo ?? ''}
                            onValueChange={(v) => filtrar({ tipo: v || undefined })}
                            placeholder="Todos"
                        >
                            {tipos.map((t) => (
                                <SelectItem key={t.value} value={t.value}>
                                    {t.label}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>

                    <div className="w-40">
                        <label className="label label-text text-xs">Desde</label>
                        <Input
                            type="date"
                            defaultValue={filters.desde ?? ''}
                            onChange={(e) => filtrar({ desde: e.target.value || undefined })}
                        />
                    </div>

                    <div className="w-40">
                        <label className="label label-text text-xs">Hasta</label>
                        <Input
                            type="date"
                            defaultValue={filters.hasta ?? ''}
                            onChange={(e) => filtrar({ hasta: e.target.value || undefined })}
                        />
                    </div>
                </div>

                {!saldoLegible && movimientos.data.length > 0 && (
                    <div className="alert alert-info mb-4">
                        <span>
                            Elige un almacén y un artículo para poder leer la columna de saldo de corrido: mezclados,
                            cada renglón trae el saldo de otra cosa.
                        </span>
                    </div>
                )}

                <div className="rounded-box border-base-300 overflow-x-auto border">
                    <table className="table table-sm">
                        <thead className="bg-base-200">
                            <tr>
                                <th>Fecha</th>
                                <th>Almacén</th>
                                <th>Artículo</th>
                                <th>Tipo</th>
                                <th>Obra</th>
                                <th>Referencia</th>
                                <th className="text-right">Cantidad</th>
                                <th className="text-right">Saldo</th>
                                <th>Capturó</th>
                            </tr>
                        </thead>
                        <tbody>
                            {movimientos.data.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="text-base-content/50 py-6 text-center">
                                        No hay movimientos con esos filtros.
                                    </td>
                                </tr>
                            ) : (
                                movimientos.data.map((m) => (
                                    <tr key={m.id} className="hover">
                                        <td className="font-mono text-xs whitespace-nowrap">{m.fecha}</td>
                                        <td>
                                            <span className="badge badge-sm badge-ghost font-mono">{m.almacen}</span>
                                        </td>
                                        <td>
                                            <span className="font-mono text-xs">{m.codigo}</span>
                                            <span className="text-base-content/60 block text-xs">{m.descripcion}</span>
                                        </td>
                                        <td>
                                            <span className={`badge badge-sm ${CLASE_TIPO[m.tipo]}`}>
                                                {m.tipo_etiqueta}
                                            </span>
                                            {m.es_reverso && (
                                                <UndoDotIcon
                                                    className="text-base-content/50 ml-1 inline size-3"
                                                    aria-label="Reverso de un documento cancelado"
                                                />
                                            )}
                                        </td>
                                        <td className="text-xs">
                                            {m.obra ?? <span className="text-base-content/30">libre</span>}
                                        </td>
                                        <td className="font-mono text-xs" title={m.observaciones ?? undefined}>
                                            {m.referencia ?? <span className="text-base-content/40">—</span>}
                                        </td>
                                        <td
                                            className={`text-right font-mono ${m.cantidad < 0 ? 'text-error' : 'text-success'}`}
                                        >
                                            {m.cantidad > 0 ? '+' : ''}
                                            {cantidad(m.cantidad)}
                                        </td>
                                        <td
                                            className={`text-right font-mono ${
                                                saldoLegible ? 'font-medium' : 'text-base-content/50'
                                            }`}
                                        >
                                            {cantidad(m.saldo_despues)}
                                        </td>
                                        <td className="text-sm">{m.usuario}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>

                    {totales.movimientos > 0 && (
                        <div className="border-base-300 bg-base-200 flex flex-wrap items-center justify-between gap-2 border-t px-4 py-2 text-sm">
                            <span>{totales.movimientos} movimiento(s)</span>
                            <span className="font-mono">
                                <span className="text-success">+{cantidad(totales.entradas)}</span>
                                {' / '}
                                <span className="text-error">{cantidad(totales.salidas)}</span>
                            </span>
                        </div>
                    )}
                </div>

                {movimientos.links.length > 3 && (
                    <div className="join mt-4 flex justify-center">
                        {movimientos.links.map((link, i) =>
                            link.url === null ? (
                                <button key={i} className="join-item btn btn-sm btn-disabled">
                                    <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                </button>
                            ) : (
                                <Link
                                    key={i}
                                    href={link.url}
                                    className={`join-item btn btn-sm ${link.active ? 'btn-active' : ''}`}
                                    preserveState
                                >
                                    <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                </Link>
                            ),
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
