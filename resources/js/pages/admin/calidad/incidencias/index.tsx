/**
 * Incidencias en obra — la portada: todas las obras del año.
 *
 * Es un circuito aparte del taller. La captura del inspector mide lo que se
 * rechaza en planta; esto mide lo que aparece en sitio, ya con la pieza
 * montada, y se atribuye a un departamento responsable para saber a quién
 * mandar la acción correctiva.
 *
 * La lista va ordenada por porcentaje, de la peor obra a la mejor, y no
 * alfabéticamente: existe para decir dónde hay que mirar.
 *
 * Las obras sin nada capturado salen aparte y no como filas en cero. Una obra
 * sin captura no es una obra sin incidencias, y mezclarlas repetiría el error
 * que este módulo corrige: confundir «revisado» con «vacío».
 */

import { Head, Link, router } from '@inertiajs/react';
import { ArrowRightIcon } from 'lucide-react';
import {
    Etiqueta,
    FilaKpis,
    Panel,
    Rejilla2,
    TarjetaGrafica,
    TarjetaKpi,
    type Tono,
} from '@/components/qal/dashboard/ui';
import { BarrasRanking } from '@/components/qal/graficas';
import { num, pct } from '@/components/qal/paleta';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { QalIncidenciaObraResumen } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Calidad', href: '/admin/calidad/catalogos' },
    { title: 'Incidencias en obra', href: '/admin/calidad/incidencias' },
];

type Props = {
    anio: number;
    anios: number[];
    semanaActual: number;
    obras: QalIncidenciaObraResumen[];
    sinCapturar: { id: number; no: string }[];
    graficas: {
        porSemana: { semana: number; pz: number }[];
        porDepartamento: { clave: string; etiqueta: string; pz: number }[];
        porMes: { mes: number; etiqueta: string; pz: number }[];
    };
};

/**
 * Verde hasta el 2 %, ámbar hasta el 5 %, rojo por encima. Es el umbral con el
 * que se lee el formato, y sin denominador no hay color que dar: gris.
 */
function tonoEtiqueta(tasa: number | null): Tono {
    if (tasa === null) {
        return 'neutro';
    }

    return tasa >= 5 ? 'malo' : tasa >= 2 ? 'alerta' : 'ok';
}

