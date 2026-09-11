/**
 * Los números de la pestaña Diagnóstico. Son datos FALSOS, a propósito, y se
 * sustituyen por props del servidor en cuanto la pestaña se calcule de
 * `qal_inspecciones`, como ya lo hacen los resúmenes, Operación y Estadística
 * (`TableroCalidad`).
 *
 * Lo que NO es relleno y hay que conservar tal cual son las **definiciones**:
 * qué mide cada número, sobre qué denominador y cuándo se calla. Salen del
 * tablero de la aplicación anterior, donde ya estaban discutidas.
 */

// ---------------------------------------------------------------------------
// DIAGNÓSTICO · ¿el dato sirve?
// ---------------------------------------------------------------------------

/**
 * Uso de los campos del formulario.
 *
 * Si se le pide al inspector responder algo en cada pieza, o sirve o sobra.
 * Dos denominadores distintos y **no intercambiables**:
 *
 *  - `% respondido` = contestadas ÷ piezas de la etapa. Un «no aplica» ES una
 *    respuesta: el inspector miró y dijo que ahí no tocaba.
 *  - `% con defecto` = con defecto ÷ piezas donde el campo SÍ aplicaba. Meter
 *    los «no aplica» en el denominador diluye la señal.
 *
 * Y una regla que se comprobó en la base: **una casilla en blanco no cuenta
 * como OK**. Las piezas rechazadas dejan más casillas vacías que las liberadas;
 * el blanco es «no se llenó», no «lo vi y estaba bien».
 */
export type CampoFormulario = {
    fase: '1ª' | '2ª' | '3ª';
    bloque: string;
    campo: string;
    /** Piezas de esa etapa. */
    n: number;
    ok: number;
    defecto: number;
    noAplica: number;
    vacio: number;
};

export const USO_CAMPOS: CampoFormulario[] = [
    { fase: '2ª', bloque: 'Preparación de junta', campo: 'Ángulo de bisel', n: 310, ok: 214, defecto: 9, noAplica: 41, vacio: 46 },
    { fase: '2ª', bloque: 'Preparación de junta', campo: 'Separación de raíz', n: 310, ok: 198, defecto: 14, noAplica: 38, vacio: 60 },
    { fase: '2ª', bloque: 'Preparación de junta', campo: 'Acceso de soldadura', n: 310, ok: 13, defecto: 1, noAplica: 232, vacio: 64 },
    { fase: '2ª', bloque: 'Preparación de junta', campo: 'Placa de respaldo', n: 310, ok: 0, defecto: 0, noAplica: 0, vacio: 310 },
    { fase: '2ª', bloque: 'Armado y vestido', campo: 'Longitud total', n: 310, ok: 241, defecto: 22, noAplica: 12, vacio: 35 },
    { fase: '2ª', bloque: 'Armado y vestido', campo: 'Distancia entre placas', n: 310, ok: 187, defecto: 31, noAplica: 24, vacio: 68 },
    { fase: '2ª', bloque: 'Armado y vestido', campo: 'Giro o caída de placas', n: 310, ok: 203, defecto: 6, noAplica: 19, vacio: 82 },
    { fase: '2ª', bloque: 'Proceso de soldadura', campo: 'Precalentamiento', n: 310, ok: 96, defecto: 2, noAplica: 51, vacio: 161 },
    { fase: '2ª', bloque: 'Proceso de soldadura', campo: 'Limpieza entre pasadas', n: 310, ok: 172, defecto: 18, noAplica: 14, vacio: 106 },
    { fase: '2ª', bloque: 'Acabado e identificación', campo: 'Etiqueta', n: 310, ok: 268, defecto: 3, noAplica: 6, vacio: 33 },
    { fase: '2ª', bloque: 'Acabado e identificación', campo: 'Limpieza mecánica', n: 310, ok: 244, defecto: 11, noAplica: 8, vacio: 47 },
    { fase: '3ª', bloque: 'Mediciones', campo: 'Prueba de espesor', n: 154, ok: 41, defecto: 5, noAplica: 3, vacio: 105 },
    { fase: '3ª', bloque: 'Mediciones', campo: 'Método', n: 154, ok: 46, defecto: 0, noAplica: 2, vacio: 106 },
    { fase: '3ª', bloque: 'Inspección visual', campo: 'Revisión', n: 154, ok: 129, defecto: 9, noAplica: 4, vacio: 12 },
    { fase: '3ª', bloque: 'Inspección visual', campo: 'Acción', n: 154, ok: 22, defecto: 0, noAplica: 96, vacio: 36 },
    { fase: '3ª', bloque: 'Adherencia', campo: 'Adherencia', n: 154, ok: 18, defecto: 1, noAplica: 8, vacio: 127 },
    { fase: '1ª', bloque: 'Dimensional', campo: 'Longitud', n: 6, ok: 5, defecto: 1, noAplica: 0, vacio: 0 },
    { fase: '1ª', bloque: 'Corte y bisel', campo: 'Defectos de corte', n: 6, ok: 4, defecto: 2, noAplica: 0, vacio: 0 },
];

