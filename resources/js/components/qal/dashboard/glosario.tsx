/**
 * Pestaña **Glosario** del tablero: ¿qué significa cada número?
 *
 * Dos frases por término, siempre en el mismo orden: la primera dice para qué
 * sirve y la segunda cómo se calcula. Va agrupado como el tablero —pantalla,
 * sección, término— porque un glosario se consulta hojeando; el buscador es un
 * extra, no la única puerta.
 *
 * Tiene que decir lo mismo que el código. Cuando cambie una definición en
 * `TableroCalidad`, `EstadisticaDelTablero` o `DiagnosticoDelTablero`, cambia
 * aquí también: el del tablero anterior acabó hablando de métricas que la
 * auditoría había cambiado o retirado, y eso es peor que no tener glosario.
 */

import { useState } from 'react';
import { NotaCallada } from '@/components/qal/dashboard/ui';

type Termino = [titulo: string, definicion: string];

type Pantalla = { pantalla: string; secciones: { nombre: string; terminos: Termino[] }[] };

const GLOSARIO: Pantalla[] = [
    {
        pantalla: 'Conceptos base',
        secciones: [
            {
                nombre: 'Cómo se cuenta todo',
                terminos: [
                    [
                        'Inspección',
                        'Cada vez que un inspector guarda el formulario se crea una inspección con su folio QAL. No es una pieza: una pieza inspeccionada tres veces deja tres inspecciones.',
                    ],
                    [
                        'Pieza',
                        'La pieza física: en 2ª y pintura, su QR; en 1ª, la marca con su consecutivo, porque en corte todavía no hay QR. Casi todo el tablero cuenta piezas, no inspecciones, para que un retrabajo no infle los números.',
                    ],
                    [
                        'Pieza-etapa',
                        'La misma pieza cuenta por separado en cada etapa por la que pasa: armado, soldado y pintura tienen cada uno su veredicto. Es la unidad del FPY y del rechazo final, que preguntan cómo salió cada etapa.',
                    ],
                    [
                        'Veredicto',
                        'El resultado de la inspección: liberado, rechazado o pendiente. Las pendientes quedan fuera de las tasas porque todavía no se sabe si pasan.',
                    ],
                    [
                        'Excepción de armado y vestido',
                        'En esa sub-etapa no existe «liberado»: la pieza correcta avanza a soldado quedando pendiente. Por eso ahí pendiente cuenta como resultado correcto; si no, armado marcaría siempre 100 % de rechazo.',
                    ],
                    [
                        'Rechazada alguna vez vs veredicto final',
                        'Son dos formas de contar y ninguna es falsa: «alguna vez» mide el trabajo que hubo que rehacer y el veredicto final lo que quedó mal al cierre. El resumen y los factores de riesgo usan la primera; el chi² de Estadística, la segunda.',
                    ],
                    [
                        'Primera inspección',
                        'Las agrupaciones —por soldador, obra, tipo, módulo— toman lo que traía la pieza en su primera inspección. Si el retrabajo lo hizo otro soldador, la pieza sigue contando donde nació.',
                    ],
                    [
                        'Lote con muestreo',
                        'En accesorios y en lotes de 1ª se inspecciona una muestra y el veredicto ampara al lote entero. Las piezas liberadas cuentan el lote; las tasas de defectos, sólo la muestra que se miró.',
                    ],
                ],
            },
        ],
    },
    {
        pantalla: 'Resumen ejecutivo',
        secciones: [
            {
                nombre: 'Las cuatro tarjetas',
                terminos: [
                    [
                        'Piezas liberadas',
                        'Sirve para saber cuánto trabajo está realmente terminado. Cuenta las piezas cuyas etapas terminaron todas bien, no las inspecciones aprobadas; un lote con muestreo cuenta por lo que ampara.',
                    ],
                    [
                        'Rechazo en fabricación (2ª)',
                        'Mide qué proporción del armado y soldado hubo que rehacer. Es piezas de 2ª rechazadas alguna vez ÷ piezas de 2ª con veredicto; la flecha la compara con la semana anterior.',
                    ],
                    [
                        'Rechazo en pintura (3ª)',
                        'Lo mismo para recubrimientos. Va aparte de la 2ª porque las dos etapas tienen niveles de exigencia muy distintos y promediarlas escondía las dos.',
                    ],
                    [
                        'Pendientes',
                        'Piezas inspeccionadas cuyo resultado aún no está definido. No son un fallo, pero son trabajo abierto que puede esconder problemas.',
                    ],
                ],
            },
            {
                nombre: 'Debajo de las tarjetas',
                terminos: [
                    [
                        'Alertas',
                        'Avisos de hechos que piden una acción, no interpretaciones. Hoy son dos: lotes rechazados sin anotar qué se hizo con ellos y piezas que llevan más de dos semanas sin veredicto.',
                    ],
                    [
                        'Hallazgos',
                        'Frases automáticas con lo más relevante de lo filtrado. Son reglas fijas —un cambio de al menos medio punto contra la semana anterior, dos defectos que juntan la mitad de la soldadura, una obra que dobla al resto— y si no hay base, no dicen nada.',
                    ],
                ],
            },
        ],
    },
    {
        pantalla: 'Resumen analítica',
        secciones: [
            {
                nombre: 'Indicadores',
                terminos: [
                    [
                        'Inspecciones',
                        'Cuántas veces se inspeccionó, incluidas las re-inspecciones. Es carga de trabajo de calidad, no cantidad de piezas.',
                    ],
                    [
                        'Obras con inspecciones',
                        'Proyectos con al menos una inspección en lo que se está mirando. Cambia con los filtros de arriba.',
                    ],
                    [
                        'Inspecciones rechazadas',
                        'Qué parte del trabajo de inspección termina en rechazo. Es por inspección, así que una pieza rechazada dos veces cuenta dos veces: mide esfuerzo, no calidad.',
                    ],
                    [
                        'FPY · First Pass Yield',
                        'Qué porcentaje sale bien a la primera, sin retoques. Toma sólo la primera inspección de cada pieza-etapa: las que pasaron ÷ las que se inspeccionaron por primera vez.',
                    ],
                    [
                        'Rechazo final',
                        'Qué queda mal cuando ya no se va a tocar más. Son las pieza-etapa cuya última inspección es rechazada ÷ pieza-etapa con veredicto.',
                    ],
                    [
                        'Piezas en ambas etapas',
                        'Cuántas piezas han pasado ya por 2ª y por pintura. Mientras sean pocas, fabricación y pintura hablan de piezas distintas y por eso no se juntan en un solo rendimiento.',
                    ],
                    [
                        'Juntas mapeadas',
                        'Juntas de soldadura revisadas una por una en el mapeo y seguidas entre re-inspecciones. Sus porcentajes —FPY de soldadura, yield final, reproceso— no se publican por debajo de 20 juntas.',
                    ],
                ],
            },
        ],
    },
    {
        pantalla: 'Operación',
        secciones: [
            {
                nombre: 'Gráficas',
                terminos: [
                    [
                        'Resultado final por obra',
                        'Cómo acabó cada pieza de cada proyecto: liberada, rechazada o pendiente. Sirve para ver si una obra concreta está arrastrando el promedio.',
                    ],
                    [
                        'Rechazo por transformación',
                        'Compara las tres etapas: 1ª corte, 2ª armado y soldado, 3ª pintura. Es la lectura de gerencia: dónde duele más el proceso.',
                    ],
                    [
                        'Rechazo por…',
                        '% de piezas con al menos un rechazo en la dimensión elegida. Quedan fuera los grupos de menos de 4 piezas (10 si es una persona), se muestran los 12 de mayor rechazo y, si la lista mezcla etapas, se da la base de cada una.',
                    ],
                    [
                        'Rechazo por → Módulo',
                        'La tasa de cada módulo, con la obra pegada al nombre. El número de módulo se repite entre proyectos y sin la obra se sumarían dos módulos distintos.',
                    ],
                    [
                        'Tendencia de reproceso',
                        'Qué parte de las piezas que empezaron en cada semana o mes hubo que retrabajar. Cada pieza cuenta en el periodo de su primera inspección, para que un retrabajo no ensucie dos semanas.',
                    ],
                    [
                        'Pareto de defectos',
                        'Ordena los defectos de más a menos frecuente, con su acumulado. Va siempre por etapa —soldadura, armado y vestido, pintura— porque un ranking que junta procesos no dice dónde actuar.',
                    ],
                    [
                        'Tasa de defectos por elemento / tonelada / m²',
                        'Normaliza los defectos por el tamaño de lo fabricado, para comparar piezas grandes con pequeñas. Cuenta los defectos de la primera inspección sobre la exposición de cada pieza una sola vez; en 2ª sólo entra soldado, que es donde se capturan.',
                    ],
                    [
                        'Cobertura',
                        'Qué parte de las piezas tiene capturado el dato que necesita una tasa. Por debajo del 30 % la tasa no se publica, porque describiría a una minoría.',
                    ],
                    [
                        'Medida imposible',
                        'Un peso, un número de elementos o un área por encima de lo posible (20 t, 2,000 elementos, 1,000 m²). Se saca de la cuenta y se avisa para corregir el registro; no se corrige sola.',
                    ],
                ],
            },
        ],
    },
    {
        pantalla: 'Estadística',
        secciones: [
            {
                nombre: 'Control del proceso',
                terminos: [
                    [
                        'Carta-p',
                        'Sirve para distinguir una mala semana real de la variación normal del proceso. Dibuja la proporción de inspecciones rechazadas por semana con los límites que produce el propio proceso.',
                    ],
                    [
                        'UCL y LCL',
                        'Los límites de control: hasta dónde puede subir o bajar el rechazo sin que haya pasado nada especial. Se mueven cada semana porque dependen de cuántas inspecciones hubo.',
                    ],
                    [
                        'p̄',
                        'El promedio de rechazo del periodo, la línea de referencia de la carta. Si todos los puntos caen dentro de los límites, esa línea es el proceso: para bajarla hay que cambiar algo, no perseguir semanas malas.',
                    ],
                    [
                        'Límites preliminares',
                        'Con menos de 20 semanas los límites ya sirven para vigilar, pero todavía se moverán. Un punto sobre el UCL merece investigarse aunque sean preliminares.',
                    ],
                    [
                        'Cpk',
                        'Dice cuántas veces cabe la variación del proceso entre el promedio y el mínimo exigido. Se calcula como (media − mínimo) ÷ 3σ, sólo para el espesor de pintura y por espesor requerido, porque cada proyecto pinta con un sistema distinto.',
                    ],
                    [
                        'Capaz / marginal / no capaz',
                        'La lectura del Cpk según la referencia industrial: 1.33 o más es capaz, entre 1.0 y 1.33 marginal, por debajo de 1.0 no capaz. Con menos de 5 piezas medidas no se publica.',
                    ],
                    [
                        'Margen sobre el espesor requerido',
                        'Cuánto se pasa cada pieza del mínimo que le pide su proyecto, en porcentaje. Se usa el margen y no los mils porque 17 mils de un sistema no se comparan con 3 de otro.',
                    ],
                ],
            },
            {
                nombre: 'Estadística descriptiva',
                terminos: [
                    [
                        'Piezas con dato',
                        'Piezas distintas que tienen esa medición, no registros. Una pieza medida tres veces cuenta una vez, con su última medición.',
                    ],
                    [
                        'Media y mediana',
                        'La media es el promedio y la mediana el valor de en medio. Cuando se separan mucho hay cola: unas pocas piezas muy distintas estiran el promedio.',
                    ],
                    [
                        'σ (desviación estándar)',
                        'Cuánto se aleja del promedio una pieza cualquiera. Sola no dice mucho: hay que leerla junto a la media.',
                    ],
                    [
                        'Variabilidad (CV)',
                        'Es σ dividida entre la media, y sirve para comparar cosas medidas en unidades distintas. En un dato de proceso, alta significa que ahí no hay estándar; en un dato de producto, sólo que se fabrica de todo.',
                    ],
                ],
            },
            {
                nombre: 'Pruebas',
                terminos: [
                    [
                        'Chi-cuadrado',
                        'Sirve para saber si un factor —tipo de pieza, obra, soldador— influye de verdad en el rechazo final o es casualidad. Compara, dentro de una sola etapa, la tasa de cada categoría con la del conjunto; las categorías de menos de 5 piezas quedan fuera.',
                    ],
                    [
                        'p (p-value)',
                        'La probabilidad de ver esta diferencia si el factor no influyera para nada. Por debajo de 0,05 se considera evidencia.',
                    ],
                    [
                        'V de Cramér',
                        'Mide la fuerza de la relación, no sólo si existe. Débil por debajo de 0,20, moderada hasta 0,40, fuerte por encima: con muchos datos una diferencia mínima puede dar p < 0,05 y no importar.',
                    ],
                    [
                        '«Peor / mejor de lo esperado»',
                        'Compara los rechazos reales de una categoría con los que tendría si fuera como el resto. Se marca cuando la diferencia supera dos desviaciones.',
                    ],
                    [
                        '¿Está mejorando con el tiempo?',
                        'Prueba de tendencia de Cochran-Armitage: mira si el rechazo final sube o baja de forma sostenida semana a semana. Es distinto de comparar dos semanas sueltas, que casi siempre difieren por azar.',
                    ],
                ],
            },
        ],
    },
    {
        pantalla: 'Diagnóstico',
        secciones: [
            {
                nombre: 'Uso de los campos',
                terminos: [
                    [
                        '% respondido',
                        'De las inspecciones de esa etapa en que el punto aplicaba, en cuántas el inspector lo contestó. Un «no aplica» sí cuenta como respuesta: miró y decidió que ahí no tocaba.',
                    ],
                    [
                        '% con defecto',
                        'De las veces que el campo sí aplicaba, en cuántas salió mal. Los «no aplica» quedan fuera del denominador porque diluyen la señal.',
                    ],
                    [
                        'Detecta defectos',
                        'El campo se responde y al menos una de cada veinte veces sale mal. Está haciendo su trabajo.',
                    ],
                    [
                        'Se responde a medias',
                        'Se contesta en menos de la mitad de las piezas. O estorba, o no se entiende, o no aplica tan seguido como se creía.',
                    ],
                    [
                        'Siempre sale OK',
                        'Se contesta pero nunca falla. No ayuda a decidir, aunque valga como evidencia ante el cliente.',
                    ],
                    [
                        'Sin una sola respuesta',
                        'Ni una respuesta en diez o más piezas. Candidato a salir del formulario.',
                    ],
                    [
                        'Sin muestra',
                        'Esa etapa tiene menos de diez piezas en lo filtrado. Del campo todavía no se puede decir nada.',
                    ],
                    [
                        'Casilla en blanco',
                        'No cuenta como OK ni como defecto: cuenta como «no se llenó». Se comprobó que las piezas rechazadas dejan más casillas vacías que las liberadas, así que el blanco no significa «lo vi y estaba bien».',
                    ],
                ],
            },
            {
                nombre: 'Mapas de riesgo',
                terminos: [
                    [
                        'Frecuencia',
                        'Qué parte de todos los defectos de esa transformación es este tipo. Baja por debajo del 10 %, media hasta el 25 %, alta por encima.',
                    ],
                    [
                        'Impacto',
                        'Cuánto material compromete ese defecto: kilos en 2ª, m² en pintura. Los porcentajes no suman 100 porque una pieza con tres defectos presta sus kilos a los tres.',
                    ],
                    [
                        'Impacto en piezas',
                        'Si menos de la mitad de las piezas con defecto tienen peso o área, el impacto se mide en piezas afectadas. Un impacto de 0 por falta de dato se leería como «no impacta».',
                    ],
                ],
            },
            {
                nombre: 'Factores de riesgo',
                terminos: [
                    [
                        '«2.0×»',
                        'Esa categoría rechaza el doble que el promedio de su propia transformación. Se compara dentro de la etapa porque en pintura casi no se rechaza y mezclarlas hacía parecer un problema a cualquier cosa de 2ª.',
                    ],
                    [
                        'Confirmado',
                        'La diferencia aguanta la prueba incluso mirando todas las categorías a la vez: el listón es 0,05 dividido entre el número de comparaciones. Es la única que se puede llevar a junta.',
                    ],
                    [
                        'Indicio',
                        'Pasa la prueba simple (p < 0,05) pero no la exigente. Se vigila otra semana antes de mover a nadie.',
                    ],
                    [
                        'Puede ser azar',
                        'No significa que esté bien: significa que con estas piezas todavía no se puede afirmar. Con pocas piezas por categoría hacen falta diferencias enormes para detectar algo.',
                    ],
                ],
            },
            {
                nombre: 'Perfil de defectos',
                terminos: [
                    [
                        'Def./pieza',
                        'Cuántos defectos se le encuentran en promedio a cada pieza de ese soldador, obra o tipo. Es por pieza distinta, no por inspección.',
                    ],
                    [
                        'Defecto característico',
                        'El defecto en el que esa fila se desvía más de la mezcla normal de su etapa, con al menos 3 casos y nunca «Otro». Sirve para formación específica, no para comparar personas.',
                    ],
                    [
                        'n<10 · n<4',
                        'Con menos de diez piezas no se publica la tasa de una persona, y con menos de cuatro la de un proceso. Un «100 % de rechazo» sobre seis piezas no es una tasa.',
                    ],
                ],
            },
        ],
    },
    {
        pantalla: 'Accesorios',
        secciones: [
            {
                nombre: 'Lotes por muestreo',
                terminos: [
                    [
                        'Lote',
                        'Una marca de accesorio con un total del plano. Se va recibiendo por entregas, que son los sublotes.',
                    ],
                    [
                        'Sublote',
                        'Una entrega inspeccionada por muestreo. Se acepta o se rechaza entera según el criterio del nivel de muestreo.',
                    ],
                    [
                        'Unidades liberadas',
                        'Piezas aceptadas y disponibles para montaje. Suma las unidades de los sublotes aceptados, no las piezas de la muestra.',
                    ],
                    [
                        'No conformes en muestreo',
                        'Piezas malas encontradas dentro de la muestra revisada. Un sublote puede aceptarse con no conformes —el criterio lo permite—, pero son el aviso temprano de que el proveedor empeora.',
                    ],
                    [
                        'Sin disposición',
                        'Sublotes rechazados sin decidir qué se hace con ellos: devolver, retrabajar o aceptar por concesión. Es lo más urgente de la pestaña, porque el material se queda parado sin dueño.',
                    ],
                ],
            },
        ],
    },
    {
        pantalla: 'PND (pantalla propia)',
        secciones: [
            {
                nombre: 'Ensayos no destructivos',
                terminos: [
                    [
                        'Ensayo (spot)',
                        'Cada punto que examina el laboratorio es un ensayo; una junta puede llevar varios. Es la unidad de todo el PND: los porcentajes van sobre spots, no sobre juntas ni sobre informes.',
                    ],
                    [
                        'Informe',
                        'El reporte que entrega el laboratorio, de un solo método (UT, MT, PT, RT o VT). Un informe suele cubrir muchas juntas.',
                    ],
                    [
                        '% de rechazo',
                        'El dictamen del laboratorio: spots rechazados ÷ spots ensayados. Es la cifra normativa y la que va al dosier.',
                    ],
                    [
                        'Piezas con PND',
                        'Marcas con al menos un ensayo, y cuántas de ellas no tienen ningún rechazo. Una junta mala detiene la marca entera, así que se lee aparte del porcentaje de spots.',
                    ],
                    [
                        'Avance del plan',
                        'Spots aceptados contra los comprometidos con el cliente por cada método. Sólo aparece si la obra tiene anotada la cantidad pactada: sin ella no se inventa denominador.',
                    ],
                ],
            },
        ],
    },
    {
        pantalla: 'Reporte semanal (F-STX-CA-31)',
        secciones: [
            {
                nombre: 'Entregable',
                terminos: [
                    [
                        '% de incidencias',
                        'Qué parte de lo liberado en la semana venía de un rechazo. Es piezas liberadas con rechazo previo ÷ piezas liberadas, cada una en la semana en que se liberó, así que nunca pasa de 100.',
                    ],
                    [
                        'Kilos liberados',
                        'El peso de la estructura principal liberada en 2ª durante la semana. Sale del peso capturado en la inspección que liberó cada pieza.',
                    ],
                    [
                        'Semana ISO',
                        'La semana se nombra como 2026-S37 y va de lunes a domingo. El reporte tiene su propio selector de año y semana, y el corte viaja en el enlace.',
                    ],
                ],
            },
        ],
    },
    {
        pantalla: 'Métricas retiradas',
        secciones: [
            {
                nombre: 'Por qué no están',
                terminos: [
                    [
                        'RTY · Rolled Throughput Yield',
                        'Mide el rendimiento de la cadena completa multiplicando el de cada etapa. Se bloqueó porque hoy las piezas de 2ª y las de pintura son poblaciones distintas; vuelve cuando haya piezas suficientes recorriendo las dos.',
                    ],
                    [
                        '«% Retrabajos» separado de «% Rechazo»',
                        'Eran el mismo cálculo con dos nombres. Se fusionaron, y en su lugar se añadió «Rechazo final», que sí mide algo distinto.',
                    ],
                    [
                        'Alertas por umbral inventado',
                        'Se quitaron las alertas del tipo «rechazo por encima del 20 %», porque nadie había fijado ese 20 %. Quedan sólo las alertas de hechos comprobables.',
                    ],
                ],
            },
        ],
    },
];

