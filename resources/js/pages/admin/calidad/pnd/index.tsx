/**
 * Pruebas no destructivas — avance del contrato e informes del laboratorio.
 *
 * PND corre en paralelo a la captura del inspector y **no se suma con ella**:
 * aquí se cuentan juntas soldadas evaluadas por un laboratorio externo, allá
 * piezas revisadas a la vista. Denominadores distintos, bloques separados.
 *
 * La pantalla es de una obra a la vez porque el plan comprometido es por obra:
 * un avance de todas juntas sería una suma que nadie firmó.
 */

import { DataTable, type Column } from '@/components/data-table';
import { PlanDialog } from '@/components/qal/pnd/plan-dialog';
import { TableroAvance } from '@/components/qal/pnd/tablero-avance';
import { Button, ButtonLink } from '@/components/ui/button';
import { FormattedDate } from '@/components/ui/formatted-date';
import { Select, SelectItem } from '@/components/ui/select';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, QalObra, QalPndAvance, QalPndReporte } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import { FileTextIcon, SlidersHorizontalIcon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Calidad', href: '/admin/calidad/catalogos' },
    { title: 'PND', href: '/admin/calidad/pnd' },
];

const METODOS = ['UT', 'MT', 'PT', 'RT', 'VT'];

type Props = {
    obras: QalObra[];
    obraId: number | null;
    nota: string | null;
    plan: QalPndAvance[];
    reportes: PaginatedData<QalPndReporte>;
    filtros: { metodo?: string; anio?: string; search?: string };
};