/** Por debajo de estas piezas no se puede decir nada de un campo. */
export const MIN_PIEZAS_CAMPO = 10;

/**
 * Mapa de riesgo: frecuencia contra impacto, defecto por defecto.
 *
 * Dos decisiones que se conservan del rediseño:
 *
 *  - **Los cortes son absolutos**, no relativos al defecto más frecuente. Con
 *    frecuencias parecidas —60, 50, 50, 43— los cinco salían «Alta» porque el
 *    primero definía la escala, y el eje cambiaba de significado cada semana.
 *    Ahora: baja por debajo del 10 % del total, media hasta el 25 %, alta arriba.
 *  - **Si la magnitud no llega al 50 % de cobertura, el eje de impacto se cae a
 *    piezas.** El área de pintura está capturada en 12 de 154 piezas; dibujar
 *    «impacto 0 m²» se lee como «no impacta» cuando lo que pasa es que no hay dato.
 */
export type ItemRiesgo = {
    defecto: string;
    defectos: number;
    /** % del total de defectos de la etapa. */
    frecuencia: number;
    piezas: number;
    magnitud: number;
    /** % de la magnitud (o de las piezas) con defecto que lleva este defecto. */
    impacto: number;
};

export type MapaRiesgo = {
    etapa: string;
    nota: string;
    totalDefectos: number;
    tiposDefecto: number;
    piezasEtapa: number;
    /** `false` = el eje de impacto se mide en piezas por falta de dato. */
    usaMagnitud: boolean;
    unidadMagnitud: string;
    magnitudLarga: string;
    coberturaMagnitud: number;
    items: ItemRiesgo[];
};

export const MAPAS_RIESGO: MapaRiesgo[] = [
    {
        etapa: '2ª transformación',
        nota: 'soldadura, armado y vestido',
        totalDefectos: 148,
        tiposDefecto: 9,
        piezasEtapa: 310,
        usaMagnitud: true,
        unidadMagnitud: 'kg',
        magnitudLarga: 'kilos de pieza afectados',
        coberturaMagnitud: 87,
        items: [
            { defecto: 'Socavado', defectos: 41, frecuencia: 27.7, piezas: 34, magnitud: 48200, impacto: 31 },
            { defecto: 'Porosidad', defectos: 33, frecuencia: 22.3, piezas: 29, magnitud: 31600, impacto: 20 },
            { defecto: 'Falta de vestido', defectos: 28, frecuencia: 18.9, piezas: 26, magnitud: 52900, impacto: 34 },
            { defecto: 'Falta de fusión', defectos: 14, frecuencia: 9.5, piezas: 12, magnitud: 18400, impacto: 12 },
            { defecto: 'Fuera de escuadra', defectos: 12, frecuencia: 8.1, piezas: 11, magnitud: 9100, impacto: 6 },
        ],
    },
    {
        etapa: 'pintura',
        nota: '3ª · histórico',
        totalDefectos: 37,
        tiposDefecto: 4,
        piezasEtapa: 154,
        usaMagnitud: false,
        unidadMagnitud: 'm²',
        magnitudLarga: 'm² pintados',
        coberturaMagnitud: 8,
        items: [
            { defecto: 'Espesor bajo', defectos: 19, frecuencia: 51.4, piezas: 17, magnitud: 0, impacto: 11 },
            { defecto: 'Escurrimiento', defectos: 9, frecuencia: 24.3, piezas: 8, magnitud: 0, impacto: 5 },
            { defecto: 'Piel de naranja', defectos: 6, frecuencia: 16.2, piezas: 6, magnitud: 0, impacto: 4 },
            { defecto: 'Contaminación', defectos: 3, frecuencia: 8.1, piezas: 3, magnitud: 0, impacto: 2 },
        ],
    },
];

