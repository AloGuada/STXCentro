/**
 * Pestaña **Diagnóstico** del tablero: ¿el dato sirve?
 *
 * Las otras dos pestañas miden el taller. Ésta mide **la captura**: si los
 * campos que se le piden al inspector detectan algo, dónde ha dolido más hasta
 * ahora, qué categoría se sale del promedio de su etapa y qué le sale mal a cada
 * quien. Es la pestaña que dice cuánto se puede creer del resto del tablero.
 *
 * Tres cosas que se conservan del rediseño y no se pueden ablandar:
 *
 *  - **Una casilla en blanco no cuenta como OK.** Se comprobó en la base: las
 *    piezas rechazadas dejan más casillas vacías que las liberadas. El blanco es
 *    «no se llenó», no «lo vi y estaba bien», y por eso no entra en ningún
 *    denominador.
 *  - **Los cortes del mapa de riesgo son absolutos**, no relativos al defecto
 *    más frecuente: si no, el primero define la escala y los cinco salen «Alta».
 *  - **Asociación, no causa.** Los factores de riesgo dicen dónde mirar primero;
 *    nadie ha controlado qué piezas le tocaron a cada quien.
 */

import { useState } from 'react';
import {
    FACTORES_RIESGO,
    MAPAS_RIESGO,
    MIN_PIEZAS_CAMPO,
    MIN_PIEZAS_OTRO,
    MIN_PIEZAS_PERSONA,
    PERFIL_DEFECTOS,
    USO_CAMPOS,
    type CampoFormulario,
    type DimensionPerfil,
    type MapaRiesgo,
} from '@/components/qal/dashboard/datos';
import {
    BarraCampo,
    Etiqueta,
    NotaCallada,
    Rejilla2,
    SelectorTarjeta,
    Tabla,
    TarjetaGrafica,
    type Tono,
} from '@/components/qal/dashboard/ui';
import { num, pct } from '@/components/qal/paleta';
import { cn } from '@/lib/utils';

// ---------------------------------------------------------------------------
// Uso de los campos del formulario
// ---------------------------------------------------------------------------

type EstadoCampo = 'senal' | 'poco' | 'sin' | 'nunca' | 'nodatos';

const ESTADO: Record<EstadoCampo, { texto: string; tono: Tono }> = {
    // Nombres en cristiano: cada etiqueta dice qué hacer con el campo. «Señal»
    // no significaba nada para quien lee el tablero.
    senal: { texto: 'detecta defectos', tono: 'ok' },
    poco: { texto: 'se responde a medias', tono: 'alerta' },
    sin: { texto: 'siempre sale OK', tono: 'neutro' },
    nunca: { texto: 'sin una sola respuesta', tono: 'malo' },
    nodatos: { texto: 'sin muestra', tono: 'neutro' },
};

type CampoCalculado = CampoFormulario & {
    respondido: number;
    aplica: number;
    pctResp: number;
    pctDef: number;
    estado: EstadoCampo;
};

function calcular(campo: CampoFormulario): CampoCalculado {
    const respondido = campo.ok + campo.defecto + campo.noAplica;
    const aplica = campo.ok + campo.defecto;
    const pctResp = campo.n > 0 ? (respondido / campo.n) * 100 : 0;
    const pctDef = aplica > 0 ? (campo.defecto / aplica) * 100 : 0;

    // Con una sola pieza de esa etapa no se puede decir que un campo «no se usa
    // nunca»: los doce campos de 1ª salían marcados así porque hay un puñado de
    // registros de corte en toda la base.
    const estado: EstadoCampo =
        campo.n < MIN_PIEZAS_CAMPO
            ? 'nodatos'
            : respondido === 0
              ? 'nunca'
              : pctResp < 50
                ? 'poco'
                : pctDef >= 5
                  ? 'senal'
                  : 'sin';

    return { ...campo, respondido, aplica, pctResp, pctDef, estado };
}

