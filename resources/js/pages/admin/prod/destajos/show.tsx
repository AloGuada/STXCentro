import { AgregarPagoExtra } from '@/components/prod/agregar-pago-extra';
import { CapturarProduccion } from '@/components/prod/capturar-produccion';
import { GrupoDestajoCard, type PagoExtraPreview, type RegistroPreview } from '@/components/prod/grupo-destajo-card';
import { LiquidacionCard, type LiquidacionFull } from '@/components/prod/liquidacion-card';
import { PendientesLiquidar } from '@/components/prod/pendientes-liquidar';
import { Button } from '@/components/ui/button';
import { FormattedDate } from '@/components/ui/formatted-date';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type {
    Concepto,
    Obra,
    ProdDestajo,
    ProdGrupoTrabajo,
    ProdPendienteLiquidar,
    ProdPiezaSinPrecio,
    ProdTipoPagoExtra,
} from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangleIcon, CalendarCheckIcon, FileDownIcon, LockIcon, Trash2Icon } from 'lucide-react';

type DestajoFull = ProdDestajo & { liquidaciones: LiquidacionFull[] };

type Props = {
    destajo: DestajoFull;
    registrosPreview?: Record<string, RegistroPreview[]>;
    pagosExtraPreview?: Record<string, PagoExtraPreview[]>;
    piezasSinPrecio?: ProdPiezaSinPrecio[];
    gruposTrabajo?: ProdGrupoTrabajo[];
    conceptos?: (Concepto & { obra?: Obra })[];
    tipos?: ProdTipoPagoExtra[];
    pendientes?: ProdPendienteLiquidar[];
};

export default function DestajosShow({
    destajo,
    registrosPreview = {},
    pagosExtraPreview = {},
    piezasSinPrecio = [],
    gruposTrabajo = [],
    conceptos = [],
    tipos = [],
    pendientes = [],
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/destajos' },
        { title: 'Destajos', href: '/admin/prod/destajos' },
        { title: `Semana ${destajo.semana}`, href: `/admin/prod/destajos/${destajo.id}` },
    ];

    const cerrar = () => {
        if (confirm('¿Cerrar este destajo? Se generarán las liquidaciones y ya no podrá editarse.')) {
            router.post(`/admin/prod/destajos/${destajo.id}/cerrar`, {}, { preserveScroll: true });
        }
    };

    const eliminar = () => {
        if (confirm('¿Eliminar este destajo?')) {
            router.delete(`/admin/prod/destajos/${destajo.id}`);
        }
    };

    const grupoNombre = (grupoId: string): string =>
        registrosPreview[grupoId]?.[0]?.grupo_trabajo?.descripcion ??
        pagosExtraPreview[grupoId]?.[0]?.grupo_trabajo?.descripcion ??
        gruposTrabajo.find((g) => String(g.id) === grupoId)?.descripcion ??
        `Grupo ${grupoId}`;

    const grupoIds = Array.from(
        new Set([...Object.keys(registrosPreview), ...Object.keys(pagosExtraPreview)]),
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Destajo Semana ${destajo.semana}`} />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-semibold">
                            Destajo · Semana {destajo.semana}
                            <span className={`badge ${destajo.cerrado ? 'badge-neutral' : 'badge-success'}`}>
                                {destajo.cerrado ? 'Cerrado' : 'Abierto'}
                            </span>
                        </h1>
                        <p className="text-base-content/60 text-sm">
                            Año {destajo.anio} · <FormattedDate value={destajo.fecha_inicio} /> — <FormattedDate value={destajo.fecha_fin} />
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Link href={`/admin/prod/destajos/${destajo.id}/asistencia`} className="btn btn-outline">
                            <CalendarCheckIcon className="size-4" /> Asistencia
                        </Link>
                        <a
                            href={`/admin/prod/destajos/${destajo.id}/orden-pago`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="btn btn-outline"
                        >
                            <FileDownIcon className="size-4" /> Orden de pago
                        </a>
                        {!destajo.cerrado && (
                            <>
                                <Button variant="outline" className="text-error" onClick={eliminar}>
                                    <Trash2Icon className="size-4" /> Eliminar
                                </Button>
                                <Button onClick={cerrar}>
                                    <LockIcon className="size-4" /> Cerrar destajo
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                {!destajo.cerrado && (
                    <div className="space-y-6">
                        {piezasSinPrecio.length > 0 && (
                            <div className="alert alert-warning">
                                <AlertTriangleIcon className="size-5" />
                                <div>
                                    <div className="font-semibold">
                                        {piezasSinPrecio.length} pieza(s) con producción sin precio asignado
                                    </div>
                                    <div className="text-sm">
                                        Se pagarían en $0 al cerrar:{' '}
                                        {piezasSinPrecio.map((p) => p.marca).join(', ')}. Asígnales un grupo de precio.
                                    </div>
                                </div>
                            </div>
                        )}

                        <PendientesLiquidar
                            destajo={destajo}
                            pendientes={pendientes}
                            gruposTrabajo={gruposTrabajo}
                        />

                        <CapturarProduccion destajo={destajo} conceptos={conceptos} gruposTrabajo={gruposTrabajo} />
                        <AgregarPagoExtra destajo={destajo} tipos={tipos} gruposTrabajo={gruposTrabajo} />

                        <div>
                            <h2 className="mb-3 text-lg font-semibold">Resumen por grupo</h2>
                            {grupoIds.length === 0 ? (
                                <div className="rounded-box border border-dashed border-base-300 p-8 text-center">
                                    <p className="text-base-content/60">
                                        Aún no hay producción ni pagos extra en este destajo.
                                    </p>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {grupoIds.map((grupoId) => (
                                        <GrupoDestajoCard
                                            key={grupoId}
                                            destajoId={destajo.id}
                                            grupoNombre={grupoNombre(grupoId)}
                                            registros={registrosPreview[grupoId] ?? []}
                                            pagosExtra={pagosExtraPreview[grupoId] ?? []}
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {destajo.cerrado && (
                    <div className="space-y-4">
                        {destajo.liquidaciones.length === 0 ? (
                            <div className="rounded-box border border-dashed border-base-300 p-8 text-center">
                                <p className="text-base-content/60">
                                    No se generaron liquidaciones (sin producción ni pagos extra en el periodo).
                                </p>
                            </div>
                        ) : (
                            destajo.liquidaciones.map((liq) => <LiquidacionCard key={liq.id} liquidacion={liq} />)
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
