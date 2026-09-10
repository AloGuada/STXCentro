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
 *
 * La presentación es la del kit del módulo (`components/qal/ui`), el mismo del
 * avance de producción: las dos pantallas comparan obras entre sí y se leen de
 * un vistazo, así que la tarjeta de obra, los números de cabecera y las tablas
 * se ven igual en las dos. Los datos y el cálculo no cambian.
 */

import { Head, Link, router } from '@inertiajs/react';
import { ArrowRightIcon } from 'lucide-react';
import { BarrasRanking } from '@/components/qal/graficas';
import { num, pct } from '@/components/qal/paleta';
import { Barra, Kpi, Leyenda, Pastilla, Tarjeta, type Tono } from '@/components/qal/ui';
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
function tonoDeTasa(tasa: number | null): Tono {
    if (tasa === null) {
        return 'neutro';
    }

    return tasa >= 5 ? 'error' : tasa >= 2 ? 'warn' : 'ok';
}

/**
 * La barra de la tarjeta de obra mide lo LIMPIO, no lo defectuoso.
 *
 * Es la única forma de que llena y verde signifique lo mismo aquí que en el
 * avance de producción: una barra que se llenara con el porcentaje de
 * incidencias se leería al revés justo en la obra que va peor.
 */
function limpieza(tasa: number | null): number | null {
    return tasa === null ? null : Math.max(0, 100 - tasa);
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

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Kpi titulo="Incidencias" valor={num(total.incidencias)} pie={`registradas en ${anio}`} />
                    <Kpi
                        titulo="Piezas con defecto"
                        valor={num(total.pz_defecto)}
                        pie={`${pct(tasaTotal, 2)} de lo montado`}
                        tono={total.pz_defecto > 0 ? 'error' : 'ok'}
                    />
                    <Kpi titulo="Piezas montadas" valor={num(total.pz_montadas)} pie="acumulado del año" />
                    <Kpi
                        titulo="Sin cerrar"
                        valor={num(total.abiertas)}
                        pie={total.abiertas > 0 ? 'pendientes de resolver' : 'todo cerrado'}
                        tono={total.abiertas > 0 ? 'warn' : 'ok'}
                    />
                </div>

                <Tarjeta titulo="Obras" nota="de la peor a la mejor · pulsa para capturar">
                    {ordenadas.length === 0 ? (
                        <p className="text-base-content/60 px-4 py-10 text-center text-sm">
                            Todavía no hay nada registrado en {anio}. Entra en una obra para capturar el montaje y las
                            incidencias.
                        </p>
                    ) : (
                        <div className="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3">
                            {ordenadas.map((obra) => (
                                <Link
                                    key={obra.id}
                                    href={`/admin/calidad/incidencias/${obra.id}?anio=${anio}`}
                                    className="border-base-300 bg-base-100 hover:border-primary/50 block rounded-xl border p-3 transition-colors"
                                >
                                    <div className="font-semibold">{obra.no}</div>
                                    {obra.descripcion && (
                                        <div className="text-base-content/50 truncate text-xs">{obra.descripcion}</div>
                                    )}

                                    <div className="mt-2 flex items-baseline gap-2">
                                        <span className="text-2xl leading-none font-bold">{pct(obra.tasa, 2)}</span>
                                        <span className="text-base-content/50 text-xs">de incidencias</span>
                                    </div>
                                    <Barra porcentaje={limpieza(obra.tasa)} />
                                    <div className="text-base-content/50 mt-1 text-xs">
                                        {num(obra.pz_defecto)} pieza(s) con defecto de {num(obra.pz_montadas)} montadas
                                    </div>

                                    <div className="mt-1.5 flex flex-wrap gap-1">
                                        <Pastilla tono={tonoDeTasa(obra.tasa)}>
                                            {obra.incidencias} incidencia(s)
                                        </Pastilla>
                                        {obra.abiertas > 0 && (
                                            <Pastilla tono="warn">{obra.abiertas} sin cerrar</Pastilla>
                                        )}
                                        {obra.incidencias_semana > 0 && (
                                            <Pastilla tono="error">
                                                {obra.incidencias_semana} en la semana {semanaActual}
                                            </Pastilla>
                                        )}
                                    </div>

                                    <div className="text-primary mt-2 inline-flex items-center gap-1 text-xs font-semibold">
                                        Abrir la obra <ArrowRightIcon className="size-3.5" />
                                    </div>
                                </Link>
                            ))}
                        </div>
                    )}

                    {sinCapturar.length > 0 && (
                        <div className="border-base-300 border-t p-4">
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
                </Tarjeta>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Tarjeta titulo="Piezas con defecto por semana">
                        <div className="p-4">
                            <BarrasRanking
                                datos={graficas.porSemana.map((f) => ({ nombre: `Sem ${f.semana}`, valor: f.pz }))}
                                decimales={0}
                            />
                        </div>
                        <Leyenda>
                            Se cuentan piezas, no incidencias: un solo hallazgo puede afectar a diez.
                        </Leyenda>
                    </Tarjeta>

                    <Tarjeta titulo="Por departamento responsable">
                        <div className="p-4">
                            <BarrasRanking
                                datos={graficas.porDepartamento.map((f) => ({ nombre: f.etiqueta, valor: f.pz }))}
                                decimales={0}
                            />
                        </div>
                        <Leyenda>
                            Es el corte que decide a quién se le manda la acción correctiva. Los departamentos sin nada
                            no salen.
                        </Leyenda>
                    </Tarjeta>

                    <Tarjeta titulo="Por mes">
                        <div className="p-4">
                            <BarrasRanking
                                datos={graficas.porMes.map((f) => ({ nombre: f.etiqueta, valor: f.pz }))}
                                decimales={0}
                            />
                        </div>
                        <Leyenda>
                            El mes de una semana es el de su jueves, que es la regla ISO: así una semana a caballo entre
                            dos meses no se cuenta en los dos.
                        </Leyenda>
                    </Tarjeta>

                    <Tarjeta titulo="% de incidencias por obra">
                        <div className="p-4">
                            <BarrasRanking
                                datos={ordenadas
                                    .filter((o) => o.tasa !== null)
                                    .map((o) => ({ nombre: o.no, valor: o.tasa as number }))}
                                unidad="%"
                                decimales={2}
                            />
                        </div>
                        <Leyenda>
                            Sobre piezas montadas. Las obras sin montaje capturado no aparecen: no tienen denominador.
                        </Leyenda>
                    </Tarjeta>
                </div>

                <Tarjeta titulo="Detalle por obra" nota={String(anio)}>
                    <div className="overflow-x-auto">
                        <table className="table-sm table w-full whitespace-nowrap">
                            <thead>
                                <tr>
                                    <th className="bg-base-200">Obra</th>
                                    <th className="bg-base-200 text-right">Incidencias</th>
                                    <th className="bg-base-200 text-right">Pz con defecto</th>
                                    <th className="bg-base-200 text-right">Pz montadas</th>
                                    <th className="bg-base-200 text-right">% incidencias</th>
                                    <th className="bg-base-200 text-right">Sin cerrar</th>
                                </tr>
                            </thead>
                            <tbody>
                                {[...obras]
                                    .sort((a, b) => a.no.localeCompare(b.no, 'es'))
                                    .map((obra) => (
                                        <tr key={obra.id} className="hover:bg-base-200/50">
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
                                                    <Pastilla tono="warn">{obra.abiertas}</Pastilla>
                                                ) : (
                                                    <span className="text-base-content/30">—</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                            </tbody>
                            <tfoot>
                                <tr className="bg-base-200 font-semibold">
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
                </Tarjeta>
            </div>
        </AppLayout>
    );
}