function UsoCampos() {
    const [fase, setFase] = useState<'todas' | '1ª' | '2ª' | '3ª'>('todas');
    const [estado, setEstado] = useState<EstadoCampo | ''>('');

    const todos = USO_CAMPOS.map(calcular);
    const deLaFase = todos.filter((c) => fase === 'todas' || c.fase === fase);
    const filas = deLaFase.filter((c) => !estado || c.estado === estado);

    const bloques = filas.reduce<Record<string, CampoCalculado[]>>((acumulado, campo) => {
        const clave = `${campo.fase} · ${campo.bloque}`;
        (acumulado[clave] ??= []).push(campo);
        return acumulado;
    }, {});

    return (
        <TarjetaGrafica
            titulo="Uso de los campos del formulario"
            apunte="qué se responde y qué no"
            controles={
                <SelectorTarjeta
                    etiqueta="Etapa del formulario"
                    value={fase}
                    onChange={setFase}
                    opciones={[
                        { valor: 'todas', texto: 'Todas las etapas' },
                        { valor: '1ª', texto: '1ª · Corte' },
                        { valor: '2ª', texto: '2ª · Armado y soldado' },
                        { valor: '3ª', texto: '3ª · Pintura' },
                    ]}
                />
            }
            pie={
                <>
                    El <b>% con defecto</b> se calcula sobre las piezas donde el campo <b>aplicaba</b> (OK + defecto),
                    no sobre todas las respuestas: incluir los «no aplica» diluye la señal.
                    <br />
                    <b>Detecta defectos</b> = se responde y al menos 1 de cada 20 veces sale mal: el campo está
                    haciendo su trabajo. <b>Se responde a medias</b> = se contesta en menos de la mitad de las piezas: o
                    estorba, o no se entiende, o no aplica tan seguido como creíamos. <b>Siempre sale OK</b> = se
                    contesta pero nunca sale mal: no ayuda a decidir, aunque valga como evidencia ante el cliente.{' '}
                    <b>Sin una sola respuesta</b> = candidato a salir del formulario. <b>Sin muestra</b> = esa etapa
                    tiene menos de {MIN_PIEZAS_CAMPO} piezas registradas.
                    <br />
                    <b>Una casilla en blanco no cuenta como OK.</b> Se comprobó en la base: las piezas rechazadas dejan
                    más casillas vacías que las liberadas. El blanco es «no se llenó», no «lo vi y estaba bien»; por eso
                    no entra en ningún denominador.
                </>
            }
            ancha
        >
            <div className="mb-3 flex flex-wrap items-center gap-2">
                <b className="text-sm">{deLaFase.length} campos</b>
                {(['senal', 'poco', 'sin', 'nunca', 'nodatos'] as EstadoCampo[]).map((e) => {
                    const cuantos = deLaFase.filter((c) => c.estado === e).length;
                    if (cuantos === 0) {
                        return null;
                    }
                    return (
                        <button
                            key={e}
                            type="button"
                            onClick={() => setEstado(estado === e ? '' : e)}
                            aria-pressed={estado === e}
                            className={cn('cursor-pointer', estado === e && 'ring-primary rounded-full ring-2')}
                        >
                            <Etiqueta texto={`${cuantos} ${ESTADO[e].texto}`} tono={ESTADO[e].tono} />
                        </button>
                    );
                })}
                {estado && (
                    <button
                        type="button"
                        onClick={() => setEstado('')}
                        className="text-primary ml-auto text-xs underline"
                    >
                        quitar filtro
                    </button>
                )}
            </div>

            {filas.length === 0 ? (
                <NotaCallada>Ningún campo cumple ese filtro.</NotaCallada>
            ) : (
                <div className="space-y-3">
                    {Object.keys(bloques)
                        .sort()
                        .map((clave) => {
                            const grupo = bloques[clave]
                                .slice()
                                .sort((a, b) => b.pctDef - a.pctDef || b.pctResp - a.pctResp);
                            const respondido = grupo.reduce((a, c) => a + c.pctResp, 0) / grupo.length;
                            const conSenal = grupo.filter((c) => c.estado === 'senal').length;

                            return (
                                <div key={clave} className="rounded-box border-base-300 border">
                                    <div className="border-base-300 bg-base-200/40 flex flex-wrap items-center gap-2 border-b px-3 py-2">
                                        <b className="text-sm">{clave}</b>
                                        <span className="text-base-content/50 text-xs">
                                            {grupo.length} campo(s) · {Math.round(respondido)} % respondido
                                            {conSenal > 0 && ` · ${conSenal} detecta(n) defectos`}
                                        </span>
                                    </div>
                                    <Tabla
                                        columnas={[
                                            'Campo',
                                            'Piezas',
                                            '% respondido',
                                            '% con defecto',
                                            'Distribución',
                                            'Estado',
                                        ]}
                                        filas={grupo.map((c) => [
                                            <b key="c">{c.campo}</b>,
                                            num(c.n),
                                            <span key="r" className={c.pctResp < 50 ? 'text-error font-bold' : ''}>
                                                {Math.round(c.pctResp)} %
                                            </span>,
                                            c.aplica > 0 ? (
                                                <span key="d">
                                                    <span className={c.pctResp < 50 ? 'text-base-content/50' : 'font-bold'}>
                                                        {c.pctDef.toFixed(1)} %
                                                    </span>{' '}
                                                    <span className="text-base-content/50 text-xs">
                                                        {c.defecto}/{c.aplica}
                                                    </span>
                                                    {c.pctResp < 50 && (
                                                        <span
                                                            className="text-base-content/50"
                                                            title="El campo se responde en menos de la mitad de las piezas: con tantos blancos, esta tasa es orientativa"
                                                        >
                                                            {' '}
                                                            ≈
                                                        </span>
                                                    )}
                                                </span>
                                            ) : (
                                                <span key="d" className="text-base-content/40">
                                                    n/a
                                                </span>
                                            ),
                                            <BarraCampo
                                                key="b"
                                                ok={c.ok}
                                                defecto={c.defecto}
                                                noAplica={c.noAplica}
                                                vacio={c.vacio}
                                                total={c.n}
                                            />,
                                            <Etiqueta
                                                key="e"
                                                texto={ESTADO[c.estado].texto}
                                                tono={ESTADO[c.estado].tono}
                                            />,
                                        ])}
                                    />
                                </div>
                            );
                        })}
                </div>
            )}

            <p className="text-base-content/50 mt-2 text-xs">
                <span className="bg-success mr-1 inline-block size-2 rounded-[2px]" /> OK ·{' '}
                <span className="bg-error mr-1 inline-block size-2 rounded-[2px]" /> con defecto ·{' '}
                <span className="bg-base-content/25 mr-1 inline-block size-2 rounded-[2px]" /> no aplica ·{' '}
                <span className="bg-base-300 mr-1 inline-block size-2 rounded-[2px]" /> sin contestar. Pasa el ratón por
                la barra para ver los números.
            </p>
        </TarjetaGrafica>
    );
}

