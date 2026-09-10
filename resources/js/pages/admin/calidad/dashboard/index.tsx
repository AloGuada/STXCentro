/**
 * Tablero de Calidad — el `Dashboard_Calidad_Steelex.html` de la aplicación
 * anterior, reestructurado.
 *
 * El original eran siete pestañas de primer nivel (Resumen ejecutivo, Analítica,
 * PND, Accesorios, Reporte semanal, Análisis, Glosario) y, dentro de Analítica,
 * otras tres. Aquí las dos primeras se aplanan en **una sola página**:
 *
 *     barra de filtros  →  resumen ejecutivo  →  resumen analítica
 *                       →  tabs: Operación · Estadística · Diagnóstico
 *
 * PND ya es pantalla propia en el mono (`/admin/calidad/pnd`), así que no se
 * duplica aquí. Accesorios sí vive aquí, como cuarta pestaña: es la única que
 * ya lee datos de verdad, y se pide al servidor al abrirla (`?tab=accesorios`).
 *
 * «Resultado final por obra» bajó del resumen ejecutivo a Operación: es una
 * respuesta a *dónde* falla, no uno de los números con los que se abre.
 *
 * Todavía no lee nada: las tablas de inspección no existen y los números salen
 * de `components/qal/dashboard/datos.ts`, que está marcado como falso. Los
 * filtros son controles de verdad pero **aún no recalculan**.
 */

import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { TabAccesorios, type DatosAccesorios } from '@/components/qal/dashboard/accesorios';
import {
    ALERTAS,
    COBERTURA_BASE,
    ETIQUETA_BASE,
    HALLAZGOS,
    KPIS_ANALITICA,
    KPIS_EJECUTIVO,
    PARETO,
    RECHAZO_POR_DIMENSION,
    RECHAZO_POR_FASE,
    RESULTADO_POR_OBRA,
    TASAS_SIN_BASE,
    TASA_DEFECTOS,
    TENDENCIA_REPROCESO,
    type BaseTasa,
    type DimensionRechazo,
    type DimensionTasa,
    type OrigenPareto,
} from '@/components/qal/dashboard/datos';
import { TabDiagnostico } from '@/components/qal/dashboard/diagnostico';
import { TabEstadistica } from '@/components/qal/dashboard/estadistica';
import { BarraFiltros, FILTROS_VACIOS, resumenFiltros, type FiltrosTablero } from '@/components/qal/dashboard/filtros';
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
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Calidad', href: '/admin/calidad/catalogos' },
    { title: 'Tablero', href: '/admin/calidad/dashboard' },
];

type TabTablero = 'op' | 'est' | 'diag' | 'acc';

const TABS: Tab<TabTablero>[] = [
    { valor: 'op', titulo: '🏭 Operación', nota: 'dónde falla' },
    { valor: 'est', titulo: '📐 Estadística', nota: '¿es real?' },
    { valor: 'diag', titulo: '🔎 Diagnóstico', nota: '¿el dato sirve?' },
];

const TAB_ACCESORIOS: Tab<TabTablero> = { valor: 'acc', titulo: '📦 Accesorios', nota: '¿cuánto se liberó?' };

type Props = {
    /** La pestaña que pidió la URL; sólo Accesorios se recuerda. */
    tab: 'accesorios' | null;
    /** Los lotes, sólo con la pestaña Accesorios abierta y con permiso de verlos. */
    accesorios: DatosAccesorios | null;
};

