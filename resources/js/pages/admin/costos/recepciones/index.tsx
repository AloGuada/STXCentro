import { formatMoney } from '@/components/costos/monto';
import { EditarRecepcionModal } from '@/components/costos/editar-recepcion-modal';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FormattedDate } from '@/components/ui/formatted-date';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosRecepcionRow, PaginatedData } from '@/types/models';
import { useCan } from '@/hooks/use-can';
import { Head, Link, router } from '@inertiajs/react';
import { DownloadIcon, FileTextIcon, PackageCheckIcon, PencilIcon, ReceiptIcon, SearchIcon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/solicitudes-pago' },
    { title: 'Recepciones', href: '/admin/costos/recepciones' },
];

type Props = {
    recepciones: PaginatedData<CostosRecepcionRow>;
    filters: { search?: string; tipo?: string };
    /** Recibido bajo los filtros activos (todas las páginas), por moneda. */
    totales_recibidos: Record<string, number>;
    /** Candidatos para "recibió" al corregir una recepción. */
    usuarios: { id: string; name: string }[];
};


/** Cuántas obras se alcanzan a leer sin ensanchar la columna; el resto va al tooltip. */
const OBRAS_VISIBLES = 2;

/**
 * Obras a las que carga la recepción. Una OC puede repartirse entre varias, y
 * saber cuáles es justo el dato que se concilia contra presupuesto, así que se
 * enumeran en vez de resumirlas en un "Varios presupuestos".
 */
function Obras({ nombres }: { nombres: string[] }) {
    if (nombres.length === 0) {
        return <span className="text-base-content/40">-</span>;
    }

    const visibles = nombres.slice(0, OBRAS_VISIBLES);
    const ocultas = nombres.length - visibles.length;

    return (
        <div className="flex flex-col gap-0.5" title={nombres.join(' · ')}>
            {visibles.map((nombre) => (
                <span key={nombre} className="truncate">
                    {nombre}
                </span>
            ))}
            {ocultas > 0 && <span className="text-xs text-base-content/60">+{ocultas} más</span>}
        </div>
    );
}

/** `YYYY-MM-DD` en hora local; `toISOString()` correria el dia en zonas UTC-. */
const aInput = (fecha: Date): string =>
    `${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, '0')}-${String(fecha.getDate()).padStart(2, '0')}`;

