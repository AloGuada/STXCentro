/**
 * Tablero de Calidad — el `Dashboard_Calidad_Steelex.html` de la aplicación
 * anterior, reestructurado.
 *
 * El original eran siete pestañas de primer nivel (Resumen ejecutivo, Analítica,
 * PND, Accesorios, Reporte semanal, Análisis, Glosario) y, dentro de Analítica,
 * otras tres. Aquí las dos primeras se aplanan en **una sola página**:
 *
 *     barra de filtros  →  resumen ejecutivo  →  resumen analítica
 *                       →  tabs: Operación · Estadística · Diagnóstico · Accesorios · Glosario
 *
 * PND ya es pantalla propia en el mono (`/admin/calidad/pnd`), así que no se
 * duplica aquí.
 *
 * «Resultado final por obra» bajó del resumen ejecutivo a Operación: es una
 * respuesta a *dónde* falla, no uno de los números con los que se abre.
 *
 * Todo se calcula en el servidor (`TableroCalidad`) con los filtros de la URL;
 * Accesorios se pide al abrir su pestaña.
 */

import { Head, router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { TabAccesorios, type DatosAccesorios } from '@/components/qal/dashboard/accesorios';
import { TabDiagnostico } from '@/components/qal/dashboard/diagnostico';
import { TabEstadistica } from '@/components/qal/dashboard/estadistica';
import { BarraFiltros, filtrosDeLaUrl, resumenFiltros, type FiltrosTablero } from '@/components/qal/dashboard/filtros';
import { TabGlosario } from '@/components/qal/dashboard/glosario';
import {
    alertas,
    hallazgos,
    kpisAnalitica,
    kpisEjecutivo,
    notaRechazoPor,
    tasaPublicable,
    tasasSinBase,
} from '@/components/qal/dashboard/resumen';
import {
    COBERTURA_MINIMA,
    ETIQUETA_BASE,
    type BaseTasa,
    type DatosTablero,
    type DimensionRechazo,
    type DimensionTasa,
    type Fase,
    type OpcionesFiltros,
    type OrigenPareto,
} from '@/components/qal/dashboard/tipos';
import {
    FilaKpis,
    NotaCallada,
    Panel,
    Rejilla2,
    SelectorTarjeta,
    Tabs,
    TarjetaGrafica,
    TarjetaKpi,
    type Tab,
} from '@/components/qal/dashboard/ui';
import { BarrasRanking, BarrasSimples, Pareto, ResultadoPorObra, TendenciaLinea } from '@/components/qal/graficas';
import { num } from '@/components/qal/paleta';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Calidad', href: '/admin/calidad/catalogos' },
    { title: 'Tablero', href: '/admin/calidad/dashboard' },
];

type TabTablero = 'op' | 'est' | 'diag' | 'acc' | 'glo';

const TABS: Tab<TabTablero>[] = [
    { valor: 'op', titulo: '🏭 Operación', nota: 'dónde falla' },
    { valor: 'est', titulo: '📐 Estadística', nota: '¿es real?' },
    { valor: 'diag', titulo: '🔎 Diagnóstico', nota: '¿el dato sirve?' },
];

const TAB_ACCESORIOS: Tab<TabTablero> = { valor: 'acc', titulo: '📦 Accesorios', nota: '¿cuánto se liberó?' };

const TAB_GLOSARIO: Tab<TabTablero> = { valor: 'glo', titulo: '📖 Glosario', nota: '¿qué significa?' };

const ETIQUETA_FASE: Record<Fase, string> = {
    '1ª': '1ª · Corte',
    '2ª': '2ª · Armado y soldado',
    '3ª': '3ª · Pintura',
};

type Props = {
    /** La pestaña que pidió la URL; sólo Accesorios se recuerda. */
    tab: 'accesorios' | null;
    filtros: Record<keyof FiltrosTablero, string | null>;
    opciones: OpcionesFiltros;
    tablero: DatosTablero;
    /** Los lotes, sólo con la pestaña Accesorios abierta y con permiso de verlos. */
    accesorios: DatosAccesorios | null;
};

/** Una gráfica sin datos no se dibuja vacía: se dice por qué no hay nada. */
function SinDatos({ hay, children }: { hay: boolean; children: ReactNode }) {
    return hay ? children : <NotaCallada>Sin datos con estos filtros.</NotaCallada>;
}