/** Sin acentos ni mayúsculas: «pieza» encuentra «Pieza-etapa» y «tasa» encuentra «Tasa». */
function normalizar(texto: string): string {
    return texto
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();
}

function ancla(pantalla: string): string {
    return `glosario-${normalizar(pantalla).replace(/[^a-z0-9]+/g, '-')}`;
}

export function TabGlosario() {
    const [buscar, setBuscar] = useState('');
    const consulta = normalizar(buscar.trim());

    const visibles = GLOSARIO.map((p) => ({
        ...p,
        secciones: p.secciones
            .map((s) => ({
                ...s,
                terminos: s.terminos.filter(([t, d]) => !consulta || normalizar(`${t} ${d}`).includes(consulta)),
            }))
            .filter((s) => s.terminos.length > 0),
    })).filter((p) => p.secciones.length > 0);

    const contar = (pantallas: Pantalla[]) =>
        pantallas.reduce((a, p) => a + p.secciones.reduce((b, s) => b + s.terminos.length, 0), 0);

    return (
        <div className="space-y-3">
            <div className="rounded-box border-base-300 bg-base-100 space-y-2 border p-3">
                <div className="flex flex-wrap items-center gap-3">
                    <input
                        type="search"
                        className="input input-bordered input-sm w-full max-w-xs"
                        placeholder="Buscar: rechazo, muestreo, pieza…"
                        value={buscar}
                        onChange={(e) => setBuscar(e.target.value)}
                    />
                    <span className="text-base-content/60 text-xs">
                        {consulta ? `${contar(visibles)} coincidencia(s)` : `${contar(GLOSARIO)} términos`}
                    </span>
                </div>
                {visibles.length > 1 && (
                    <nav className="flex flex-wrap gap-1.5">
                        {visibles.map((p) => (
                            <a key={p.pantalla} href={`#${ancla(p.pantalla)}`} className="badge badge-ghost badge-sm">
                                {p.pantalla}
                            </a>
                        ))}
                    </nav>
                )}
            </div>

            {visibles.length === 0 ? (
                <NotaCallada>
                    Ningún término coincide con esa búsqueda. Prueba con una palabra suelta: «rechazo», «muestreo»,
                    «pieza».
                </NotaCallada>
            ) : (
                visibles.map((p) => (
                    <section
                        key={p.pantalla}
                        id={ancla(p.pantalla)}
                        className="rounded-box border-base-300 bg-base-100 scroll-mt-4 border p-4"
                    >
                        <h3 className="text-sm font-semibold">{p.pantalla}</h3>
                        {p.secciones.map((s) => (
                            <div key={s.nombre} className="mt-3">
                                <h4 className="text-base-content/50 mb-1 text-[11px] font-bold uppercase">{s.nombre}</h4>
                                <dl className="divide-base-300 divide-y">
                                    {s.terminos.map(([titulo, definicion]) => (
                                        <div key={titulo} className="grid gap-1 py-2 sm:grid-cols-[14rem_1fr] sm:gap-4">
                                            <dt className="text-sm font-semibold">{titulo}</dt>
                                            <dd className="text-base-content/70 text-sm">{definicion}</dd>
                                        </div>
                                    ))}
                                </dl>
                            </div>
                        ))}
                    </section>
                ))
            )}
        </div>
    );
}
