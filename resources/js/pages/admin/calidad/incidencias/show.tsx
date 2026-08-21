/**
 * Incidencias en obra — la obra: se captura la semana y se mira su año.
 *
 * Va aparte de la portada porque responde otra pregunta. La portada compara
 * obras entre sí —«¿cuál va peor?»— y ésta es la de trabajo: «¿qué pasó esta
 * semana aquí?». Es la que se abre veinte veces por semana.
 *
 * Dos cosas que no son adorno y vienen del formato que sustituye:
 *
 *  - **«Sin base» no es «0 %».** Una semana con defectos y sin piezas montadas
 *    capturadas no tiene porcentaje; escribir cero ahí se lee como que salió
 *    limpia.
 *  - **Borrar el avance no borra las incidencias.** Son hechos distintos, y el
 *    diálogo lo dice antes de que alguien lo descubra por las malas.
 */

import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeftIcon, EyeIcon, Trash2Icon } from 'lucide-react';
import { DeleteDialog } from '@/components/delete-dialog';
import { Etiqueta, FilaKpis, Panel, Rejilla2, TarjetaGrafica, TarjetaKpi } from '@/components/qal/dashboard/ui';
import { BarrasRanking } from '@/components/qal/graficas';
import { CapturaSemana } from '@/components/qal/incidencias/captura-semana';
import { num, pct } from '@/components/qal/paleta';
import { Select, SelectItem } from '@/components/ui/select';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { QalIncidenciaSemana, QalObraIncidencia, QalObraMontaje, QalOpcion } from '@/types/models';

type Props = {
    obra: { id: number; no: string; descripcion: string | null; pz_total: number | null };
    anio: number;
    anios: number[];
    semana: number;
    semanaActual: number;
    semanas: { numero: number; rango: string }[];
    montajeSemana: QalObraMontaje | null;
    incidencias: QalObraIncidencia[];
    historial: QalIncidenciaSemana[];
    areas: QalOpcion[];
    departamentos: QalOpcion[];
    graficas: {
        porSemana: { semana: number; pz: number }[];
        porDepartamento: { clave: string; etiqueta: string; pz: number }[];
    };
};