/**
 * Factores de riesgo: qué categoría concreta se desvía del promedio **de su
 * propia transformación**.
 *
 * El chi² dice si un factor influye; esto dice cuál es el caso y por dónde
 * empezar. Aquí una pieza cuenta como rechazada si lo fue **alguna vez** —la
 * misma definición que la portada—, por eso los porcentajes salen más altos.
 *
 * `evidencia` no es el p a secas: como se miran muchas categorías a la vez,
 * alguna saldría alta por casualidad. **Confirmado** exige el listón corregido
 * (0,05 ÷ número de comparaciones) e **indicio** es el p < 0,05 de siempre.
 * «Puede ser azar» no significa que esté bien: significa que todavía no se puede
 * afirmar.
 *
 * Y lo más importante: **asociación, no causa**. Nadie ha controlado qué piezas
 * le tocaron a cada quien, y las difíciles no se reparten al azar.
 */
export type FactorRiesgo = {
    fase: string;
    factor: string;
    valor: string;
    piezas: number;
    rechazadas: number;
    /** % de rechazo de la categoría. */
    tasa: number;
    /** % de rechazo de su etapa. */
    base: number;
    /** Riesgo relativo: cuántas veces la tasa de su etapa. */
    rr: number;
    p: number;
    evidencia: 'confirmado' | 'indicio' | 'nada';
};

export const FACTORES_RIESGO: FactorRiesgo[] = [
    { fase: '2ª', factor: 'Sub-etapa', valor: 'Armado-Vestido', piezas: 96, rechazadas: 66, tasa: 68.8, base: 28.7, rr: 2.4, p: 0.0000001, evidencia: 'confirmado' },
    { fase: '2ª', factor: 'Tipo de pieza', valor: 'Contraviento', piezas: 41, rechazadas: 23, tasa: 56.1, base: 28.7, rr: 2, p: 0.0002, evidencia: 'confirmado' },
    { fase: '2ª', factor: 'Módulo', valor: 'Módulo 4', piezas: 28, rechazadas: 14, tasa: 50, base: 28.7, rr: 1.7, p: 0.014, evidencia: 'indicio' },
    { fase: '2ª', factor: 'Soldador', valor: 'MHV', piezas: 38, rechazadas: 17, tasa: 44.7, base: 28.7, rr: 1.6, p: 0.031, evidencia: 'indicio' },
    { fase: '3ª', factor: 'Obra', valor: 'CANCUN PARKS II NAVE A', piezas: 44, rechazadas: 9, tasa: 20.5, base: 11.7, rr: 1.8, p: 0.072, evidencia: 'nada' },
    { fase: '2ª', factor: 'Tipo de pieza', valor: 'Larguero de cubierta', piezas: 42, rechazadas: 6, tasa: 14.3, base: 28.7, rr: 0.5, p: 0.028, evidencia: 'indicio' },
    { fase: '2ª', factor: 'Soldador', valor: 'LGT', piezas: 34, rechazadas: 5, tasa: 14.7, base: 28.7, rr: 0.5, p: 0.061, evidencia: 'nada' },
];

/** Piezas mínimas para publicar una tasa con nombre de persona, y sin él. */
export const MIN_PIEZAS_PERSONA = 20;
export const MIN_PIEZAS_OTRO = 8;

/**
 * Perfil de defectos: **no** mide lo mismo que las dos tarjetas anteriores.
 *
 * El chi² y los factores de riesgo preguntan *quién rechaza más*; esto pregunta
 * *qué le sale mal a cada uno*. Dos soldadores con el mismo 30 % de rechazo
 * pueden tener problemas distintos —uno socavado, otro falta de remate— y eso
 * cambia por completo qué se hace al respecto.
 *
 * El **defecto característico** es aquel en el que esa categoría se desvía más
 * de la mezcla de **su etapa** (mínimo 3 casos; «Otro» no cuenta, es el cajón de
 * sastre). Sirve para dar formación específica, no para comparar personas.
 */
