/**
 * Reporte semanal de calidad — el F-STX-CA-31 que se manda a dirección.
 *
 * Es el `Dashboard_Calidad_Steelex.html` en su pestaña «Reporte semanal»,
 * portado al mono. No es el tablero con otro peinado: el tablero se filtra y se
 * explora, y esto es un **documento** de seis hojas fijas con folio de formato y
 * una semana de corte, que sale igual todas las semanas para poder compararse
 * con las anteriores. Por eso arriba sólo hay dos controles —año y semana— y no
 * la barra de filtros del tablero.
 *
 * Las seis hojas se calculan de la base: la inspección visual (0, 1 y 3), PND
 * (2) y el montaje e incidencias en obra (4 y 5). Cada hoja dice de dónde salen
 * sus números en su encabezado, porque un documento que se manda fuera no
 * puede dejar la duda.
 *
 * La corrección de fórmula que trae este reporte y que no hay que perder: el
 * porcentaje de incidencias es **piezas liberadas que traían rechazo previo ÷
 * piezas liberadas**, no «rechazadas ÷ liberadas de la misma semana». Lo
 * segundo daba 140 % y 240 % porque son dos conjuntos distintos de piezas.
 */

import { Head, router } from '@inertiajs/react';
import { PrinterIcon } from 'lucide-react';
import type { FilaMontaje, FilaVisual, PuntoSerie } from '@/components/qal/reporte-semanal/datos';
import { HojaMontaje } from '@/components/qal/reporte-semanal/hoja-montaje';
import { HojaPnd } from '@/components/qal/reporte-semanal/hoja-pnd';
import { HojaResumen } from '@/components/qal/reporte-semanal/hoja-resumen';
import { HojaTendencia } from '@/components/qal/reporte-semanal/hoja-tendencia';
import { HojaVisual } from '@/components/qal/reporte-semanal/hoja-visual';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { QalReporteSemanalPnd } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Calidad', href: '/admin/calidad/catalogos' },
    { title: 'Reporte semanal', href: '/admin/calidad/reporte-semanal' },
];

type Props = {
    anio: number;
    semana: number;
    anios: number[];
    semanas: number[];
    /** Hoja 1: lo liberado en la semana por obra, con su rechazo previo. */
    visual: FilaVisual[];
    /** Kilos de estructura principal liberados en la semana. */
    kg_liberados: number;
    /** Hoja 3: la misma cuenta semana a semana hasta el corte. */
    serie: PuntoSerie[];
    pnd: QalReporteSemanalPnd[];
    /** Hoja 4: todas las incidencias, partidas por área —taller / montaje—. */
    montaje: FilaMontaje[];
    /** Hoja 5: sólo las de pintura, partidas por departamento —taller / obra—. */
    pintura: FilaMontaje[];
    /** Piezas montadas en la semana de corte, en todas las obras. */
    montadas_semana: number;
};

export default function ReporteSemanal({
    anio,
    semana,
    anios,
    semanas,
    visual,
    kg_liberados: kgLiberados,
    serie,
    pnd,
    montaje,
    pintura,
    montadas_semana: montadasSemana,
}: Props) {
    /** El corte viaja en la URL: un reporte se manda por correo con su semana. */
    const cortar = (cambios: Record<string, string>) => {
        router.get(window.location.pathname, { anio: String(anio), semana: String(semana), ...cambios }, {
            preserveScroll: true,
            replace: true,
        });
    };

    const spotsPnd = pnd.reduce((a, f) => a + f.spots, 0);

    // Los proyectos del alcance son los que tienen algo en el reporte: lo que
    // liberaron esta semana o lo que llevan ensayado.
    const proyectos = [...new Set([...visual.map((f) => f.obra), ...pnd.map((f) => f.obra)])];

    // Las piezas con incidencia de la semana salen de la hoja 4, que es la que
    // cuenta todas: la 5 es un corte suyo y sumarlas contaría dos veces.
    const incidenciasSemana = montaje.reduce((a, f) => a + f.aSemana + f.bSemana, 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Calidad — Reporte semanal" />

            <div className="space-y-4 p-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Reporte semanal de calidad</h1>
                        <p className="text-base-content/60 text-sm">
                            F-STX-CA-31 · seis hojas con corte semanal. Cada hoja dice si sus números salen de la base o
                            son de ejemplo.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-end gap-2">
                        <label className="text-xs">
                            <span className="text-base-content/60 mb-1 block">Año</span>
                            <Select
                                value={String(anio)}
                                onValueChange={(valor) => cortar({ anio: valor, semana: '' })}
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
                                onValueChange={(valor) => cortar({ semana: valor })}
                                className="select-sm w-36"
                            >
                                {semanas.map((s) => (
                                    <SelectItem key={s} value={String(s)}>
                                        Semana {s}
                                    </SelectItem>
                                ))}
                            </Select>
                        </label>
                        <button type="button" onClick={() => window.print()} className="btn btn-sm btn-outline">
                            <PrinterIcon className="size-4" />
                            Imprimir
                        </button>
                    </div>
                </div>

                <HojaResumen
                    anio={anio}
                    semana={semana}
                    obras={proyectos}
                    spotsPnd={spotsPnd}
                    incidenciasSemana={incidenciasSemana}
                    filas={visual}
                    kgLiberados={kgLiberados}
                    serie={serie}
                />
                <HojaVisual semana={semana} filas={visual} />
                <HojaPnd filas={pnd} />
                <HojaTendencia anio={anio} semana={semana} serie={serie} />
                <HojaMontaje
                    numero={4}
                    titulo="Montaje e incidencias en obra"
                    pregunta="¿Cuánto se ha montado y cuántas piezas dieron problema?"
                    semana={semana}
                    filas={montaje}
                    montadasSemana={montadasSemana}
                    nombreA="Incidencias de taller"
                    nombreB="Incidencias de montaje"
                    columnaA="Inc. taller"
                    columnaB="Inc. montaje"
                    variante="montaje"
                    notaAreas="La estadística arranca en la semana en que se empezó a capturar, igual que en el formato en Excel."
                />
                <HojaMontaje
                    numero={5}
                    titulo="Incidencias de pintura"
                    pregunta="¿Dónde se está dañando el recubrimiento, en taller o en obra?"
                    semana={semana}
                    filas={pintura}
                    montadasSemana={montadasSemana}
                    nombreA="Incidencias de pintura en taller"
                    nombreB="Incidencias de pintura en obra"
                    columnaA="Inc. taller"
                    columnaB="Inc. obra"
                    variante="pintura"
                    notaAreas="El daño en taller y el daño en obra se separan porque la acción es distinta: uno se corrige en planta y el otro se repara en sitio, que suele salir más caro."
                />
            </div>
        </AppLayout>
    );
}