export default function IncidenciasIndex({ anio, anios, semanaActual, obras, sinCapturar, graficas }: Props) {
    const total = obras.reduce(
        (a, o) => ({
            incidencias: a.incidencias + o.incidencias,
            pz_defecto: a.pz_defecto + o.pz_defecto,
            pz_montadas: a.pz_montadas + o.pz_montadas,
            abiertas: a.abiertas + o.abiertas,
        }),
        { incidencias: 0, pz_defecto: 0, pz_montadas: 0, abiertas: 0 },
    );

    const tasaTotal = total.pz_montadas > 0 ? (total.pz_defecto * 100) / total.pz_montadas : null;
    const ordenadas = [...obras].sort((a, b) => (b.tasa ?? -1) - (a.tasa ?? -1));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Incidencias en obra" />

            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Incidencias en obra</h1>
                        <p className="text-base-content/60 max-w-2xl text-sm">
                            Lo que falla durante el montaje, atribuido a un departamento responsable. No se suma con lo
                            que Calidad rechaza en planta: aquí el denominador son las piezas ya montadas.
                        </p>
                    </div>

                    <label className="text-xs">
                        <span className="text-base-content/60 mb-1 block">Año</span>
                        <Select
                            value={String(anio)}
                            onValueChange={(valor) =>
                                router.get(window.location.pathname, { anio: valor }, { replace: true })
                            }
                            className="select-sm w-28"
                        >
                            {anios.map((a) => (
                                <SelectItem key={a} value={String(a)}>
                                    {a}
                                </SelectItem>
                            ))}
                        </Select>
                    </label>
                </div>

                <FilaKpis>
                    <TarjetaKpi
                        kpi={{ etiqueta: 'Incidencias', valor: num(total.incidencias), nota: `registradas en ${anio}` }}
                    />
                    <TarjetaKpi
                        kpi={{
                            etiqueta: 'Piezas con defecto',
                            valor: num(total.pz_defecto),
                            nota: `${pct(tasaTotal, 2)} de lo montado`,
                            tono: total.pz_defecto > 0 ? 'malo' : 'bueno',
                        }}
                    />
                    <TarjetaKpi
                        kpi={{
                            etiqueta: 'Piezas montadas',
                            valor: num(total.pz_montadas),
                            nota: 'acumulado del año',
                        }}
                    />
                    <TarjetaKpi
                        kpi={{
                            etiqueta: 'Sin cerrar',
                            valor: num(total.abiertas),
                            nota: total.abiertas > 0 ? 'pendientes de resolver' : 'todo cerrado',
                            tono: total.abiertas > 0 ? 'pendiente' : 'bueno',
                        }}
                    />
                </FilaKpis>

                <Panel titulo="Obras" apunte="de la peor a la mejor · pulsa para capturar">
                    {ordenadas.length === 0 ? (
                        <p className="text-base-content/60 py-6 text-center text-sm">
                            Todavía no hay nada registrado en {anio}. Entra en una obra para capturar el montaje y las
                            incidencias.
                        </p>
                    ) : (
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {ordenadas.map((obra) => (
                                <Link
                                    key={obra.id}
                                    href={`/admin/calidad/incidencias/${obra.id}?anio=${anio}`}
                                    className="rounded-box border-base-300 bg-base-100 hover:border-primary/50 block border p-4 transition"
                                >
                                    <div className="text-sm font-semibold">{obra.no}</div>
                                    {obra.descripcion && (
                                        <div className="text-base-content/50 truncate text-xs">{obra.descripcion}</div>
                                    )}

                                    <div className="mt-3 text-3xl leading-none font-semibold">{pct(obra.tasa, 2)}</div>
                                    <div className="text-base-content/60 mt-1 text-xs">
                                        {num(obra.pz_defecto)} pieza(s) con defecto de {num(obra.pz_montadas)} montadas
                                    </div>

                                    <div className="mt-3 flex flex-wrap gap-1.5">
                                        <Etiqueta
                                            texto={`${obra.incidencias} incidencia(s)`}
                                            tono={tonoEtiqueta(obra.tasa)}
                                        />
                                        {obra.abiertas > 0 && (
                                            <Etiqueta texto={`${obra.abiertas} sin cerrar`} tono="alerta" />
                                        )}
                                        {obra.incidencias_semana > 0 && (
                                            <Etiqueta
                                                texto={`${obra.incidencias_semana} en la semana ${semanaActual}`}
                                                tono="malo"
                                            />
                                        )}
                                    </div>

                                    <div className="text-primary mt-3 inline-flex items-center gap-1 text-xs font-semibold">
                                        Abrir la obra <ArrowRightIcon className="size-3.5" />
                                    </div>
                                </Link>
                            ))}
                        </div>
                    )}

                    {sinCapturar.length > 0 && (
                        <div className="border-base-300 mt-4 border-t pt-3">
                            <p className="text-base-content/60 text-xs">
                                Sin nada capturado en {anio} — una obra sin captura no es una obra sin incidencias:
                            </p>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {sinCapturar.map((obra) => (
                                    <Link
                                        key={obra.id}
                                        href={`/admin/calidad/incidencias/${obra.id}?anio=${anio}`}
                                        className="btn btn-xs btn-outline"
                                    >
                                        {obra.no}
                                    </Link>
                                ))}
                            </div>
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
                        pie="Es el corte que decide a quién se le manda la acción correctiva. Los departamentos sin nada no salen."
                    >
                        <BarrasRanking
                            datos={graficas.porDepartamento.map((f) => ({ nombre: f.etiqueta, valor: f.pz }))}
                            decimales={0}
                        />
                    </TarjetaGrafica>

                    <TarjetaGrafica
                        titulo="Por mes"
                        pie="El mes de una semana es el de su jueves, que es la regla ISO: así una semana a caballo entre dos meses no se cuenta en los dos."
                    >
                        <BarrasRanking
                            datos={graficas.porMes.map((f) => ({ nombre: f.etiqueta, valor: f.pz }))}
                            decimales={0}
                        />
                    </TarjetaGrafica>

                    <TarjetaGrafica
                        titulo="% de incidencias por obra"
                        pie="Sobre piezas montadas. Las obras sin montaje capturado no aparecen: no tienen denominador."
                    >
                        <BarrasRanking
                            datos={ordenadas
                                .filter((o) => o.tasa !== null)
                                .map((o) => ({ nombre: o.no, valor: o.tasa as number }))}
                            unidad="%"
                            decimales={2}
                        />
                    </TarjetaGrafica>
                </Rejilla2>

                <Panel titulo="Detalle por obra" apunte={String(anio)}>
                    <div className="overflow-x-auto">
                        <table className="table-zebra table table-sm">
                            <thead>
                                <tr>
                                    <th>Obra</th>
                                    <th className="text-right">Incidencias</th>
                                    <th className="text-right">Pz con defecto</th>
                                    <th className="text-right">Pz montadas</th>
                                    <th className="text-right">% incidencias</th>
                                    <th className="text-right">Sin cerrar</th>
                                </tr>
                            </thead>
                            <tbody>
                                {[...obras]
                                    .sort((a, b) => a.no.localeCompare(b.no, 'es'))
                                    .map((obra) => (
                                        <tr key={obra.id}>
                                            <td>
                                                <Link
                                                    href={`/admin/calidad/incidencias/${obra.id}?anio=${anio}`}
                                                    className="link link-hover font-medium"
                                                >
                                                    {obra.no}
                                                </Link>
                                            </td>
                                            <td className="text-right font-mono">{obra.incidencias}</td>
                                            <td className="text-right font-mono">{obra.pz_defecto}</td>
                                            <td className="text-right font-mono">{num(obra.pz_montadas)}</td>
                                            <td className="text-right font-mono font-semibold">
                                                {pct(obra.tasa, 2)}
                                            </td>
                                            <td className="text-right">
                                                {obra.abiertas > 0 ? (
                                                    <Etiqueta texto={String(obra.abiertas)} tono="alerta" />
                                                ) : (
                                                    <span className="text-base-content/30">—</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                            </tbody>
                            <tfoot>
                                <tr className="font-semibold">
                                    <td>TOTAL</td>
                                    <td className="text-right font-mono">{total.incidencias}</td>
                                    <td className="text-right font-mono">{total.pz_defecto}</td>
                                    <td className="text-right font-mono">{num(total.pz_montadas)}</td>
                                    <td className="text-right font-mono">{pct(tasaTotal, 2)}</td>
                                    <td className="text-right font-mono">{total.abiertas}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </Panel>
            </div>
        </AppLayout>
    );
}
