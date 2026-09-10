/**
 * Avance de producción — el control semanal de producción contra calidad.
 *
 * Es el `Produccion_Steelex.html` de la aplicación anterior, portado al mono.
 * Producción pega la lista de marcas que espera fabricar esta semana —una por
 * línea, tal cual sale de su hoja— y todo lo demás se deduce cruzándola con lo
 * que ha visto calidad.
 *
 * La unidad es **la pieza, no la cantidad**, y esa decisión resuelve sola los dos
 * problemas difíciles:
 *
 *  - El **arrastre** deja de ser un número que se acumula y pasa a ser una lista
 *    de piezas concretas: una marca no puede contarse dos veces, y siempre se
 *    puede señalar cuál lleva tres semanas sin hacerse.
 *  - Las **reparaciones** no vuelven al plan. Una pieza rechazada ya está
 *    fabricada; reprogramarla la contaría dos veces y además repararla no cuesta
 *    lo mismo que armarla. Vive en su propio bloque, con su antigüedad.
 *
 * Dos vistas: la portada compara todas las obras sin sumarlas —una obra
 * adelantada taparía a otra retrasada—, y dentro de una obra está el plan de la
 * semana con sus cinco bloques.
 *
 * Todavía no lee ni escribe en la base: `qal_inspecciones` y `qal_programaciones`
 * no existen. Los datos salen de `components/qal/avance/datos.ts` y el botón de
 * Guardar está desactivado a propósito — dejar guardar sin persistir sería peor
 * que no ofrecerlo.
 *
 * Divergencia con la especificación que hay que resolver antes del backend:
 * RF-13.5 pide **una sola programación por obra y semana**, pero la aplicación
 * anterior lleva una por obra, semana y transformación, y su razón es buena: 2ª
 * y 3ª no van al mismo ritmo, y una pieza que se termina de fabricar el viernes
 * no da tiempo a pintarse esa semana. La maqueta sigue el comportamiento de la
 * aplicación anterior.
 */

import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { indexar, type IndicePiezas } from '@/components/qal/avance/calculo';
import {
    maquetaDeEjemplo,
    OBRAS_AVANCE,
    semanaActual,
    semanasDisponibles,
    type Fase,
} from '@/components/qal/avance/datos';
import { numeroSemana, rangoSemana } from '@/components/qal/avance/semanas';
import { VistaComparativa } from '@/components/qal/avance/vista-comparativa';
import { VistaObra } from '@/components/qal/avance/vista-obra';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Calidad', href: '/admin/calidad/catalogos' },
    { title: 'Avance de producción', href: '/admin/calidad/avance' },
];

export default function AvanceProduccion() {
    const { piezas, enProceso, planes } = useMemo(() => maquetaDeEjemplo(), []);

    const semanas = useMemo(() => semanasDisponibles(), []);
    const [semana, setSemana] = useState(semanaActual);
    const [obra, setObra] = useState('');
    const [fase, setFase] = useState<Fase>('2');

    const indices = useMemo<Record<Fase, IndicePiezas>>(
        () => ({ '2': indexar(piezas['2']), '3': indexar(piezas['3']) }),
        [piezas],
    );

    const abrir = (cual: string, suFase?: Fase) => {
        setObra(cual);
        if (suFase) {
            setFase(suFase);
        }
        window.scrollTo(0, 0);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Calidad — Avance de producción" />

            <div className="space-y-4 p-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Avance de producción</h1>
                        <p className="text-base-content/60 text-sm">
                            Lo que producción programó contra lo que calidad vio. La unidad es la pieza, no la cantidad.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-end gap-2">
                        <label className="text-xs">
                            <span className="text-base-content/60 mb-1 block">Semana</span>
                            <Select
                                value={semana}
                                onValueChange={setSemana}
                                className="select-sm w-60"
                            >
                                {semanas.map((s) => (
                                    <SelectItem key={s} value={s}>
                                        {`Semana ${numeroSemana(s)} · ${rangoSemana(s)}`}
                                    </SelectItem>
                                ))}
                            </Select>
                        </label>
                        <label className="text-xs">
                            <span className="text-base-content/60 mb-1 block">Obra</span>
                            <Select value={obra} onValueChange={setObra} className="select-sm w-56">
                                <SelectItem value="">Todas las obras</SelectItem>
                                {OBRAS_AVANCE.map((o) => (
                                    <SelectItem key={o} value={o}>
                                        {o}
                                    </SelectItem>
                                ))}
                            </Select>
                        </label>
                        <span className="badge badge-warning badge-sm font-semibold">Maqueta · datos de ejemplo</span>
                    </div>
                </div>

                {obra ? (
                    <VistaObra
                        obra={obra}
                        semana={semana}
                        fase={fase}
                        planes={planes}
                        indices={indices}
                        enProceso={enProceso}
                        piezas={piezas[fase]}
                        onFase={setFase}
                        onVolver={() => setObra('')}
                    />
                ) : (
                    <VistaComparativa
                        semana={semana}
                        obras={OBRAS_AVANCE}
                        planes={planes}
                        indices={indices}
                        enProceso={enProceso}
                        piezas={piezas['2']}
                        onAbrir={abrir}
                    />
                )}
            </div>
        </AppLayout>
    );
}