export default function IncidenciasShow({
    obra,
    anio,
    anios,
    semana,
    semanaActual,
    semanas,
    montajeSemana,
    incidencias,
    historial,
    areas,
    departamentos,
    graficas,
}: Props) {
    const { can } = useCan();
    const puedeCapturar = can('qal.incidencias.capturar');
    const puedeEliminar = can('qal.incidencias.eliminar');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Calidad', href: '/admin/calidad/catalogos' },
        { title: 'Incidencias en obra', href: '/admin/calidad/incidencias' },
        { title: obra.no, href: '#' },
    ];

    const etiquetaDe = (opciones: QalOpcion[], valor: string) =>
        opciones.find((o) => o.valor === valor)?.etiqueta ?? valor;

    const ir = (cambios: Record<string, string>) =>
        router.get(
            window.location.pathname,
            { anio: String(anio), semana: String(semana), ...cambios },
            { preserveScroll: true, replace: true },
        );

    const pzDefecto = incidencias.reduce((a, i) => a + i.pz_defecto, 0);
    const pzMontadas = historial.reduce((a, f) => a + (f.pz_montadas ?? 0), 0);
    const abiertas = incidencias.filter((i) => i.abierta).length;
    const tasa = pzMontadas > 0 ? (pzDefecto * 100) / pzMontadas : null;

    const deLaSemana = incidencias.filter((i) => i.semana === semana);
    const rango = semanas.find((s) => s.numero === semana)?.rango ?? '';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Incidencias — ${obra.no}`} />

            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <Link
                            href="/admin/calidad/incidencias"
                            className="text-base-content/60 hover:text-base-content mb-1 inline-flex items-center gap-1 text-xs"
                        >
                            <ArrowLeftIcon className="size-3.5" /> Todas las obras
                        </Link>
                        <h1 className="text-2xl font-semibold">{obra.no}</h1>
                        {obra.descripcion && <p className="text-base-content/60 text-sm">{obra.descripcion}</p>}
                    </div>

                    <div className="flex flex-wrap items-end gap-2">
                        <label className="text-xs">
                            <span className="text-base-content/60 mb-1 block">Año</span>
                            <Select
                                value={String(anio)}
                                onValueChange={(valor) => ir({ anio: valor, semana: '' })}
                                className="select-sm w-28"
                            >
                                {anios.map((a) => (
                                    <SelectItem key={a} value={String(a)}>
                                        {a}
                                    </SelectItem>
                                ))}
                            </Select>
                        </label>
                        <label className="text-xs">
                            <span className="text-base-content/60 mb-1 block">Semana</span>
                            <Select
                                value={String(semana)}
                                onValueChange={(valor) => ir({ semana: valor })}
                                className="select-sm w-64"
                            >
                                {semanas.map((s) => (
                                    <SelectItem key={s.numero} value={String(s.numero)}>
                                        Semana {s.numero} · {s.rango}
                                        {s.numero === semanaActual ? ' · esta semana' : ''}
                                    </SelectItem>
                                ))}
                            </Select>
                        </label>
                    </div>
                </div>

                <FilaKpis>
                    <TarjetaKpi kpi={{ etiqueta: 'Incidencias', valor: num(incidencias.length), nota: `en ${anio}` }} />
                    <TarjetaKpi
                        kpi={{
                            etiqueta: 'Piezas con defecto',
                            valor: num(pzDefecto),
                            nota: `${pct(tasa, 2)} de lo montado`,
                            tono: pzDefecto > 0 ? 'malo' : 'bueno',
                        }}
                    />
                    <TarjetaKpi
                        kpi={{
                            etiqueta: 'Piezas montadas',
                            valor: num(pzMontadas),
                            nota:
                                obra.pz_total !== null
                                    ? `${pct((pzMontadas * 100) / obra.pz_total, 0)} de ${num(obra.pz_total)} del proyecto`
                                    : 'sin piezas totales en la ficha de la obra',
                        }}
                    />
                    <TarjetaKpi
                        kpi={{
                            etiqueta: 'Sin cerrar',
                            valor: num(abiertas),
                            nota: abiertas > 0 ? 'pendientes' : 'todo cerrado',
                            tono: abiertas > 0 ? 'pendiente' : 'bueno',
                        }}
                    />
                </FilaKpis>

                <CapturaSemana
                    obraId={obra.id}
                    anio={anio}
                    semana={semana}
                    rango={rango}
                    esSemanaActual={semana === semanaActual}
                    montaje={montajeSemana}
                    incidenciasSemana={deLaSemana.length}
                    defectoSemana={deLaSemana.reduce((a, i) => a + i.pz_defecto, 0)}
                    areas={areas}
                    departamentos={departamentos}
                    puedeCapturar={puedeCapturar}
                />

                <Panel titulo="Historial de incidencias" apunte={`${incidencias.length} en ${anio}`}>
                    {incidencias.length === 0 ? (
                        <p className="text-base-content/60 py-6 text-center text-sm">
                            Sin incidencias registradas este año.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Semana</th>
                                        <th>Área</th>
                                        <th>Departamento</th>
                                        <th className="text-right">Pz</th>
                                        <th>Folio</th>
                                        <th>Descripción</th>
                                        <th>Estado</th>
                                        <th />
                                    </tr>
                                </thead>
                                <tbody>
                                    {incidencias.map((i) => (
                                        <tr key={i.id} className={i.semana === semana ? 'bg-primary/5' : undefined}>
                                            <td className="font-medium whitespace-nowrap">Sem {i.semana}</td>
                                            <td>{etiquetaDe(areas, i.area)}</td>
                                            <td className="font-mono text-xs">
                                                {etiquetaDe(departamentos, i.departamento)}
                                            </td>
                                            <td className="text-right font-mono font-semibold">{i.pz_defecto}</td>
                                            <td className="font-mono text-xs">{i.folio ?? '—'}</td>
                                            <td className="max-w-96 text-xs">{i.descripcion ?? '—'}</td>
                                            <td>
                                                <Etiqueta
                                                    texto={i.abierta ? 'Abierta' : 'Cerrada'}
                                                    tono={i.abierta ? 'alerta' : 'ok'}
                                                />
                                                {i.capturista && (
                                                    <div className="text-base-content/50 text-[11px]">
                                                        {i.capturista.name}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="text-right whitespace-nowrap">
                                                {puedeCapturar && (
                                                    <button
                                                        type="button"
                                                        className="btn btn-ghost btn-xs"
                                                        onClick={() =>
                                                            router.patch(
                                                                `/admin/calidad/incidencias/${obra.id}/${i.id}/estado`,
                                                                {},
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                    >
                                                        {i.abierta ? 'Cerrar' : 'Reabrir'}
                                                    </button>
                                                )}
                                                {puedeEliminar && (
                                                    <DeleteDialog
                                                        deleteUrl={`/admin/calidad/incidencias/${obra.id}/${i.id}`}
                                                        title="Eliminar la incidencia"
                                                        description="Deja de contar en el porcentaje de la obra y en el reporte semanal. No se puede deshacer."
                                                        trigger={
                                                            <button
                                                                type="button"
                                                                className="btn btn-ghost btn-xs text-error"
                                                            >
                                                                <Trash2Icon className="size-3.5" />
                                                            </button>
                                                        }
                                                    />
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Panel>

                <Panel titulo="Avance de montaje por semana">
                    {historial.length === 0 ? (
                        <p className="text-base-content/60 py-6 text-center text-sm">Sin avance capturado.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Semana</th>
                                        <th className="text-right">Pz montadas</th>
                                        <th className="text-right">Incidencias</th>
                                        <th className="text-right">Pz con defecto</th>
                                        <th className="text-right">%</th>
                                        <th>Nota</th>
                                        <th />
                                    </tr>
                                </thead>
                                <tbody>
                                    {historial.map((f) => (
                                        <tr key={f.semana} className={f.semana === semana ? 'bg-primary/5' : undefined}>
                                            <td className="whitespace-nowrap">
                                                <span className="font-medium">Sem {f.semana}</span>{' '}
                                                <span className="text-base-content/50 text-xs">{f.rango}</span>
                                            </td>
                                            <td className="text-right font-mono">
                                                {f.pz_montadas === null ? (
                                                    <Etiqueta texto="falta" tono="alerta" />
                                                ) : (
                                                    num(f.pz_montadas)
                                                )}
                                            </td>
                                            <td className="text-right font-mono">{f.incidencias || '—'}</td>
                                            <td className="text-right font-mono">{f.pz_defecto || '—'}</td>
                                            <td className="text-right font-mono">
                                                {/* Con defectos y sin denominador no hay porcentaje que dar:
                                                    un «0 %» aquí se leería como que la semana salió limpia. */}
                                                {f.pz_defecto > 0 && f.tasa === null ? (
                                                    <Etiqueta texto="sin base" tono="malo" />
                                                ) : (
                                                    pct(f.tasa, 2)
                                                )}
                                            </td>
                                            <td className="text-xs">
                                                {f.sin_incidencias && f.incidencias === 0 && (
                                                    <Etiqueta texto="revisada, sin incidencias" tono="ok" />
                                                )}
                                                {f.notas && <div className="text-base-content/60">{f.notas}</div>}
                                            </td>
                                            <td className="text-right whitespace-nowrap">
                                                <button
                                                    type="button"
                                                    className="btn btn-ghost btn-xs"
                                                    title="Llevar la captura a esta semana"
                                                    onClick={() => ir({ semana: String(f.semana) })}
                                                >
                                                    <EyeIcon className="size-3.5" />
                                                </button>
                                                {puedeEliminar && f.montaje_id !== null && (
                                                    <DeleteDialog
                                                        deleteUrl={`/admin/calidad/incidencias/${obra.id}/montaje/${f.montaje_id}`}
                                                        title={`Borrar el avance de la semana ${f.semana}`}
                                                        description={
                                                            f.incidencias > 0
                                                                ? `Las ${f.incidencias} incidencia(s) de esa semana NO se borran, pero se quedan sin piezas montadas y su porcentaje pasa a «sin base». No se puede deshacer.`
                                                                : 'No se puede deshacer.'
                                                        }
                                                        trigger={
                                                            <button
                                                                type="button"
                                                                className="btn btn-ghost btn-xs text-error"
                                                            >
                                                                <Trash2Icon className="size-3.5" />
                                                            </button>
                                                        }
                                                    />
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot>
                                    <tr className="font-semibold">
                                        <td>TOTAL</td>
                                        <td className="text-right font-mono">{num(pzMontadas)}</td>
                                        <td className="text-right font-mono">{incidencias.length}</td>
                                        <td className="text-right font-mono">{pzDefecto}</td>
                                        <td className="text-right font-mono">{pct(tasa, 2)}</td>
                                        <td colSpan={2} />
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    )}
                </Panel>

                <Rejilla2>
                    <TarjetaGrafica
                        titulo="Piezas con defecto por semana"
                        pie="Se cuentan piezas, no incidencias: un solo hallazgo puede afectar a diez."
                    >
                        <BarrasRanking
                            datos={graficas.porSemana.map((f) => ({ nombre: `Sem ${f.semana}`, valor: f.pz }))}
                            decimales={0}
                        />
                    </TarjetaGrafica>
                    <TarjetaGrafica
                        titulo="Por departamento responsable"
                        pie="Es el corte que decide a quién se le manda la acción correctiva."
                    >
                        <BarrasRanking
                            datos={graficas.porDepartamento.map((f) => ({ nombre: f.etiqueta, valor: f.pz }))}
                            decimales={0}
                        />
                    </TarjetaGrafica>
                </Rejilla2>
            </div>
        </AppLayout>
    );
}