export default function RecepcionesIndex({ recepciones, filters, totales_recibidos, usuarios }: Props) {
    const { can } = useCan();
    const puedeEditar = can('costos.entregas.editar');
    const [editando, setEditando] = useState<CostosRecepcionRow | null>(null);
    const [search, setSearch] = useState(filters.search ?? '');
    const [tipo, setTipo] = useState(filters.tipo ?? '');

    const hoy = new Date();
    const [reporteAbierto, setReporteAbierto] = useState(false);
    const [fechaInicio, setFechaInicio] = useState(aInput(new Date(hoy.getFullYear(), hoy.getMonth(), 1)));
    const [fechaFin, setFechaFin] = useState(aInput(hoy));

    const rangoInvalido = fechaInicio === '' || fechaFin === '' || fechaFin < fechaInicio;

    const hayFiltros = Boolean(filters.search || filters.tipo);
    const totales = Object.entries(totales_recibidos);

    /** Lo recibido en la página visible; las canceladas no suman. */
    const totalesPagina = Object.entries(
        recepciones.data.reduce<Record<string, number>>((acc, r) => {
            if (r.cancelada) return acc;
            const moneda = (r.oc?.moneda ?? 'mxn').toLowerCase();
            acc[moneda] = (acc[moneda] ?? 0) + Number(r.total ?? 0);
            return acc;
        }, {}),
    );

    const generarReporte = () => {
        const params = new URLSearchParams({ fecha_inicio: fechaInicio, fecha_fin: fechaFin });
        if (search) params.set('search', search);
        if (tipo) params.set('tipo', tipo);

        window.location.href = `/admin/costos/recepciones/exportar?${params.toString()}`;
        setReporteAbierto(false);
    };

    const aplicarFiltros = (next: { search?: string; tipo?: string }) => {
        router.get(
            '/admin/costos/recepciones',
            { search: next.search ?? search, tipo: next.tipo ?? tipo },
            { preserveState: true, replace: true },
        );
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        aplicarFiltros({});
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Recepciones" />

            <div className="p-6">
                <div className="mb-6 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-semibold">
                            <PackageCheckIcon className="h-6 w-6" /> Recepciones
                        </h1>
                        <p className="mt-1 text-sm text-base-content/60">
                            Recepciones de almacén ligadas a su orden de compra y solicitud de pago.
                        </p>
                    </div>
                    <button type="button" className="btn btn-outline btn-sm gap-1 self-start" onClick={() => setReporteAbierto(true)}>
                        <DownloadIcon className="size-4" /> Reporte
                    </button>
                </div>

                <Dialog open={reporteAbierto} onOpenChange={setReporteAbierto}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Reporte de recepciones</DialogTitle>
                            <p className="mt-1 text-sm text-base-content/60">
                                Se exporta a Excel lo recibido entre las dos fechas, contadas por fecha de
                                recepción: la del documento, no la de entrega.
                                {(search || tipo) && ' Se respetan los filtros activos de la pantalla.'}
                            </p>
                        </DialogHeader>

                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <label className="flex flex-col gap-1 text-sm">
                                <span className="text-base-content/60">Desde</span>
                                <input
                                    type="date"
                                    className="input input-bordered input-sm"
                                    value={fechaInicio}
                                    onChange={(e) => setFechaInicio(e.target.value)}
                                />
                            </label>
                            <label className="flex flex-col gap-1 text-sm">
                                <span className="text-base-content/60">Hasta</span>
                                <input
                                    type="date"
                                    className="input input-bordered input-sm"
                                    value={fechaFin}
                                    min={fechaInicio}
                                    onChange={(e) => setFechaFin(e.target.value)}
                                />
                            </label>
                        </div>

                        {rangoInvalido && (
                            <p className="mt-2 text-sm text-error">La fecha final no puede ser anterior a la inicial.</p>
                        )}

                        <DialogFooter>
                            <DialogClose className="btn-ghost btn-sm">Cancelar</DialogClose>
                            <button
                                type="button"
                                className="btn btn-primary btn-sm gap-1"
                                disabled={rangoInvalido}
                                onClick={generarReporte}
                            >
                                <DownloadIcon className="size-4" /> Generar Excel
                            </button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <div className="mb-4 flex flex-wrap items-end gap-3">
                    <form onSubmit={handleSearch} className="flex items-end gap-2">
                        <label className="flex flex-col gap-1 text-sm">
                            <span className="text-base-content/60">Buscar</span>
                            <input
                                type="text"
                                className="input input-bordered input-sm w-64"
                                placeholder="Folio REC, OC o proveedor…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </label>
                        <button type="submit" className="btn btn-sm btn-primary gap-1">
                            <SearchIcon className="h-4 w-4" /> Buscar
                        </button>
                    </form>

                    <label className="flex flex-col gap-1 text-sm">
                        <span className="text-base-content/60">Tipo</span>
                        <select
                            className="select select-bordered select-sm"
                            value={tipo}
                            onChange={(e) => {
                                setTipo(e.target.value);
                                aplicarFiltros({ tipo: e.target.value });
                            }}
                        >
                            <option value="">Todos</option>
                            <option value="parcial">Parcial</option>
                            <option value="completa">Completa</option>
                        </select>
                    </label>
                </div>

                <div className="mb-4 flex flex-wrap items-baseline gap-x-4 gap-y-1 rounded-box border border-base-300 px-4 py-2">
                    <span className="text-sm text-base-content/60">
                        Total recibido{hayFiltros ? ' (con los filtros activos)' : ''}
                    </span>
                    {totales.length === 0 ? (
                        <span className="text-lg font-semibold">{formatMoney(0)}</span>
                    ) : (
                        totales.map(([moneda, total]) => (
                            <span key={moneda} className="text-lg font-semibold">
                                {formatMoney(total, moneda)}
                            </span>
                        ))
                    )}
                    <span className="text-xs text-base-content/50">
                        Sin IVA · no incluye recepciones canceladas
                    </span>
                </div>

                <div className="overflow-x-auto">
                    <table className="table">
                        <thead>
                            <tr>
                                <th>Folio</th>
                                {/* La de recepción la sella el sistema al elaborar el
                                    documento; la de entrega la captura quien recibe. */}
                                <th>Fecha de recepción</th>
                                <th>Fecha de Entrega</th>
                                <th>OC</th>
                                <th>Solicitud de Pago</th>
                                <th>Proveedor</th>
                                <th>Obra</th>
                                <th>Factura</th>
                                <th>Recibió</th>
                                <th className="text-right">Total</th>
                                <th>Tipo</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {recepciones.data.length === 0 ? (
                                <tr>
                                    <td colSpan={12} className="py-12 text-center text-base-content/60">
                                        No hay recepciones.
                                    </td>
                                </tr>
                            ) : (
                                recepciones.data.map((r) => (
                                    <tr key={r.id}>
                                        <td className="font-medium">{r.folio ?? '-'}</td>
                                        <td className="whitespace-nowrap">
                                            <FormattedDate value={r.fecha_recepcion} />
                                        </td>
                                        <td className="whitespace-nowrap">
                                            <FormattedDate value={r.fecha_entrega} />
                                        </td>
                                        <td>
                                            {r.oc ? (
                                                <Link href={r.oc.url} className="link link-primary flex items-center gap-1">
                                                    <FileTextIcon className="h-3 w-3" /> {r.oc.folio}
                                                </Link>
                                            ) : (
                                                '-'
                                            )}
                                        </td>
                                        <td>
                                            {r.solicitudes_pago.length === 0 ? (
                                                <span className="text-base-content/40">-</span>
                                            ) : (
                                                <div className="flex flex-col gap-1">
                                                    {r.solicitudes_pago.map((sp) => (
                                                        <Link key={sp.id} href={sp.url} className="link link-primary flex items-center gap-1">
                                                            <ReceiptIcon className="h-3 w-3" /> {sp.folio}
                                                        </Link>
                                                    ))}
                                                </div>
                                            )}
                                        </td>
                                        <td>{r.proveedor ?? '-'}</td>
                                        <td className="max-w-xs">
                                            <Obras nombres={r.obras} />
                                        </td>
                                        <td>{r.factura?.folio ?? '-'}</td>
                                        <td>{r.recibido_por ?? '-'}</td>
                                        <td
                                            className={`whitespace-nowrap text-right font-medium ${r.cancelada ? 'text-base-content/40 line-through' : ''}`}
                                        >
                                            {formatMoney(r.total, r.oc?.moneda ?? 'mxn')}
                                        </td>
                                        <td>
                                            <span
                                                className={`badge badge-sm ${r.tipo === 'completa' ? 'badge-success' : 'badge-warning'}`}
                                            >
                                                {r.tipo === 'completa' ? 'Completa' : 'Parcial'}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap">
                                            <a href={r.pdf_url} target="_blank" rel="noopener noreferrer" className="btn btn-ghost btn-xs">
                                                PDF
                                            </a>
                                            {puedeEditar && r.puede_editar && (
                                                <button
                                                    type="button"
                                                    className="btn btn-ghost btn-xs"
                                                    onClick={() => setEditando(r)}
                                                >
                                                    <PencilIcon className="size-3.5" />
                                                    Editar
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                        {recepciones.data.length > 0 && (
                            <tfoot>
                                <tr>
                                    <td colSpan={9} className="text-right text-base-content/60">
                                        Total en esta página
                                    </td>
                                    <td className="whitespace-nowrap text-right font-semibold">
                                        {totalesPagina.length === 0
                                            ? formatMoney(0)
                                            : totalesPagina.map(([moneda, total]) => (
                                                  <div key={moneda}>{formatMoney(total, moneda)}</div>
                                              ))}
                                    </td>
                                    <td colSpan={2}></td>
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>

                {recepciones.last_page > 1 && (
                    <div className="mt-4 flex flex-col items-start gap-2 text-sm text-base-content/70 sm:flex-row sm:items-center sm:justify-between">
                        <span>
                            Página {recepciones.current_page} de {recepciones.last_page} · {recepciones.total} recepciones
                        </span>
                        <div className="flex gap-2">
                            {recepciones.prev_page_url && (
                                <Link href={recepciones.prev_page_url} className="btn btn-sm btn-outline" preserveState>
                                    Anterior
                                </Link>
                            )}
                            {recepciones.next_page_url && (
                                <Link href={recepciones.next_page_url} className="btn btn-sm btn-outline" preserveState>
                                    Siguiente
                                </Link>
                            )}
                        </div>
                    </div>
                )}

                <EditarRecepcionModal
                    recepcion={editando}
                    usuarios={usuarios}
                    onClose={() => setEditando(null)}
                />
            </div>
        </AppLayout>
    );
}