export type DimensionPerfil = 'soldador' | 'inspector' | 'obra' | 'modulo' | 'tipo';

export type FilaPerfil = {
    fase: string;
    nombre: string;
    piezas: number;
    inspecciones: number;
    defectos: number;
    defPorPieza: number;
    /** `null` = no llega al mínimo de piezas para publicar una tasa. */
    rechazo: number | null;
    top: { defecto: string; n: number; share: number }[];
    caracteristico: { defecto: string; indice: number } | null;
};

export const PERFIL_DEFECTOS: Record<DimensionPerfil, FilaPerfil[]> = {
    soldador: [
        {
            fase: '2ª', nombre: 'MHV', piezas: 38, inspecciones: 44, defectos: 31, defPorPieza: 0.82, rechazo: 44.7,
            top: [
                { defecto: 'Socavado', n: 16, share: 51.6 },
                { defecto: 'Porosidad', n: 9, share: 29 },
                { defecto: 'Falta de fusión', n: 4, share: 12.9 },
            ],
            caracteristico: { defecto: 'Socavado', indice: 1.9 },
        },
        {
            fase: '2ª', nombre: 'RSC', piezas: 44, inspecciones: 48, defectos: 24, defPorPieza: 0.55, rechazo: 34.1,
            top: [
                { defecto: 'Porosidad', n: 13, share: 54.2 },
                { defecto: 'Socavado', n: 6, share: 25 },
                { defecto: 'Salpicadura', n: 3, share: 12.5 },
            ],
            caracteristico: { defecto: 'Porosidad', indice: 2.4 },
        },
        {
            fase: '2ª', nombre: 'APC', piezas: 51, inspecciones: 53, defectos: 18, defPorPieza: 0.35, rechazo: 23.5,
            top: [
                { defecto: 'Socavado', n: 8, share: 44.4 },
                { defecto: 'Falta de fusión', n: 5, share: 27.8 },
                { defecto: 'Cráter', n: 3, share: 16.7 },
            ],
            caracteristico: { defecto: 'Falta de fusión', indice: 2.9 },
        },
        {
            fase: '2ª', nombre: 'LGT', piezas: 34, inspecciones: 35, defectos: 9, defPorPieza: 0.26, rechazo: 14.7,
            top: [
                { defecto: 'Socavado', n: 5, share: 55.6 },
                { defecto: 'Porosidad', n: 3, share: 33.3 },
            ],
            caracteristico: null,
        },
        {
            fase: '2ª', nombre: 'TDL', piezas: 12, inspecciones: 12, defectos: 3, defPorPieza: 0.25, rechazo: null,
            top: [{ defecto: 'Porosidad', n: 3, share: 100 }],
            caracteristico: null,
        },
    ],
    inspector: [
        {
            fase: '2ª', nombre: 'J. Cabrera', piezas: 94, inspecciones: 108, defectos: 62, defPorPieza: 0.66, rechazo: 38.3,
            top: [
                { defecto: 'Socavado', n: 22, share: 35.5 },
                { defecto: 'Falta de vestido', n: 19, share: 30.6 },
                { defecto: 'Porosidad', n: 12, share: 19.4 },
            ],
            caracteristico: { defecto: 'Falta de vestido', indice: 1.6 },
        },
        {
            fase: '2ª', nombre: 'E. Rivas', piezas: 121, inspecciones: 134, defectos: 58, defPorPieza: 0.48, rechazo: 27.3,
            top: [
                { defecto: 'Porosidad', n: 18, share: 31 },
                { defecto: 'Socavado', n: 15, share: 25.9 },
                { defecto: 'Fuera de escuadra', n: 9, share: 15.5 },
            ],
            caracteristico: { defecto: 'Fuera de escuadra', indice: 1.9 },
        },
        {
            fase: '3ª', nombre: 'M. Solís', piezas: 88, inspecciones: 91, defectos: 24, defPorPieza: 0.27, rechazo: 12.5,
            top: [
                { defecto: 'Espesor bajo', n: 14, share: 58.3 },
                { defecto: 'Escurrimiento', n: 6, share: 25 },
            ],
            caracteristico: null,
        },
    ],
    obra: [
        {
            fase: '2ª', nombre: 'AMPLIACION T4 CANCUN', piezas: 131, inspecciones: 149, defectos: 64, defPorPieza: 0.49, rechazo: 25.2,
            top: [
                { defecto: 'Socavado', n: 21, share: 32.8 },
                { defecto: 'Porosidad', n: 16, share: 25 },
                { defecto: 'Falta de vestido', n: 11, share: 17.2 },
            ],
            caracteristico: null,
        },
        {
            fase: '2ª', nombre: 'TRES GUERRAS VILLA MAGNA', piezas: 46, inspecciones: 58, defectos: 38, defPorPieza: 0.83, rechazo: 47.8,
            top: [
                { defecto: 'Falta de vestido', n: 17, share: 44.7 },
                { defecto: 'Fuera de escuadra', n: 8, share: 21.1 },
                { defecto: 'Socavado', n: 7, share: 18.4 },
            ],
            caracteristico: { defecto: 'Falta de vestido', indice: 2.4 },
        },
        {
            fase: '3ª', nombre: 'CANCUN PARKS II NAVE A', piezas: 44, inspecciones: 47, defectos: 16, defPorPieza: 0.36, rechazo: 20.5,
            top: [
                { defecto: 'Espesor bajo', n: 11, share: 68.8 },
                { defecto: 'Piel de naranja', n: 3, share: 18.8 },
            ],
            caracteristico: { defecto: 'Espesor bajo', indice: 1.3 },
        },
    ],
    modulo: [
        {
            fase: '2ª', nombre: 'Módulo 4', piezas: 28, inspecciones: 36, defectos: 26, defPorPieza: 0.93, rechazo: 50,
            top: [
                { defecto: 'Socavado', n: 12, share: 46.2 },
                { defecto: 'Falta de fusión', n: 6, share: 23.1 },
                { defecto: 'Porosidad', n: 5, share: 19.2 },
            ],
            caracteristico: { defecto: 'Falta de fusión', indice: 2.4 },
        },
        {
            fase: '2ª', nombre: 'Módulo 2', piezas: 62, inspecciones: 68, defectos: 29, defPorPieza: 0.47, rechazo: 25.8,
            top: [
                { defecto: 'Porosidad', n: 12, share: 41.4 },
                { defecto: 'Socavado', n: 9, share: 31 },
            ],
            caracteristico: null,
        },
        {
            fase: '2ª', nombre: 'Módulo 1', piezas: 54, inspecciones: 57, defectos: 18, defPorPieza: 0.33, rechazo: 20.4,
            top: [
                { defecto: 'Socavado', n: 8, share: 44.4 },
                { defecto: 'Falta de vestido', n: 5, share: 27.8 },
            ],
            caracteristico: null,
        },
    ],
    tipo: [
        {
            fase: '2ª', nombre: 'Contraviento', piezas: 41, inspecciones: 52, defectos: 34, defPorPieza: 0.83, rechazo: 56.1,
            top: [
                { defecto: 'Fuera de escuadra', n: 11, share: 32.4 },
                { defecto: 'Falta de vestido', n: 10, share: 29.4 },
                { defecto: 'Socavado', n: 7, share: 20.6 },
            ],
            caracteristico: { defecto: 'Fuera de escuadra', indice: 4 },
        },
        {
            fase: '2ª', nombre: 'Trabe principal', piezas: 118, inspecciones: 131, defectos: 61, defPorPieza: 0.52, rechazo: 30.5,
            top: [
                { defecto: 'Socavado', n: 24, share: 39.3 },
                { defecto: 'Porosidad', n: 17, share: 27.9 },
                { defecto: 'Falta de fusión', n: 8, share: 13.1 },
            ],
            caracteristico: null,
        },
        {
            fase: '2ª', nombre: 'Columna metálica', piezas: 87, inspecciones: 92, defectos: 28, defPorPieza: 0.32, rechazo: 19.5,
            top: [
                { defecto: 'Porosidad', n: 11, share: 39.3 },
                { defecto: 'Socavado', n: 9, share: 32.1 },
            ],
            caracteristico: null,
        },
    ],
};