export default function TableroCalidad({ tab: tabDeLaUrl, accesorios }: Props) {
    const { can } = useCan();
    const [filtros, setFiltros] = useState<FiltrosTablero>(FILTROS_VACIOS);
    const [tab, setTab] = useState<TabTablero>(tabDeLaUrl === 'accesorios' ? 'acc' : 'op');
    const tabs = can('qal.accesorios.ver') ? [...TABS, TAB_ACCESORIOS] : TABS;

    /**
     * Accesorios es la única pestaña con datos del servidor: se piden al
     * abrirla, y la URL la recuerda para que volver de corregir un sublote
     * caiga en ella.
     */
    const visitar = (params: Record<string, string>) =>
        router.get('/admin/calidad/dashboard', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['tab', 'accesorios'],
        });

    const cambiarTab = (valor: TabTablero) => {
        setTab(valor);
        visitar(valor === 'acc' ? { tab: 'accesorios' } : {});
    };

    const [periodo, setPeriodo] = useState<'week' | 'month'>('week');
    const [dimension, setDimension] = useState<DimensionRechazo>('obra');
    const [origenPareto, setOrigenPareto] = useState<OrigenPareto>('p2_deftypes');
    const [base, setBase] = useState<BaseTasa>('elem');
    const [dimTasa, setDimTasa] = useState<DimensionTasa>('obra');

    const acotado = resumenFiltros(filtros);
    const cobertura = COBERTURA_BASE[base];
    const tasas = TASA_DEFECTOS[base][dimTasa];

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
                    <span className="badge badge-warning badge-sm">Maqueta · datos de ejemplo</span>
                </div>

                <BarraFiltros valor={filtros} onChange={setFiltros} />

                {/* ---------------------------------------------------------------
                    RESUMEN EJECUTIVO — cuatro números, lo que pide acción y los
                    hallazgos. Antes eran trece tarjetas de golpe, que no se leen:
                    se ignoran. Las dos gráficas que había aquí —evolución del
                    rechazo y causas— se quitaron: la evolución la cuenta mejor la
                    carta-p de Estadística, con sus límites de control, y las
                    causas ya están en el Pareto de Operación, acotado por etapa.
                    --------------------------------------------------------------- */}
                <Panel titulo="Resumen ejecutivo" apunte={acotado}>
                    <FilaKpis>
                        {KPIS_EJECUTIVO.map((kpi) => (
                            <TarjetaKpi key={kpi.etiqueta} kpi={kpi} />
                        ))}
                    </FilaKpis>

                    {ALERTAS.length > 0 && (
                        <div className="mt-3 space-y-1.5">
                            {ALERTAS.map((alerta) => (
                                <div
                                    key={alerta.texto}
                                    className="rounded-box border border-warning/40 bg-warning/10 px-3 py-2 text-sm"
                                >
                                    ⚠️ {alerta.texto}
                                </div>
                            ))}
                        </div>
                    )}

                    <div className="rounded-box border-base-300 mt-3 border p-3">
                        <h3 className="mb-2 text-sm font-semibold">Hallazgos</h3>
                        <ul className="space-y-1.5">
                            {HALLAZGOS.map((h) => (
                                <li key={h} className="text-base-content/80 flex gap-2 text-sm">
                                    <span className="text-base-content/30">—</span>
                                    <span>{h}</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                </Panel>

                {/* ---------------------------------------------------------------
                    RESUMEN ANALÍTICA — el detalle que sostiene al resumen.
                    --------------------------------------------------------------- */}
                <Panel
                    titulo="Resumen analítica"
                    apunte="el detalle que sostiene a los cuatro números de arriba"
                >
                    <FilaKpis>
                        {KPIS_ANALITICA.map((kpi) => (
                            <TarjetaKpi key={kpi.etiqueta} kpi={kpi} />
                        ))}
                    </FilaKpis>

                    {TASAS_SIN_BASE.length > 0 && (
                        <div className="mt-3">
                            <NotaCallada>
                                No se publica la tasa{' '}
                                {TASAS_SIN_BASE.map((t) => `${t.nombre} (cobertura ${t.cobertura}%)`).join(' · ')}. Por
                                debajo del 30 % de cobertura el número existe pero no significa nada: sería dar por
                                medido lo que no se midió.
                            </NotaCallada>
                        </div>
                    )}
                </Panel>

                {/* ---------------------------------------------------------------
                    TRES LECTORES, TRES TABS: quien pregunta dónde falla, quien
                    pregunta si eso es real, y quien revisa si el formulario sirve.
                    --------------------------------------------------------------- */}
                <Tabs value={tab} onChange={cambiarTab} tabs={tabs} />

                {tab === 'op' && (
                    <Rejilla2>
                        <TarjetaGrafica
                            titulo="Resultado final por obra"
                            apunte="piezas"
                            pie="Liberadas, rechazadas y pendientes de cada obra. Las pendientes no son un fallo: es trabajo sin cerrar."
                            ancha
                        >
                            <ResultadoPorObra datos={RESULTADO_POR_OBRA} />
                        </TarjetaGrafica>

                        <TarjetaGrafica
                            titulo="Rechazo por transformación"
                            apunte="1ª corte · 2ª armado y soldado · 3ª pintura"
                            pie="Para ver qué pasa dentro de la 2ª —armado y vestido frente a soldado— usa «Rechazo por» con sub-etapa."
                        >
                            <BarrasSimples
                                datos={RECHAZO_POR_FASE.map((f) => ({
                                    nombre: f.fase,
                                    valor: f.pct,
                                    nota: `n=${f.n}`,
                                }))}
                            />
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
                            pie="% de piezas que hubo que volver a inspeccionar tras un rechazo."
                        >
                            <TendenciaLinea datos={TENDENCIA_REPROCESO[periodo]} />
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
                            pie="% de piezas con al menos un rechazo. Una pieza rechazada tres veces es una pieza mala, no tres."
                        >
                            <BarrasRanking
                                datos={RECHAZO_POR_DIMENSION[dimension].map((d) => ({
                                    nombre: d.nombre,
                                    valor: d.pct,
                                    nota: `n=${d.n}`,
                                }))}
                                unidad="%"
                            />
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
                            <Pareto datos={PARETO[origenPareto]} />
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
                                cobertura.pct >= 30
                                    ? `1ª inspección · cobertura ${cobertura.pct}% (${cobertura.con} de ${cobertura.total} piezas).`
                                    : undefined
                            }
                        >
                            {cobertura.pct >= 30 && tasas.length > 0 ? (
                                <BarrasRanking
                                    datos={tasas.map((t) => ({ nombre: t.nombre, valor: t.tasa }))}
                                    decimales={base === 'elem' ? 3 : 2}
                                />
                            ) : (
                                <NotaCallada>
                                    Sin base suficiente: sólo {cobertura.con} de {cobertura.total} piezas tienen{' '}
                                    {base === 'm2' ? 'área pintada' : 'la base'} capturada ({cobertura.pct} %). La tasa
                                    se calcularía, pero estaría midiendo una muestra que no representa a la obra.
                                </NotaCallada>
                            )}
                        </TarjetaGrafica>
                    </Rejilla2>
                )}

                {tab === 'est' && <TabEstadistica />}

                {tab === 'diag' && <TabDiagnostico />}

                {tab === 'acc' &&
                    (accesorios ? (
                        <TabAccesorios
                            datos={accesorios}
                            onObra={(obra) => visitar(obra ? { tab: 'accesorios', obra } : { tab: 'accesorios' })}
                        />
                    ) : (
                        <div className="skeleton h-40 w-full" />
                    ))}
            </div>
        </AppLayout>
    );
}