export default function TableroCalidad({ tab: tabDeLaUrl, filtros: filtrosUrl, opciones, tablero, accesorios }: Props) {
    const { can } = useCan();
    const filtros = filtrosDeLaUrl(filtrosUrl);
    const [tab, setTab] = useState<TabTablero>(tabDeLaUrl === 'accesorios' ? 'acc' : 'op');
    const [cargando, setCargando] = useState(false);
    const tabs = [...TABS, ...(can('qal.accesorios.ver') ? [TAB_ACCESORIOS] : []), TAB_GLOSARIO];

    /**
     * Los filtros viajan en la URL: un tablero acotado se comparte con su
     * enlace. Cambiar de pestaña sólo pide Accesorios; cambiar un filtro
     * recalcula todo lo de abajo.
     */
    const visitar = (nuevos: FiltrosTablero, pestana: TabTablero, only: string[]) => {
        const params: Record<string, string> = Object.fromEntries(Object.entries(nuevos).filter(([, v]) => v !== ''));
        if (pestana === 'acc') {
            params.tab = 'accesorios';
        }

        router.get('/admin/calidad/dashboard', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only,
            onStart: () => setCargando(true),
            onFinish: () => setCargando(false),
        });
    };

    const filtrar = (nuevos: FiltrosTablero) => visitar(nuevos, tab, ['filtros', 'tablero', 'accesorios']);

    const cambiarTab = (valor: TabTablero) => {
        setTab(valor);
        visitar(filtros, valor, ['tab', 'accesorios']);
    };

    const [periodo, setPeriodo] = useState<'week' | 'month'>('week');
    const [dimension, setDimension] = useState<DimensionRechazo>('obra');
    const [origenPareto, setOrigenPareto] = useState<OrigenPareto>('p2_deftypes');
    const [base, setBase] = useState<BaseTasa>('elem');
    const [dimTasa, setDimTasa] = useState<DimensionTasa>('obra');

    const { resumen, operacion, tasas } = tablero;
    const acotado = resumenFiltros(filtros, opciones);
    const listaAlertas = alertas(resumen);
    const listaHallazgos = hallazgos(resumen, operacion);
    const sinBase = tasasSinBase(tasas);
    const rechazoPor = operacion.rechazoPor[dimension];
    const tasa = tasas[base];
    const { cobertura } = tasa;
    const porDimension = tasa.porDimension[dimTasa];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tablero de Calidad" />

            <div className="space-y-4 p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Tablero de Calidad</h1>
                        <p className="text-base-content/60 text-sm">
                            Cuánto se hizo, cuánto salió bien a la primera y dónde se está yendo el trabajo.
                        </p>
                    </div>
                </div>

                <BarraFiltros valor={filtros} opciones={opciones} onChange={filtrar} />

                <div className={cn('space-y-4 transition-opacity', cargando && 'opacity-60')}>
                    {/* -----------------------------------------------------------
                        RESUMEN EJECUTIVO — cuatro números, lo que pide acción y
                        los hallazgos. Antes eran trece tarjetas de golpe, que no
                        se leen: se ignoran. La evolución del rechazo la cuenta
                        mejor la carta-p de Estadística, y las causas ya están en
                        el Pareto de Operación, acotado por etapa.
                        ----------------------------------------------------------- */}
                    <Panel titulo="Resumen ejecutivo" apunte={acotado}>
                        {resumen.inspecciones.total === 0 && (
                            <div className="mb-3">
                                <NotaCallada>No hay inspecciones capturadas con estos filtros.</NotaCallada>
                            </div>
                        )}

                        <FilaKpis>
                            {kpisEjecutivo(resumen).map((kpi) => (
                                <TarjetaKpi key={kpi.etiqueta} kpi={kpi} />
                            ))}
                        </FilaKpis>

                        {listaAlertas.length > 0 && (
                            <div className="mt-3 space-y-1.5">
                                {listaAlertas.map((alerta) => (
                                    <div
                                        key={alerta}
                                        className="rounded-box border border-warning/40 bg-warning/10 px-3 py-2 text-sm"
                                    >
                                        ⚠️ {alerta}
                                    </div>
                                ))}
                            </div>
                        )}

                        {listaHallazgos.length > 0 && (
                            <div className="rounded-box border-base-300 mt-3 border p-3">
                                <h3 className="mb-2 text-sm font-semibold">Hallazgos</h3>
                                <ul className="space-y-1.5">
                                    {listaHallazgos.map((h) => (
                                        <li key={h} className="text-base-content/80 flex gap-2 text-sm">
                                            <span className="text-base-content/30">—</span>
                                            <span>{h}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </Panel>

                    {/* -----------------------------------------------------------
                        RESUMEN ANALÍTICA — el detalle que sostiene al resumen.
                        ----------------------------------------------------------- */}
                    <Panel titulo="Resumen analítica" apunte="el detalle que sostiene a los cuatro números de arriba">
                        <FilaKpis>
                            {kpisAnalitica(resumen, tasas).map((kpi) => (
                                <TarjetaKpi key={kpi.etiqueta} kpi={kpi} />
                            ))}
                        </FilaKpis>

                        {sinBase.length > 0 && (
                            <div className="mt-3">
                                <NotaCallada>
                                    No se publica la tasa{' '}
                                    {sinBase.map((t) => `${t.nombre} (cobertura ${t.cobertura}%)`).join(' · ')}. Por
                                    debajo del {COBERTURA_MINIMA} % de cobertura el número existe pero no significa
                                    nada: sería dar por medido lo que no se midió.
                                </NotaCallada>
                            </div>
                        )}
                    </Panel>

                    {/* -----------------------------------------------------------
                        TRES LECTORES, TRES TABS: quien pregunta dónde falla, quien
                        pregunta si eso es real, y quien revisa si el formulario
                        sirve.
                        ----------------------------------------------------------- */}
                    <Tabs value={tab} onChange={cambiarTab} tabs={tabs} />

                    {tab === 'op' && (
                        <Rejilla2>
                            <TarjetaGrafica
                                titulo="Resultado final por obra"
                                apunte="piezas"
                                pie="Cómo acabó cada pieza: liberada, rechazada o pendiente. Las pendientes no son un fallo: es trabajo sin cerrar."
                                ancha
                            >
                                <SinDatos hay={operacion.resultadoPorObra.length > 0}>
                                    <ResultadoPorObra datos={operacion.resultadoPorObra} />
                                </SinDatos>
                            </TarjetaGrafica>

                            <TarjetaGrafica
                                titulo="Rechazo por transformación"
                                apunte="1ª corte · 2ª armado y soldado · 3ª pintura"
                                pie="Para ver qué pasa dentro de la 2ª —armado y vestido frente a soldado— usa «Rechazo por» con sub-etapa."
                            >
                                <SinDatos hay={operacion.rechazoPorFase.length > 0}>
                                    <BarrasSimples
                                        datos={operacion.rechazoPorFase.map((f) => ({
                                            nombre: ETIQUETA_FASE[f.fase],
                                            valor: f.pct,
                                            nota: `n=${f.n}`,
                                        }))}
                                    />
                                </SinDatos>
                            </TarjetaGrafica>

                            <TarjetaGrafica
                                titulo="Tendencia de reproceso"
                                controles={
                                    <SelectorTarjeta
                                        etiqueta="Periodo"
                                        value={periodo}
                                        onChange={setPeriodo}
                                        opciones={[
                                            { valor: 'week', texto: 'Por semana' },
                                            { valor: 'month', texto: 'Por mes' },
                                        ]}
                                    />
                                }
                                pie="% de las piezas que empezaron en ese periodo y hubo que retrabajar. Cada pieza cuenta en el periodo de su primera inspección, no en el del retrabajo."
                            >
                                <SinDatos hay={operacion.tendencia[periodo].length > 0}>
                                    <TendenciaLinea datos={operacion.tendencia[periodo]} />
                                </SinDatos>
                            </TarjetaGrafica>

                            <TarjetaGrafica
                                titulo="Rechazo por"
                                controles={
                                    <SelectorTarjeta
                                        etiqueta="Dimensión del rechazo"
                                        value={dimension}
                                        onChange={setDimension}
                                        opciones={[
                                            { valor: 'obra', texto: 'Obra' },
                                            { valor: 'soldador', texto: 'Soldador' },
                                            { valor: 'p2_subetapa', texto: 'Sub-etapa de 2ª' },
                                            { valor: 'tipo', texto: 'Tipo de pieza' },
                                            { valor: 'modulo', texto: 'Módulo' },
                                            { valor: 'inspector', texto: 'Inspector' },
                                            { valor: 'p1_subtipo', texto: 'Perfil/Placa (1ª)' },
                                        ]}
                                    />
                                }
                                pie={notaRechazoPor(dimension, rechazoPor)}
                            >
                                <SinDatos hay={rechazoPor.filas.length > 0}>
                                    <BarrasRanking
                                        datos={rechazoPor.filas.map((d) => ({
                                            nombre: d.nombre,
                                            valor: d.pct,
                                            nota: rechazoPor.bases.length > 1 ? `n=${d.n} · ${d.fase}` : `n=${d.n}`,
                                        }))}
                                        unidad="%"
                                    />
                                </SinDatos>
                            </TarjetaGrafica>

                            <TarjetaGrafica
                                titulo="Pareto de defectos"
                                apunte={acotado}
                                controles={
                                    <SelectorTarjeta
                                        etiqueta="Origen del Pareto"
                                        value={origenPareto}
                                        onChange={setOrigenPareto}
                                        opciones={[
                                            { valor: 'p2_deftypes', texto: 'Soldadura (2ª)' },
                                            { valor: 'armado', texto: 'Armado y vestido (2ª)' },
                                            { valor: 'p3_deftypes', texto: 'Pintura (3ª)' },
                                        ]}
                                    />
                                }
                                pie="Va siempre acotado por obra y etapa. Un Pareto que promedia obras, fases y procesos distintos produce un ranking que no dice dónde actuar."
                            >
                                <SinDatos hay={operacion.pareto[origenPareto].length > 0}>
                                    <Pareto datos={operacion.pareto[origenPareto]} />
                                </SinDatos>
                            </TarjetaGrafica>

                            <TarjetaGrafica
                                titulo="Tasa de defectos"
                                apunte={ETIQUETA_BASE[base]}
                                controles={
                                    <>
                                        <SelectorTarjeta
                                            etiqueta="Base de la tasa"
                                            value={base}
                                            onChange={setBase}
                                            opciones={[
                                                { valor: 'elem', texto: 'por elemento (2ª)' },
                                                { valor: 'ton', texto: 'por tonelada (2ª)' },
                                                { valor: 'm2', texto: 'por m² (3ª)' },
                                            ]}
                                        />
                                        <SelectorTarjeta
                                            etiqueta="Dimensión de la tasa"
                                            value={dimTasa}
                                            onChange={setDimTasa}
                                            opciones={[
                                                { valor: 'obra', texto: 'Obra' },
                                                { valor: 'soldador', texto: 'Soldador' },
                                                { valor: 'tipo', texto: 'Tipo de pieza' },
                                                { valor: 'modulo', texto: 'Módulo' },
                                                { valor: 'inspector', texto: 'Inspector' },
                                            ]}
                                        />
                                    </>
                                }
                                pie={
                                    tasaPublicable(tasa)
                                        ? `${num(tasa.defectos)} defectos en la 1ª inspección · cobertura ${cobertura.pct}% (${num(cobertura.con)} de ${num(cobertura.total)} piezas).` +
                                          (cobertura.fuera > 0
                                              ? ` ${num(cobertura.fuera)} captura(s) fuera por una medida imposible: revisar el registro.`
                                              : '')
                                        : undefined
                                }
                            >
                                {tasaPublicable(tasa) && porDimension.length > 0 ? (
                                    <BarrasRanking
                                        datos={porDimension.map((t) => ({
                                            nombre: t.nombre,
                                            valor: t.tasa,
                                            nota: base === 'ton' ? `${t.exposicion.toFixed(1)} t` : num(Math.round(t.exposicion)),
                                        }))}
                                        decimales={base === 'elem' ? 3 : 2}
                                    />
                                ) : cobertura.total === 0 ? (
                                    <NotaCallada>
                                        No hay piezas {base === 'm2' ? 'de pintura' : 'de 2ª en soldado'} con estos
                                        filtros.
                                    </NotaCallada>
                                ) : (
                                    <NotaCallada>
                                        Sin base suficiente: sólo {num(cobertura.con)} de {num(cobertura.total)} piezas
                                        tienen {base === 'm2' ? 'área pintada' : base === 'elem' ? 'elementos' : 'peso'}{' '}
                                        capturado ({cobertura.pct ?? 0} %). La tasa se calcularía, pero estaría midiendo
                                        una muestra que no representa a la obra.
                                    </NotaCallada>
                                )}
                            </TarjetaGrafica>
                        </Rejilla2>
                    )}

                    {tab === 'est' && <TabEstadistica datos={tablero.estadistica} />}

                    {tab === 'diag' && <TabDiagnostico datos={tablero.diagnostico} />}

                    {tab === 'glo' && <TabGlosario />}

                    {tab === 'acc' &&
                        (accesorios ? (
                            <TabAccesorios
                                datos={accesorios}
                                onObra={(obra) =>
                                    visitar({ ...filtros, obra: obra ? String(obra) : '' }, 'acc', [
                                        'filtros',
                                        'tablero',
                                        'accesorios',
                                    ])
                                }
                            />
                        ) : (
                            <div className="skeleton h-40 w-full" />
                        ))}
                </div>
            </div>
        </AppLayout>
    );
}