// ---------------------------------------------------------------------------
// Mapa de riesgo
// ---------------------------------------------------------------------------

/** Cortes absolutos sobre el total, no relativos al defecto más frecuente. */
function nivel(valor: number): 0 | 1 | 2 {
    return valor >= 25 ? 2 : valor >= 10 ? 1 : 0;
}

const FONDO: string[][] = [
    ['bg-success/10', 'bg-warning/10', 'bg-error/10'],
    ['bg-warning/10', 'bg-warning/20', 'bg-error/15'],
    ['bg-error/10', 'bg-error/15', 'bg-error/25'],
];

const NIVEL_Y = ['Baja', 'Media', 'Alta'];
const NIVEL_X = ['Bajo', 'Medio', 'Alto'];

function MapaDeRiesgo({ mapa }: { mapa: MapaRiesgo }) {
    // Cada celda guarda los números de los defectos que caen en ella.
    const celdas: Record<string, number[]> = {};
    mapa.items.forEach((item, i) => {
        const clave = `${nivel(item.frecuencia)}-${nivel(item.impacto)}`;
        (celdas[clave] ??= []).push(i + 1);
    });

    const rango =
        Math.max(...mapa.items.map((i) => i.frecuencia)) - Math.min(...mapa.items.map((i) => i.frecuencia));

    return (
        <TarjetaGrafica
            titulo={`Mapa de riesgo · ${mapa.etapa}`}
            apunte={mapa.nota}
            pie={
                <>
                    Cruza la <b>frecuencia</b> de cada defecto con{' '}
                    {mapa.usaMagnitud ? (
                        <>
                            {mapa.unidadMagnitud === 'm²' ? (
                                <>la <b>superficie pintada</b></>
                            ) : (
                                <>el <b>peso de material</b></>
                            )}{' '}
                            que compromete
                        </>
                    ) : (
                        <>
                            el <b>número de piezas</b> afectadas
                        </>
                    )}
                    . Es histórico, no predicción: muestra dónde ha dolido más hasta ahora.
                    {!mapa.usaMagnitud && (
                        <>
                            {' '}
                            <b className="text-error">
                                El eje de impacto no usa {mapa.unidadMagnitud}: sólo el {mapa.coberturaMagnitud} % de
                                las piezas con defecto tienen ese dato capturado
                            </b>
                            , y un impacto de 0 por falta de dato se leería como «no impacta».
                        </>
                    )}
                    {(mapa.items.length < 3 || rango < 5) && (
                        <>
                            {' '}
                            <b>Con los datos actuales todavía hay poca diferencia entre defectos</b>, así que el mapa aún
                            no separa bien; ganará valor conforme entren más registros.
                        </>
                    )}
                    <br />
                    Los cortes son fijos: <b>baja</b> por debajo del 10 % del total, <b>media</b> hasta el 25 %,{' '}
                    <b>alta</b> por encima. Los porcentajes de impacto <b>no suman 100</b>: una pieza con tres defectos
                    aporta sus {mapa.unidadMagnitud} a los tres, así que cada uno se lee como «qué parte del material
                    con defecto lleva este defecto», no como un reparto.
                </>
            }
        >
            <div className="text-base-content/50 mb-1.5 text-[11px] font-bold uppercase">
                Frecuencia · qué tan seguido aparece
            </div>

            <div className="grid grid-cols-[auto_repeat(3,1fr)] gap-1">
                {[2, 1, 0].map((y) => (
                    <div key={y} className="contents">
                        <div className="text-base-content/50 flex items-center pr-1 text-[11px] font-semibold">
                            {NIVEL_Y[y]}
                        </div>
                        {[0, 1, 2].map((x) => (
                            <div
                                key={x}
                                className={cn(
                                    'flex min-h-14 flex-wrap items-center justify-center gap-1 rounded-md p-1.5',
                                    FONDO[y][x],
                                )}
                            >
                                {(celdas[`${y}-${x}`] ?? []).map((n) => (
                                    <span
                                        key={n}
                                        className="bg-base-100 border-base-300 grid size-6 place-items-center rounded-full border text-xs font-bold"
                                    >
                                        {n}
                                    </span>
                                ))}
                            </div>
                        ))}
                    </div>
                ))}
                <div />
                {NIVEL_X.map((t) => (
                    <div key={t} className="text-base-content/50 text-center text-[11px] font-semibold">
                        {t}
                    </div>
                ))}
            </div>

            <div className="text-base-content/50 mt-1.5 text-center text-[11px] font-bold uppercase">
                Impacto · {mapa.usaMagnitud ? mapa.magnitudLarga : 'piezas afectadas'}
            </div>

            <ul className="divide-base-300 mt-3 divide-y">
                {mapa.items.map((item, i) => (
                    <li key={item.defecto} className="flex items-start gap-2 py-1.5">
                        <span className="bg-base-100 border-base-300 mt-0.5 grid size-5 shrink-0 place-items-center rounded-full border text-[11px] font-bold">
                            {i + 1}
                        </span>
                        <div className="text-sm">
                            <b>{item.defecto}</b>
                            <div className="text-base-content/50 text-xs">
                                Frecuencia: {item.defectos} defectos ({Math.round(item.frecuencia)} % del total) ·
                                Impacto:{' '}
                                {mapa.usaMagnitud
                                    ? `${num(item.magnitud)} ${mapa.unidadMagnitud} · ${item.impacto} % de ${
                                          mapa.unidadMagnitud === 'm²' ? 'la superficie' : 'los kilos'
                                      } con defecto`
                                    : `${item.piezas} de ${num(mapa.piezasEtapa)} piezas de la etapa · ${item.impacto} %`}
                            </div>
                        </div>
                    </li>
                ))}
            </ul>
        </TarjetaGrafica>
    );
}