export default function PndIndex({ obras, obraId, nota, plan, reportes, filtros }: Props) {
    const { can } = useCan();
    const [planAbierto, setPlanAbierto] = useState(false);

    const obra = obras.find((o) => o.id === obraId) ?? null;

    /** Los filtros viajan en la URL para que un avance se pueda mandar por correo. */
    const filtrar = (cambios: Record<string, string | undefined>) => {
        const actuales = Object.fromEntries(new URLSearchParams(window.location.search));
        router.get(
            window.location.pathname,
            { ...actuales, ...cambios, page: undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const columns: Column<QalPndReporte>[] = [
        {
            key: 'reporte_no',
            label: 'Reporte',
            render: (r) => <span className="font-mono font-medium">{r.reporte_no}</span>,
        },
        {
            key: 'metodo',
            label: 'Método',
            render: (r) => <span className="badge badge-sm badge-outline font-mono">{r.metodo}</span>,
        },
        {
            key: 'laboratorio',
            label: 'Laboratorio',
            render: (r) => r.laboratorio?.siglas ?? r.laboratorio?.nombre ?? '—',
        },
        {
            key: 'fecha_prueba',
            label: 'Prueba',
            render: (r) => (
                <span className="text-base-content/70 font-mono text-sm">
                    <FormattedDate value={r.fecha_prueba} />
                </span>
            ),
        },
        {
            key: 'semana',
            label: 'Semana',
            className: 'font-mono',
            render: (r) => `${r.anio}-S${String(r.semana).padStart(2, '0')}`,
        },
        {
            key: 'spots',
            label: 'Puntos',
            className: 'text-right font-mono',
            render: (r) => r.spots ?? 0,
        },
        {
            key: 'rechazados',
            label: 'Rechazados',
            className: 'text-right',
            render: (r) =>
                (r.rechazados ?? 0) > 0 ? (
                    <span className="badge badge-sm badge-error font-mono">{r.rechazados}</span>
                ) : (
                    <span className="text-base-content/40 font-mono">0</span>
                ),
        },
        {
            key: 'archivo_pdf',
            label: '',
            className: 'text-right',
            render: (r) =>
                r.archivo_pdf ? (
                    <a
                        href={`/storage/${r.archivo_pdf}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        onClick={(e) => e.stopPropagation()}
                        className="btn btn-ghost btn-xs"
                        title="Informe original del laboratorio"
                    >
                        <FileTextIcon className="size-4" /> PDF
                    </a>
                ) : null,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="PND" />

            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Pruebas no destructivas</h1>
                        <p className="text-base-content/60 text-sm">
                            Juntas soldadas evaluadas por un laboratorio externo. No se suman con las piezas que
                            inspecciona Calidad a la vista: son universos distintos.
                        </p>
                    </div>

                    {obraId && can('qal.pnd.crear') && (
                        <ButtonLink href={`/admin/calidad/pnd/create?obra=${obraId}`} variant="primary">
                            Capturar informe
                        </ButtonLink>
                    )}
                </div>

                <div className="flex flex-wrap items-end gap-3">
                    <div className="form-control">
                        <label className="label" htmlFor="obra">
                            <span className="label-text">Obra</span>
                        </label>
                        <Select
                            id="obra"
                            className="min-w-72"
                            value={obraId ? String(obraId) : ''}
                            onValueChange={(valor) => filtrar({ obra: valor })}
                            placeholder="Elegir obra"
                        >
                            {obras.map((o) => (
                                <SelectItem key={o.id} value={String(o.id)}>
                                    {o.no}
                                    {o.descripcion ? ` · ${o.descripcion}` : ''}
                                    {o.activa === false ? ' (inactiva)' : ''}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>

                    {obra && can('qal.obras.editar') && (
                        <Button variant="outline" onClick={() => setPlanAbierto(true)}>
                            <SlidersHorizontalIcon className="size-4" /> Plan comprometido
                        </Button>
                    )}
                </div>

                {!obra ? (
                    <div className="rounded-box border border-base-300 p-8 text-center">
                        <p className="text-base-content/60">
                            No hay obras dadas de alta en Calidad. El plan de PND se pacta sobre una obra, así que aquí
                            no hay nada que medir todavía.
                        </p>
                    </div>
                ) : (
                    <>
                        <TableroAvance plan={plan} />

                        {nota ? (
                            <div className="rounded-box border border-base-300 bg-base-200/40 p-3">
                                <span className="text-base-content/50 text-xs uppercase">De dónde sale el número</span>
                                <p className="text-sm">{nota}</p>
                            </div>
                        ) : (
                            <p className="text-base-content/50 text-xs">
                                Sin nota del contrato. Sin ella, dentro de seis meses nadie sabrá si eran 60 u 80.
                            </p>
                        )}

                        <div className="flex flex-wrap items-end gap-3">
                            <div className="form-control">
                                <label className="label" htmlFor="metodo">
                                    <span className="label-text">Método</span>
                                </label>
                                <Select
                                    id="metodo"
                                    className="w-40"
                                    value={filtros.metodo ?? ''}
                                    onValueChange={(valor) => filtrar({ metodo: valor || undefined })}
                                >
                                    <SelectItem value="">Todos</SelectItem>
                                    {METODOS.map((metodo) => (
                                        <SelectItem key={metodo} value={metodo}>
                                            {metodo}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </div>

                            <div className="form-control">
                                <label className="label" htmlFor="anio">
                                    <span className="label-text">Año</span>
                                </label>
                                <input
                                    id="anio"
                                    type="number"
                                    className="input input-bordered w-28 font-mono"
                                    placeholder="Todos"
                                    defaultValue={filtros.anio ?? ''}
                                    onBlur={(e) => filtrar({ anio: e.target.value || undefined })}
                                />
                            </div>
                        </div>

                        <DataTable
                            columns={columns}
                            data={reportes}
                            searchable
                            searchPlaceholder="Buscar por número de reporte..."
                            searchValue={filtros.search}
                            getRowHref={(r) => `/admin/calidad/pnd/${r.id}/edit`}
                            emptyMessage="Esta obra no tiene informes de PND capturados"
                        />
                    </>
                )}
            </div>

            {obra && (
                <PlanDialog
                    obraId={obra.id}
                    plan={plan}
                    nota={nota}
                    abierto={planAbierto}
                    onCerrar={() => setPlanAbierto(false)}
                />
            )}
        </AppLayout>
    );
}
