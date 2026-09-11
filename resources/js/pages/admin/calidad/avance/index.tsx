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
 * semana con sus cinco bloques. El plan va uno por obra, semana y
 * transformación, porque 2ª y pintura no van al mismo ritmo; el cruce lo
 * calcula el servidor. Semana, obra y transformación viven en la URL.
 */

import { Head, router } from '@inertiajs/react';
import { numeroSemana, rangoSemana } from '@/components/qal/avance/semanas';
import type { ComparativaAvance, Fase, VistaAvance } from '@/components/qal/avance/tipos';
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

type ObraOpcion = { id: number; no: string | null; descripcion: string | null };

type Props = {
    semana: string;
    semanas: string[];
    obras: ObraOpcion[];
    obraId: number | null;
    fase: Fase;
    /** Con obra elegida: su plan de la semana, ya cruzado. */
    vista: VistaAvance | null;
    /** Sin obra elegida: la portada con todas. */
    comparativa: ComparativaAvance | null;
    puedeCapturar: boolean;
};

const nombreDe = (obra: ObraOpcion | undefined) => (obra ? [obra.no, obra.descripcion].filter(Boolean).join(' — ') : '');

export default function AvanceProduccion({ semana, semanas, obras, obraId, fase, vista, comparativa, puedeCapturar }: Props) {
    const ir = (cambios: { semana?: string; obra?: number | null; fase?: Fase }) => {
        const destino = { semana, obra: obraId, fase, ...cambios };

        router.get('/admin/calidad/avance', {
            semana: destino.semana,
            ...(destino.obra ? { obra: destino.obra, fase: destino.fase } : {}),
        });
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
                            <Select value={semana} onValueChange={(valor) => ir({ semana: valor })} className="select-sm w-60">
                                {semanas.map((s) => (
                                    <SelectItem key={s} value={s}>
                                        {`Semana ${numeroSemana(s)} · ${rangoSemana(s)}`}
                                    </SelectItem>
                                ))}
                            </Select>
                        </label>
                        <label className="text-xs">
                            <span className="text-base-content/60 mb-1 block">Obra</span>
                            <Select
                                value={obraId ? String(obraId) : ''}
                                onValueChange={(valor) => ir({ obra: valor ? Number(valor) : null, fase: '2' })}
                                className="select-sm w-56"
                            >
                                <SelectItem value="">Todas las obras</SelectItem>
                                {obras.map((o) => (
                                    <SelectItem key={o.id} value={String(o.id)}>
                                        {nombreDe(o)}
                                    </SelectItem>
                                ))}
                            </Select>
                        </label>
                    </div>
                </div>

                {obraId !== null && vista ? (
                    <VistaObra
                        key={`${obraId}|${fase}|${semana}`}
                        obraId={obraId}
                        obra={nombreDe(obras.find((o) => o.id === obraId))}
                        semana={semana}
                        fase={fase}
                        vista={vista}
                        puedeCapturar={puedeCapturar}
                        onFase={(siguiente) => ir({ fase: siguiente })}
                        onVolver={() => ir({ obra: null })}
                    />
                ) : (
                    comparativa && (
                        <VistaComparativa
                            semana={semana}
                            comparativa={comparativa}
                            onAbrir={(id, suFase) => ir({ obra: id, fase: suFase ?? '2' })}
                        />
                    )
                )}
            </div>
        </AppLayout>
    );
}