// ---------------------------------------------------------------------------
// Factores de riesgo y perfil de defectos
// ---------------------------------------------------------------------------

const EVIDENCIA: Record<'confirmado' | 'indicio' | 'nada', { texto: string; tono: Tono }> = {
    confirmado: { texto: 'confirmado', tono: 'ok' },
    indicio: { texto: 'indicio', tono: 'alerta' },
    nada: { texto: 'puede ser azar', tono: 'neutro' },
};

function FactoresRiesgo() {
    const confirmados = FACTORES_RIESGO.filter((f) => f.evidencia === 'confirmado').length;

    return (
        <TarjetaGrafica
            titulo="Factores de riesgo"
            apunte={`dónde mirar primero · ${confirmados} confirmado(s) de ${FACTORES_RIESGO.length} señales`}
            pie={
                <>
                    <b>«2.0×»</b> = esa categoría rechaza el doble que el promedio de su transformación. Cada categoría
                    se compara con <b>su propia etapa</b>, no con el total: en pintura casi no se rechaza al cierre, así
                    que mezclarlas hacía que cualquiera de 2ª pareciera un problema.
                    <br />
                    <b>¿Es real?</b> Como se miran muchas categorías a la vez, alguna saldría alta por casualidad: por
                    eso <b>confirmado</b> exige un listón más duro (0,05 dividido entre el número de comparaciones) e{' '}
                    <b>indicio</b> es el p &lt; 0,05 de toda la vida. «Puede ser azar» no significa que esté bien:
                    significa que con estas piezas todavía no se puede afirmar.
                    <br />
                    Una pieza cuenta como rechazada si lo fue <b>alguna vez</b> —la misma definición que la portada—, no
                    sólo si acabó mal: por eso los porcentajes son más altos que en el chi² de Estadística.
                    <br />
                    <b>Asociación, no causa.</b> Sirve para decidir dónde mirar primero, no para atribuir culpas: nadie
                    ha controlado qué piezas le tocaron a cada quien, y las difíciles no se reparten al azar.
                </>
            }
            ancha
        >
            <p className="text-base-content/60 mb-3 text-xs">
                Ordena todas las categorías de todos los factores por <b>cuánto más (o menos) se rechaza</b> que el
                promedio <b>de su propia transformación</b>. El chi² de Estadística dice si un factor influye; esta
                tabla dice <b>cuál</b> es el caso concreto y por dónde empezar.
            </p>

            <Tabla
                columnas={['Etapa', 'Factor', 'Valor', 'Piezas', 'Rechazo', 'vs su etapa', '¿Es real?']}
                atenuadas={FACTORES_RIESGO.map((f) => f.evidencia === 'nada')}
                filas={FACTORES_RIESGO.map((f) => {
                    const malo = f.rr >= 1.3;

                    return [
                        <span key="f" className="text-base-content/50 text-xs">
                            {f.fase}
                        </span>,
                        f.factor,
                        <b key="v">{f.valor}</b>,
                        <span key="n">
                            {num(f.piezas)}{' '}
                            <span className="text-base-content/50 text-xs">({f.rechazadas} rech.)</span>
                        </span>,
                        <span key="t">
                            {pct(f.tasa, 0)}{' '}
                            <span className="text-base-content/50 text-xs">vs {pct(f.base, 0)}</span>
                        </span>,
                        <b key="rr" className={malo ? 'text-error' : 'text-success'}>
                            {f.rr.toFixed(1)}×
                        </b>,
                        <span key="e" className="inline-flex items-center gap-1.5">
                            <Etiqueta texto={EVIDENCIA[f.evidencia].texto} tono={EVIDENCIA[f.evidencia].tono} />
                            <span className="text-base-content/50 text-xs">
                                p={f.p < 0.001 ? '<0.001' : f.p.toFixed(3)}
                            </span>
                        </span>,
                    ];
                })}
            />
        </TarjetaGrafica>
    );
}

const ETIQUETA_PERFIL: Record<DimensionPerfil, string> = {
    soldador: 'Soldador',
    inspector: 'Inspector',
    obra: 'Obra',
    modulo: 'Módulo',
    tipo: 'Tipo de pieza',
};

function PerfilDefectos() {
    const [dimension, setDimension] = useState<DimensionPerfil>('soldador');
    const filas = PERFIL_DEFECTOS[dimension];
    const esPersona = dimension === 'soldador' || dimension === 'inspector';
    const minimo = esPersona ? MIN_PIEZAS_PERSONA : MIN_PIEZAS_OTRO;

    return (
        <TarjetaGrafica
            titulo="Perfil de defectos"
            apunte="¿con qué defecto tiene problemas cada uno?"
            controles={
                <SelectorTarjeta
                    etiqueta="Dimensión del perfil"
                    value={dimension}
                    onChange={setDimension}
                    opciones={[
                        { valor: 'soldador', texto: 'Por soldador' },
                        { valor: 'inspector', texto: 'Por inspector' },
                        { valor: 'obra', texto: 'Por obra' },
                        { valor: 'modulo', texto: 'Por módulo' },
                        { valor: 'tipo', texto: 'Por tipo de pieza' },
                    ]}
                />
            }
            pie={
                <>
                    <b>Todo se compara dentro de la misma transformación.</b> Los defectos de corte, soldadura y pintura
                    no son comparables entre sí, y cada etapa tiene su propia mezcla de referencia: un socavado se mide
                    contra el resto de defectos de 2ª, no contra los de pintura.
                    <br />
                    El <b>defecto característico</b> es aquel en el que esa categoría se desvía más de la mezcla de su
                    etapa (mínimo 3 casos; «Otro» no cuenta, es el cajón de sastre). Sirve para dar formación o ajuste
                    específico, no para comparar personas. Las tasas se publican a partir de {minimo} piezas
                    {esPersona && ' —el mínimo es más alto cuando la fila lleva el nombre de una persona—'}.
                    {dimension === 'inspector' && (
                        <>
                            {' '}
                            <b>Ojo con inspector:</b> una diferencia marcada puede reflejar <b>criterio de inspección</b>{' '}
                            distinto, no mejor o peor trabajo.
                        </>
                    )}
                    <br />
                    <b>No mide lo mismo que las dos tarjetas anteriores.</b> El chi² y los factores de riesgo preguntan{' '}
                    <i>quién rechaza más</i>; esto pregunta <i>qué le sale mal a cada uno</i>. Dos soldadores con el
                    mismo 30 % de rechazo pueden tener problemas distintos —uno socavado, otro falta de remate— y eso
                    cambia por completo qué se hace al respecto.
                </>
            }
            ancha
        >
            <Tabla
                columnas={[
                    'Etapa',
                    ETIQUETA_PERFIL[dimension],
                    'Piezas',
                    'Defectos',
                    'Def./pieza',
                    'Rechazo',
                    'Sus 3 defectos principales',
                    'Defecto característico',
                ]}
                filas={filas.map((f) => [
                    <span key="f" className="text-base-content/50 text-xs">
                        {f.fase}
                    </span>,
                    <b key="k">{f.nombre}</b>,
                    <span key="p">
                        {num(f.piezas)}
                        {f.inspecciones !== f.piezas && (
                            <span className="text-base-content/50 text-xs"> · {f.inspecciones} insp.</span>
                        )}
                    </span>,
                    num(f.defectos),
                    f.defPorPieza.toFixed(2),
                    f.rechazo === null ? (
                        <span
                            key="r"
                            className="text-base-content/40 text-xs"
                            title={`hacen falta ${minimo} piezas para publicar una tasa`}
                        >
                            n&lt;{minimo}
                        </span>
                    ) : (
                        pct(f.rechazo, 0)
                    ),
                    <span key="t" className="flex flex-wrap justify-end gap-1">
                        {f.top.length > 0 ? (
                            f.top.map((t) => (
                                <span
                                    key={t.defecto}
                                    className="bg-base-200 rounded-full px-2 py-0.5 text-xs whitespace-nowrap"
                                >
                                    <b>{t.defecto}</b> {t.n} ({Math.round(t.share)} %)
                                </span>
                            ))
                        ) : (
                            <span className="text-base-content/40 text-xs">sin defectos</span>
                        )}
                    </span>,
                    f.caracteristico ? (
                        <div key="c">
                            <b className="text-warning">{f.caracteristico.defecto}</b>
                            <div className="text-base-content/50 text-xs">
                                {f.caracteristico.indice.toFixed(1)}× más frecuente que el promedio de {f.fase}
                            </div>
                        </div>
                    ) : (
                        <span key="c" className="text-base-content/40">
                            —
                        </span>
                    ),
                ])}
            />
        </TarjetaGrafica>
    );
}

export function TabDiagnostico() {
    return (
        <div className="space-y-3">
            <UsoCampos />

            <Rejilla2>
                {MAPAS_RIESGO.map((mapa) => (
                    <MapaDeRiesgo key={mapa.etapa} mapa={mapa} />
                ))}
            </Rejilla2>

            <FactoresRiesgo />
            <PerfilDefectos />
        </div>
    );
}
