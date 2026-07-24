export type Usuario = {
    id: string;
    empleado: number | null;
    departamento_id: number | null;
    name: string;
    email: string;
    email_verified_at: string | null;
    activo: boolean;
    fecha_baja: string | null;
    firma_path: string | null;
    roles?: Role[];
    departamento?: Departamento;
    created_at: string;
    updated_at: string;
};

export type Role = {
    id: number;
    name: string;
    guard_name: string;
    permissions?: Permission[];
    created_at: string;
    updated_at: string;
};

export type Permission = {
    id: number;
    name: string;
    guard_name: string;
    created_at: string;
    updated_at: string;
};

export type Departamento = {
    id: number;
    descripcion: string;
    manager: string;
    manager_usuario_id: string | null;
    manager_usuario?: Usuario;
    created_at: string;
    updated_at: string;
};

export type ObraEstatus = 'abierta' | 'cerrada';

export const OBRA_ESTATUS_LABELS: Record<ObraEstatus, string> = {
    abierta: 'Abierta',
    cerrada: 'Cerrada',
};

export type Proyecto = {
    id: number;
    no: string;
    descripcion: string;
    cliente_id?: number | null;
    fecha_inicio_plan?: string | null;
    estatus: ObraEstatus;
    activa?: boolean;
    cliente?: Cliente;
    obras?: Obra[];
    obra_base?: Obra | null;
    estimaciones?: CobEstimacion[];
    plan_cobro?: CobPlanCobro[];
    comparativos?: CobComparativo[];
    documento_carpetas?: CobDocumentoCarpeta[];
    documento_archivos?: CobDocumentoArchivo[];
    seccion_estatus?: CobDocumentoSeccionProyecto[];
    created_at: string;
    updated_at: string;
};

export type CobPlanCobro = {
    id: number;
    proyecto_id: number;
    orden: number;
    dias: number;
    fecha_inicio_plan: string;
    fecha_fin_plan: string;
    created_at: string;
    updated_at: string;
};

export type CobObraEtapa = {
    id: number;
    obra_id: number;
    descripcion: string;
    fecha_inicio_plan: string | null;
    fecha_fin_plan: string | null;
    created_at: string;
    updated_at: string;
};

export type CobReporteDetonacion = {
    obra_id: number;
    obra_no: string;
    descripcion: string | null;
    monto_sin_iva: number;
    monto_con_iva: number;
};

export type CobReporteCobro = {
    obra_id: number | null;
    obra_no: string;
    estimacion_id: number;
    numero_estimacion: number;
    monto_sin_iva: number;
    monto_con_iva: number;
};

/** Montos comunes a la fila anual y al reporte de la semana (calculados en vivo). */
type CobReporteMontos = {
    anio: number;
    semana: number;
    fecha_inicio: string;
    fecha_fin: string;
    saldo_anterior_sin_iva: number;
    saldo_anterior_con_iva: number;
    total_detonaciones_sin_iva: number;
    total_detonaciones_con_iva: number;
    total_cobrado_sin_iva: number;
    total_cobrado_con_iva: number;
    saldo_nuevo_sin_iva: number;
    saldo_nuevo_con_iva: number;
};

/** Fila de la tabla anual (una por semana). */
export type CobReporteFila = CobReporteMontos & {
    tiene_notas: boolean;
};

/** Reporte completo de una semana. */
export type CobReporteSemana = CobReporteMontos & {
    detonaciones: CobReporteDetonacion[];
    cobros: CobReporteCobro[];
    notas: string | null;
};

export type Obra = {
    id: number;
    proyecto_id?: number | null;
    tipo?: 'base' | 'adicional';
    proyecto?: Proyecto;
    no: string;
    descripcion: string;
    fecha_inicio: string | null;
    fecha_fin: string | null;
    presupuesto_total: number;
    ingreso_real: number | null;
    estatus: ObraEstatus;
    obra_rubros?: CostosObraRubro[];
    // Cobranza fields
    cliente_id?: number | null;
    tipo_contrato?: string | null;
    monto?: number | null;
    monto_iva?: number | null;
    anticipo?: number | null;
    garantia?: number | null;
    peso?: number | null;
    porcentaje_fabricacion?: number | null;
    porcentaje_montaje?: number | null;
    porcentaje_otros?: number | null;
    descripcion_otros?: string | null;
    activa?: boolean;
    es_planta?: boolean;
    porcentaje_obra?: number | null;
    cliente?: Cliente;
    partidas?: CobPartida[];
    etapas_pmo?: CobObraEtapa[];
    estimaciones?: CobEstimacion[];
    anticipos?: CobAnticipo[];
    adendas?: CobAdenda[];
    deducciones?: CobDeduccion[];
    eventos?: CobEvento[];
    disputas?: CobDisputa[];
    penalizaciones?: CobPenalizacion[];
    created_at: string;
    updated_at: string;
};

export type CobSeccionEstatus = 'pendiente' | 'completado';

export type CobDocumentoSeccion = {
    id: number;
    nombre: string;
    orden: number;
    activo?: boolean;
    carpetas_count?: number;
    archivos_count?: number;
    // Presentes solo en el contexto de un proyecto (overlay por proyecto).
    estatus?: CobSeccionEstatus;
    visible?: boolean;
    created_at?: string;
    updated_at?: string;
};

export type CobDocumentoSeccionProyecto = {
    id: number;
    proyecto_id: number;
    seccion_id: number;
    estatus: CobSeccionEstatus;
    visible: boolean;
    created_at: string;
    updated_at: string;
};

export type CobDocumentoCarpeta = {
    id: number;
    proyecto_id: number;
    seccion_id: number;
    parent_id: number | null;
    nombre: string;
    orden: number;
    created_at: string;
    updated_at: string;
};

export type CobDocumentoArchivo = {
    id: number;
    proyecto_id: number;
    seccion_id: number;
    carpeta_id: number | null;
    nombre_original: string;
    path: string;
    mime: string | null;
    size: number | null;
    subido_por_id: string | null;
    created_at: string;
    updated_at: string;
};

export type Media = {
    id: number;
    descripcion: string;
    nombre_original: string | null;
    path: string;
    mime: string;
    size: number;
    mediable_type: string;
    mediable_id: number;
    created_at: string;
    updated_at: string;
};

export type Tag = {
    id: number;
    name: string;
    slug: string;
    color: string | null;
    statusable_type: string | null;
    statusable_id: number | null;
    created_at: string;
    updated_at: string;
};

export type PaginatedData<T> = {
    current_page: number;
    data: T[];
    first_page_url: string | null;
    from: number | null;
    last_page: number;
    last_page_url: string | null;
    links: { url: string | null; label: string; active: boolean }[];
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};

// Intranet types
export type TipoDocumento =
    | 'manual_operativo'
    | 'formato_proceso'
    | 'protocolo'
    | 'politica'
    | 'instructivo_trabajo'
    | 'procedimiento_especifico'
    | 'procedimiento_general'
    | 'plan_calidad'
    | 'manual_gestion';

export type SeccionEstatica = {
    id: number;
    slug: string;
    titulo: string;
    descripcion: string | null;
    boton: string;
    url_externa: string | null;
    order: number;
    activo: boolean;
    media?: Media;
    created_at: string;
    updated_at: string;
};

export type Area = {
    id: number;
    descripcion: string;
    parent_id: number | null;
    parent?: Area;
    children?: Area[];
    order: number;
    activo: boolean;
    documentos?: Documento[];
    created_at: string;
    updated_at: string;
};

export type Documento = {
    id: number;
    area_id: number;
    descripcion: string;
    codigo: string | null;
    tipo: TipoDocumento;
    order: number;
    activo: boolean;
    area?: Area;
    media?: Media;
    created_at: string;
    updated_at: string;
};

export type DocumentosPorTipo = Record<TipoDocumento, {
    label: string;
    documentos: Documento[];
}>;

// STI Types
export type StiCriticidad = 1 | 2 | 3 | 4;

export const CRITICIDAD_LABELS: Record<StiCriticidad, string> = {
    1: 'Bajo',
    2: 'Medio',
    3: 'Alto',
    4: 'Critico',
};

export const CRITICIDAD_COLORS: Record<StiCriticidad, string> = {
    1: 'badge-info',
    2: 'badge-warning',
    3: 'badge-error',
    4: 'badge-error bg-red-700',
};

export type StiEquipo = {
    id: number;
    descripcion: string;
    serie: string | null;
    marca: string | null;
    factor_criticidad: StiCriticidad;
    tickets?: StiTicket[];
    mantenimientos?: StiMantenimiento[];
    mantenimientos_count?: number;
    asignaciones?: StiAsignacionActivo[];
    grupos?: StiGrupo[];
    items?: StiItem[];
    created_at: string;
    updated_at: string;
};

export type StiTecnico = {
    id: number;
    descripcion: string;
    activo: boolean;
    created_at: string;
    updated_at: string;
};

export type StiStatus = {
    id: number;
    descripcion: string;
    orden: number;
    detiene_tiempo: boolean;
    color: string;
    created_at: string;
    updated_at: string;
};

export type StiTicketHistorial = {
    id: number;
    ticket_id: number;
    status_id: number;
    status?: StiStatus;
    created_at: string;
    updated_at: string;
};

export type StiTicketComentario = {
    id: number;
    ticket_id: number;
    comentario: string;
    autor: string;
    tipo: 'usuario' | 'tecnico';
    created_at: string;
    updated_at: string;
};

export type StiTicket = {
    id: number;
    nombre_solicitante: string;
    comentario: string;
    tecnico_id: number | null;
    equipo_id: number | null;
    departamento_id: number;
    firma_completado: string | null;
    calificacion: number | null;
    tecnico?: StiTecnico;
    equipo?: StiEquipo;
    departamento?: Departamento;
    historial?: StiTicketHistorial[];
    comentarios?: StiTicketComentario[];
    media?: Media[];
    tags?: Tag[];
    costos?: StiCostoMantenimiento[];
    created_at: string;
    updated_at: string;
};

export type StiCostoMantenimiento = {
    id: number;
    descripcion: string;
    cantidad: number;
    costeable_id: number;
    costeable_type: string;
    created_at: string;
    updated_at: string;
};

export type StiMantenimiento = {
    id: number;
    equipo_id: number;
    plan_id: number | null;
    fecha_programada: string;
    descripcion: string | null;
    tecnico_id: number | null;
    status: 'pendiente' | 'realizado';
    fecha_realizado: string | null;
    equipo?: StiEquipo;
    tecnico?: StiTecnico;
    plan?: StiPlan;
    media?: Media[];
    costos?: StiCostoMantenimiento[];
    check_ejecuciones?: StiCheckEjecucion[];
    created_at: string;
    updated_at: string;
};

export type StiPlan = {
    id: number;
    descripcion: string;
    periodicidad: number;
    fecha_inicial: string | null;
    activo: boolean;
    checks?: StiCheck[];
    mantenimientos?: StiMantenimiento[];
    checks_count?: number;
    mantenimientos_count?: number;
    created_at: string;
    updated_at: string;
};

export type StiCheck = {
    id: number;
    plan_id: number;
    descripcion: string;
    orden: number;
    plan?: StiPlan;
    created_at: string;
    updated_at: string;
};

export type StiCheckEjecucion = {
    id: number;
    mantenimiento_id: number;
    check_id: number;
    resultado: boolean;
    observaciones: string | null;
    tecnico_id: number | null;
    check?: StiCheck;
    tecnico?: StiTecnico;
    created_at: string;
    updated_at: string;
};

export type StiAsignacionActivo = {
    id: number;
    departamento_id: number;
    equipo_id: number;
    no_empleado: string;
    empleado: string;
    firma_empleado: string | null;
    no_ti: string;
    nombre_ti: string;
    firma_ti: string | null;
    fecha_inicial: string;
    fecha_termino: string | null;
    estado: 'activo' | 'devuelto' | 'transferido';
    departamento?: Departamento;
    equipo?: StiEquipo;
    media?: Media[];
    created_at: string;
    updated_at: string;
};

// STI Inventario Types
export type StiItemEstado = 'disponible' | 'instalado' | 'dañado' | 'baja';

export const ITEM_ESTADO_LABELS: Record<StiItemEstado, string> = {
    disponible: 'Disponible',
    instalado: 'Instalado',
    dañado: 'Dañado',
    baja: 'Baja',
};

export const ITEM_ESTADO_COLORS: Record<StiItemEstado, string> = {
    disponible: 'badge-success',
    instalado: 'badge-info',
    dañado: 'badge-warning',
    baja: 'badge-error',
};

export type StiItemAccion = 'recepcion' | 'instalacion' | 'retiro' | 'baja';

export const ITEM_ACCION_LABELS: Record<StiItemAccion, string> = {
    recepcion: 'Recepción',
    instalacion: 'Instalación',
    retiro: 'Retiro',
    baja: 'Baja',
};

export type StiItemTipo = {
    id: number;
    descripcion: string;
    created_at: string;
    updated_at: string;
};

export type StiItem = {
    id: number;
    descripcion: string;
    tipo_id: number;
    costo: number;
    no_serie: string | null;
    estado: StiItemEstado;
    principal: boolean;
    accesorio: boolean;
    tipo?: StiItemTipo;
    grupo?: StiGrupo;
    historial?: StiItemHistorial[];
    media?: Media[];
    created_at: string;
    updated_at: string;
};

export type StiGrupo = {
    id: number;
    equipo_id: number;
    item_id: number;
    equipo?: StiEquipo;
    item?: StiItem;
    created_at: string;
    updated_at: string;
};

// Produccion Types
export type Concepto = {
    id: number;
    obra_id: number;
    marca: string;
    descripcion: string;
    cantidad: number;
    peso_unitario: number;
    version: number;
    activo: boolean;
    obra?: Obra;
    registros_sum_cantidad?: number;
    grupo_precio_conceptos?: ProdGrupoPrecioConcepto[];
    created_at: string;
    updated_at: string;
};

export type ProdGrupoPrecio = {
    id: number;
    obra_id: number;
    descripcion: string;
    precio_kilo: number;
    obra?: Obra;
    grupo_precio_conceptos_count?: number;
    conceptos?: Concepto[];
    created_at: string;
    updated_at: string;
};

export type ProdGrupoPrecioConcepto = {
    id: number;
    grupo_precio_id: number;
    concepto_id: number;
    grupo_precio?: ProdGrupoPrecio;
    concepto?: Concepto;
    created_at: string;
    updated_at: string;
};

export type ProdGrupoTrabajo = {
    id: number;
    descripcion: string;
    linea: number;
    modulo: number;
    activo: boolean;
    empleados?: ProdGrupoEmpleado[];
    empleados_count?: number;
    created_at: string;
    updated_at: string;
};

export type ProdGrupoEmpleado = {
    id: number;
    grupo_trabajo_id: number;
    nombre: string;
    no_empleado: string | null;
    porcentaje: number;
    created_at: string;
    updated_at: string;
};

export type ProdRegistro = {
    id: number;
    fecha: string;
    concepto_id: number;
    grupo_trabajo_id: number;
    cantidad: number;
    concepto?: Concepto;
    grupo_trabajo?: ProdGrupoTrabajo;
    created_at: string;
    updated_at: string;
};

export type ProdCorte = {
    id: number;
    semana: number;
    fecha_inicio: string;
    fecha_fin: string;
    cerrado: boolean;
    fecha_cierre: string | null;
    liquidaciones?: ProdLiquidacion[];
    liquidaciones_count?: number;
    created_at: string;
    updated_at: string;
};

export type ProdLiquidacion = {
    id: number;
    corte_id: number;
    grupo_trabajo_id: number;
    total_kilos: number;
    total_produccion: number;
    total_extras: number;
    total_final: number;
    generado_en: string;
    generado_por: string;
    corte?: ProdCorte;
    grupo_trabajo?: ProdGrupoTrabajo;
    generador?: Usuario;
    detalles?: ProdLiquidacionDetalle[];
    pagos_extra?: ProdPagoExtra[];
    empleados?: ProdLiquidacionEmpleado[];
    created_at: string;
    updated_at: string;
};

export type ProdLiquidacionDetalle = {
    id: number;
    liquidacion_id: number;
    concepto_id: number;
    grupo_precio_id: number;
    cantidad: number;
    kilos: number;
    precio_kilo_aplicado: number;
    total: number;
    created_at: string;
    updated_at: string;
};

export type ProdTipoPagoExtra = {
    id: number;
    descripcion: string;
    orden: number;
    desgloce: boolean;
    created_at: string;
    updated_at: string;
};

export type ProdPagoExtra = {
    id: number;
    descripcion: string;
    tipo_id: number;
    corte_id: number;
    grupo_trabajo_id: number;
    precio: number;
    dias: number;
    personas: number;
    monto?: number;
    tipo?: ProdTipoPagoExtra;
    corte?: ProdCorte;
    grupo_trabajo?: ProdGrupoTrabajo;
    created_at: string;
    updated_at: string;
};

export type ProdLiquidacionEmpleado = {
    id: number;
    liquidacion_id: number;
    nombre: string;
    no_empleado: string | null;
    porcentaje: number;
    monto_asignado: number;
    created_at: string;
    updated_at: string;
};

// Infraestructura Types
export type InfraCompresor = {
    id: number;
    usuario_id: string | null;
    infra_turno_id: number | null;
    compresor_1_status: boolean;
    compresor_1_presion_aire: number | null;
    compresor_1_tiempo_trabajo: number | null;
    compresor_1_tiempo_marcha: number | null;
    compresor_1_kwhr: number | null;
    compresor_2_status: boolean;
    compresor_2_presion_aire: number | null;
    compresor_2_tiempo_trabajo: number | null;
    compresor_2_tiempo_marcha: number | null;
    compresor_2_kwhr: number | null;
    compresor_3_status: boolean;
    compresor_3_presion_aire: number | null;
    compresor_3_tiempo_trabajo: number | null;
    compresor_3_tiempo_marcha: number | null;
    compresor_3_kwhr: number | null;
    observaciones: string | null;
    usuario?: Usuario;
    created_at: string;
    updated_at: string;
};

export type InfraBomba = {
    id: number;
    usuario_id: string | null;
    infra_turno_id: number | null;
    bomba_posos_1: boolean;
    bomba_posos_2: boolean;
    bomba_planta_1: boolean;
    bomba_planta_2: boolean;
    bomba_planta_3: boolean;
    nivel_salmuera: number | null;
    nivel_tinaco: number | null;
    nivel_sisterna: number | null;
    presion_tuberia: number | null;
    nivel_hipoclorito: number | null;
    nivel_anticongelante: number | null;
    aceite_del_motor: number | null;
    tanque_diesel: number | null;
    voltaje_bateria: number | null;
    bomba_jockey: boolean;
    bomba_electrica: boolean;
    bomba_diesel: boolean;
    presion_tuberia_incendio: number | null;
    observaciones: string | null;
    usuario?: Usuario;
    created_at: string;
    updated_at: string;
};

export type InfraTransformador = {
    id: number;
    usuario_id: string | null;
    infra_turno_id: number | null;
    linea_A: number | null;
    linea_A_max: number | null;
    date_A: string | null;
    linea_B: number | null;
    linea_B_max: number | null;
    date_B: string | null;
    linea_C: number | null;
    linea_C_max: number | null;
    date_C: string | null;
    voltaje_a: number | null;
    voltaje_b: number | null;
    voltaje_c: number | null;
    total_1: number | null;
    total_5: number | null;
    lectura_5y5: number | null;
    registro_a: number | null;
    registro_b: number | null;
    registro_c: number | null;
    tarifa: number | null;
    observaciones: string | null;
    usuario?: Usuario;
    created_at: string;
    updated_at: string;
};

export type InfraTanque = {
    id: number;
    usuario_id: string | null;
    infra_turno_id: number | null;
    pa_sistema_oxigeno: number | null;
    presion_sistema_oxigeno: number | null;
    presion_tanque_oxigeno: number | null;
    lt_tanque_oxigeno: number | null;
    kg_tanque_oxigeno: number | null;
    pa_sistema_argon: number | null;
    presion_sistema_argon: number | null;
    presion_tanque_argon: number | null;
    lt_tanque_argon: number | null;
    kg_tanque_argon: number | null;
    pa_sistema_co2: number | null;
    presion_sistema_co2: number | null;
    presion_tanque_co2: number | null;
    lt_tanque_co2: number | null;
    kg_tanque_co2: number | null;
    pa_sistema_lp: number | null;
    presion_sistema_lp: number | null;
    nivel_tanque_lp: number | null;
    numero_tanque_lp: number | null;
    lt_tanque_lp: number | null;
    kg_tanque_lp: number | null;
    observaciones: string | null;
    usuario?: Usuario;
    created_at: string;
    updated_at: string;
};

export type InfraPtar = {
    id: number;
    usuario_id: string | null;
    infra_turno_id: number | null;
    soplador_activa: boolean;
    bomba_activa: boolean;
    nivel_cloro: number | null;
    trampa_solida: boolean;
    observaciones: string | null;
    usuario?: Usuario;
    created_at: string;
    updated_at: string;
};

export type InfraTurno = {
    id: number | null;
    nombre: string;
    hora_inicio: string | null;
    hora_fin: string | null;
    orden: number;
    activo?: boolean;
    dias_semana?: InfraTurnoDia[];
};

export type InfraTurnoDia = {
    id: number;
    infra_turno_id: number;
    dia_semana: number;
};

export type InfraTurnoData = {
    turno: InfraTurno;
    compresores: InfraCompresor | null;
    bombas: InfraBomba | null;
    transformador: InfraTransformador | null;
    tanques: InfraTanque | null;
    ptar: InfraPtar | null;
};

// Infra Dashboard Types
export type InfraEstadoIndicador = {
    label: string;
    estado: boolean | null;
    tooltip: string;
};

export type InfraEstados = {
    compresores: InfraEstadoIndicador[];
    bombas: InfraEstadoIndicador[];
    tanques: InfraEstadoIndicador[];
    ptar: InfraEstadoIndicador[];
    transformadores: InfraEstadoIndicador[];
};

export type InfraChartCompresor = {
    mes: string;
    c1: number;
    c2: number;
    c3: number;
};

export type InfraChartBomba = {
    mes: string;
    avg_presion: number;
    stddev: number;
    max_presion: number;
    min_presion: number;
};

export type InfraChartTransformador = {
    mes: string;
    total_1: number;
    total_5: number;
    lectura_5y5: number;
};

export type InfraChartTanque = {
    mes: string;
    oxigeno: number;
    argon: number;
    co2: number;
    lp: number;
    oxigeno_acum: number;
    argon_acum: number;
    co2_acum: number;
    lp_acum: number;
};

export type StiItemHistorial = {
    id: number;
    item_id: number;
    equipo_id: number;
    tecnico_id: number | null;
    relacionable_type: string | null;
    relacionable_id: number | null;
    accion: StiItemAccion;
    fecha: string;
    observaciones: string | null;
    equipo?: StiEquipo;
    tecnico?: StiTecnico;
    created_at: string;
    updated_at: string;
};

// Costos Types
export type ProveedorEstatus = 'pendiente_validacion' | 'activo' | 'rechazado';

export const PROVEEDOR_ESTATUS_LABELS: Record<ProveedorEstatus, string> = {
    pendiente_validacion: 'Pendiente de validación',
    activo: 'Activo',
    rechazado: 'Rechazado',
};

export const PROVEEDOR_ESTATUS_COLORS: Record<ProveedorEstatus, string> = {
    pendiente_validacion: 'badge-warning',
    activo: 'badge-success',
    rechazado: 'badge-error',
};

export type TipoProveedor = 'proveedor' | 'tercero' | 'servicio';

export const TIPO_PROVEEDOR_LABELS: Record<TipoProveedor, string> = {
    proveedor: 'Proveedor',
    tercero: 'Tercero',
    servicio: 'Servicio',
};

export type FormaPago = 'transferencia' | 'cheque_efectivo';

export const FORMA_PAGO_LABELS: Record<FormaPago, string> = {
    transferencia: 'Transferencia',
    cheque_efectivo: 'Cheque / Efectivo',
};

export type Banco = {
    id: number;
    nombre: string;
    digitos_cuenta: number | null;
    es_pagador: boolean;
    activo: boolean;
    created_at: string;
    updated_at: string;
};

export type RegimenFiscal = {
    id: number;
    clave: string;
    descripcion: string;
    aplica_persona_fisica: boolean;
    aplica_persona_moral: boolean;
    activo: boolean;
    created_at: string;
    updated_at: string;
};

export type Proveedor = {
    id: number;
    codigo: string;
    razon_social: string;
    nombre_comercial: string | null;
    rfc: string;
    tipo_persona: string | null;
    regimen_fiscal_id: number | null;
    codigo_postal: string | null;
    domicilio_fiscal: string | null;
    domicilio_compra: string | null;
    giro: string | null;
    direccion: string | null;
    telefono: string | null;
    email: string | null;
    contacto_nombre: string | null;
    contacto_correo: string | null;
    banco_nombre: string | null;
    banco_id: number | null;
    titular_cuenta: string | null;
    numero_cuenta: string | null;
    clabe: string | null;
    tarjeta: string | null;
    moneda_cuenta: string | null;
    forma_pago: FormaPago | null;
    numero_servicio: string | null;
    referencia_servicio: string | null;
    tiene_acceso_portal: boolean;
    maneja_credito: boolean;
    limite_credito: number;
    dias_credito_default: number;
    respetar_fecha_factura: boolean;
    tipo_proveedor: TipoProveedor | null;
    activo: boolean;
    estatus: ProveedorEstatus;
    validado_por: string | null;
    validado_at: string | null;
    observacion_validacion: string | null;
    creado_por: string | null;
    bloqueado_complemento?: boolean;
    regimen_fiscal?: RegimenFiscal;
    banco?: Banco | null;
    validador?: Usuario;
    media?: Media[];
    complementos_pago?: CostosComplementoPago[];
    created_at: string;
    updated_at: string;
};

export type CostosTipoRubro = {
    id: number;
    descripcion: string;
    rubros_count?: number;
    created_at: string;
    updated_at: string;
};

// ===== Cotización (cotiz_) =====

export type CotizUnidad = {
    id: number;
    descripcion: string;
    created_at: string;
    updated_at: string;
};

export type CotizCentroCosto = {
    id: number;
    cod_coste: string;
    concepto: string;
    created_at: string;
    updated_at: string;
};

export type CotizCategoriaTarjeta = {
    id: number;
    descripcion: string;
    orden: number;
    created_at: string;
    updated_at: string;
};

export type CotizInsumo = {
    id: number;
    descripcion: string;
    codigo_stumis: string | null;
    unidad_id: number;
    precio_unitario: string;
    peso_lineal: string | null;
    peso_default: string | null;
    centro_costo_id: number;
    categoria_tarjeta_id: number | null;
    unidad?: CotizUnidad;
    centro_costo?: CotizCentroCosto;
    categoria_tarjeta?: CotizCategoriaTarjeta;
    created_at: string;
    updated_at: string;
};

export type CotizMerma = {
    id: number;
    descripcion: string;
    formula: string;
    created_at: string;
    updated_at: string;
};

export type CotizFactor = {
    id: number;
    codigo: string;
    nombre: string;
    insumo_id: number;
    formula: string | null;
    descripcion: string | null;
    categoria_tarjeta_id: number | null;
    insumo?: CotizInsumo;
    categoria_tarjeta?: CotizCategoriaTarjeta;
    created_at: string;
    updated_at: string;
};

export type CotizPinturaFormula = {
    id: number;
    clave: string;
    nombre: string;
    formula: string;
    orden: number;
    created_at: string;
    updated_at: string;
};

export type CotizTipoCorte = 'TIRAS' | 'RAZ' | 'KG' | 'CNX';

export type CotizKilosRealesCategoria = {
    id: number;
    descripcion: string;
    tipo_corte: CotizTipoCorte;
    orden: number;
    created_at: string;
    updated_at: string;
};

export type CotizCuadrilla = {
    id: number;
    codigo: string;
    nombre: string;
    centro_costo_id: number;
    insumo_id: number | null;
    rendimiento: string | null;
    formula_costo: string | null;
    descripcion: string | null;
    centro_costo?: CotizCentroCosto;
    insumo?: CotizInsumo;
    created_at: string;
    updated_at: string;
};

export type CotizPersonalCategoria = {
    id: number;
    codigo: string;
    nombre: string;
    sueldo_semanal: string;
    orden: number;
    created_at: string;
    updated_at: string;
};

export type CotizFaseMontaje = {
    id: number;
    codigo: string;
    nombre: string;
    unidad: string;
    centro_costo_id: number | null;
    orden: number;
    centro_costo?: CotizCentroCosto;
    created_at: string;
    updated_at: string;
};

export type CotizGrupoFlete =
    | 'VIATICOS'
    | 'SUPERV_MONTAJE'
    | 'ENERGIA'
    | 'VARIOS'
    | 'FLETES'
    | 'GRUAS'
    | 'PLATAFORMAS'
    | 'LABORATORIO'
    | 'TOPOGRAFIA';

export type CotizMetodoFleteEstandar = 'por_kg' | 'por_piezas';

export type CotizFleteViaticoCatalogo = {
    id: number;
    grupo: CotizGrupoFlete;
    orden: number;
    concepto: string;
    unidad: string | null;
    p_unit_default: string;
    notas: string | null;
    clave: string | null;
    formula_cantidad: string | null;
    formula_p_unit: string | null;
    created_at: string;
    updated_at: string;
};

export type CotizResumenBloque = 'MO_FAB' | 'MO_MONTAJE' | 'EXTRAS' | 'TOTALES';

export type CotizResumenTipoFormula =
    | 'materiales'
    | 'por_kg'
    | 'por_m2_pintura'
    | 'mo_fab_subgrupo'
    | 'flete_kg_prorrateado'
    | 'viatico_m2_prorrateado'
    | 'subtotal'
    | 'margen'
    | 'total';

export type CotizResumenFila = {
    id: number;
    descripcion: string;
    bloque: CotizResumenBloque;
    tipo_formula: CotizResumenTipoFormula;
    coef_default: string | null;
    referencia_extra: string | null;
    orden: number;
    bloqueada: boolean;
    created_at: string;
    updated_at: string;
};

export type CotizObra = {
    id: number;
    nombre: string;
    op: string | null;
    factor_contratista: string;
    num_grupos: number;
    generadoras_count?: number;
    created_at: string;
    updated_at: string;
};

export type CotizLockUser = {
    id: string;
    name: string;
};

export type CotizGeneradora = {
    id: number;
    obra_id: number;
    titulo: string;
    orden: number;
    registros_count?: number;
    is_locked?: boolean;
    locked_by?: CotizLockUser | null;
    locked_at?: string | null;
    obra?: CotizObra;
    created_at: string;
    updated_at: string;
};

export type CotizGeneradoraRegistro = {
    id: number;
    generadora_id: number;
    material_origen_id: number | null;
    material: string | null;
    marca: string | null;
    ancho: string | null;
    largo: string | null;
    cantidad: string | null;
    cant_pzas: string | null;
    peso_porcentual: string | null;
    kilos_totales: string | null;
    merma_id: number;
    validado: boolean;
    t_ml_m2: number | null;
    kilos_reales: number | null;
    kilos_con_merma: number;
    material_origen?: CotizInsumo | null;
    merma?: CotizMerma;
};

export type CotizObraInsumoOverride = {
    id: number;
    obra_id: number;
    insumo_id: number;
    descripcion: string | null;
    codigo_stumis: string | null;
    unidad_id: number | null;
    precio_unitario: string | null;
    peso_lineal: string | null;
    peso_default: string | null;
    centro_costo_id: number | null;
    comentario: string | null;
};

export type CotizObraFactorOverride = {
    id: number;
    obra_id: number;
    factor_id: number;
    nombre: string | null;
    insumo_id: number | null;
    formula: string | null;
    descripcion: string | null;
    comentario: string | null;
};

export type CotizCatalogoInsumoRow = {
    insumo: CotizInsumo;
    override: CotizObraInsumoOverride | null;
};

export type CotizCatalogoFactorRow = {
    factor: CotizFactor;
    override: CotizObraFactorOverride | null;
};

export type CotizTarjeta = {
    id: number;
    obra_id: number;
    descripcion: string;
    orden: number;
    registros_count?: number;
    generadoras_count?: number;
    importe_materiales: string | null;
    kilos_reales: string | null;
    is_locked?: boolean;
    locked_by?: CotizLockUser | null;
    locked_at?: string | null;
    obra?: CotizObra;
    generadoras?: Pick<CotizGeneradora, 'id' | 'titulo' | 'orden'>[];
};

/** Registro de tarjeta ya resuelto por el TarjetaCalculator (no es un modelo crudo). */
export type CotizTarjetaRegistroResuelto = {
    id: number;
    es_manual: boolean;
    generadora_titulo: string | null;
    marca: string | null;
    insumo_id: number | null;
    descripcion: string;
    codigo_stumis: string | null;
    unidad: string | null;
    categoria: string | null;
    categoria_orden: number;
    peso_lineal: number | null;
    kilos_reales: number | null;
    tipo_pintura: string;
    cantidad: number;
    precio_unitario: number;
    precio_obra: number;
    precio_global: number;
    importe: number;
    importe_sugerido: number;
    area_pintura: number;
    validado: boolean;
};

export type CotizTarjetaFactorResuelto = {
    id: number;
    factor_id: number;
    codigo: string;
    nombre: string;
    formula: string | null;
    formula_global: string | null;
    categoria: string | null;
    categoria_orden: number;
    cantidad: number;
    precio_unitario: number;
    precio_obra: number;
    precio_global: number;
    importe: number;
    importe_sugerido: number;
    validado: boolean;
};

export type CotizTarjetaEstructura = {
    id: number;
    nombre: string;
    orden: number;
};

export type CotizTarjetaCategoriaKilos = {
    id: number;
    categoria_id: number;
    descripcion: string | null;
    tipo_corte: string | null;
    porcentual: string | null;
    orden: number;
};

export type CotizTarjetaKilosCelda = {
    id: number;
    categoria_id: number;
    estructura_id: number;
    kilos: string;
};

export type CostosRubro = {
    id: number;
    codigo: string;
    descripcion: string;
    ambito: 'obra' | 'planta';
    ocultar_en_reporte: boolean;
    tipo_rubro_id: number;
    departamento_id: number | null;
    tipo_rubro?: CostosTipoRubro;
    departamento?: Departamento;
    created_at: string;
    updated_at: string;
};

export type CostosTipoSolicitud = {
    id: number;
    titulo: string;
    descripcion: string | null;
    rubros: boolean;
    saltar_verificacion_costos: boolean;
    documentos?: CostosDocumento[];
    documentos_count?: number;
    created_at: string;
    updated_at: string;
};

export type CostosDocumento = {
    id: number;
    tipo_solicitud_id: number;
    titulo: string;
    multiple: boolean;
    opcional: boolean;
    texto: string | null;
    texto_adicional: boolean;
    created_at: string;
    updated_at: string;
};

export type CostosObraRubro = {
    id: number;
    presupuesto_id: number;
    obra_id: number | null;
    rubro_id: number;
    presupuestado: number;
    acumulado: number;
    apartado: number;
    comprometido?: number;
    disponible?: number;
    rubro?: CostosRubro;
    obra?: Obra;
    presupuesto?: CostosPresupuesto;
    created_at: string;
    updated_at: string;
};

export type PresupuestableTipo = 'proyecto' | 'obra' | 'partida';

export type CostosPresupuestoEstatus = 'activo' | 'cerrado';

export type CostosPresupuesto = {
    id: number;
    presupuestable_type: string;
    presupuestable_id: number;
    nombre_interno: string | null;
    op_interno: string | null;
    estatus: CostosPresupuestoEstatus;
    nombre_mostrar?: string;
    op_mostrar?: string | null;
    descripcion_mostrar?: string | null;
    presupuestable?: { id: number; no?: string | null; descripcion?: string | null; estatus?: string };
    created_at: string;
    updated_at: string;
};

/** Opción de presupuesto para el selector de cabecera de requisición. */
export type PresupuestoOption = {
    id: number;
    label: string;
    cerrado: boolean;
};

/** Fila de presupuesto presentada por PresupuestoController (index/edit). */
export type PresupuestoRow = {
    id: number;
    tipo: PresupuestableTipo;
    presupuestable_id: number;
    nombre: string;
    nombre_interno: string | null;
    op: string | null;
    op_interno: string | null;
    no: string | null;
    descripcion: string | null;
    estatus: CostosPresupuestoEstatus;
    es_planta: boolean;
    rubros_count: number;
    sum_presupuestado: number;
    sum_acumulado: number;
    sum_apartado?: number;
};

export type CostosPermiso = {
    id: number;
    descripcion: string;
    nivel: number;
    tipo_aprobacion: 'solicitud_pago' | 'requisicion';
    omitir_si_presupuesto_reservado: boolean;
    es_costos: boolean;
    created_at: string;
    updated_at: string;
};

export type CostosProductoPrecio = {
    id: number;
    producto_id: number;
    proveedor_id: number | null;
    precio: number | string;
    moneda: CostosTipoMoneda;
    fecha: string;
    requisicion_id: number | null;
    proveedor?: Pick<Proveedor, 'id' | 'razon_social'>;
    created_at: string;
};

export type CostosProducto = {
    id: number;
    codigo: string | null;
    descripcion: string;
    unidad: string;
    activo: boolean;
    creado_por: string | null;
    precios?: CostosProductoPrecio[];
    precios_count?: number;
    creador?: Pick<Usuario, 'id' | 'name'>;
    created_at: string;
    updated_at: string;
};

export type CostosAprobacionDepartamento = {
    id: number;
    departamento_id: number;
    permiso_id: number;
    aprobador_id: string | null;
    permiso?: CostosPermiso;
    departamento?: Departamento;
    aprobador?: Usuario;
    created_at: string;
    updated_at: string;
};

export type CostosSolicitudPagoEstatus = 'borrador' | 'pendiente_firma' | 'aprobada' | 'pagada' | 'cancelada';

export const SOLICITUD_PAGO_ESTATUS_LABELS: Record<CostosSolicitudPagoEstatus, string> = {
    borrador: 'Borrador',
    pendiente_firma: 'Pendiente Firma',
    aprobada: 'Aprobada',
    pagada: 'Pagada',
    cancelada: 'Cancelada',
};

export const SOLICITUD_PAGO_ESTATUS_COLORS: Record<CostosSolicitudPagoEstatus, string> = {
    borrador: 'badge-ghost',
    pendiente_firma: 'badge-warning',
    aprobada: 'badge-success',
    pagada: 'badge-info',
    cancelada: 'badge-error',
};

export type CostosTipoMoneda = 'mxn' | 'usd' | 'eur';

export const TIPO_MONEDA_LABELS: Record<CostosTipoMoneda, string> = {
    mxn: 'MXN',
    usd: 'USD',
    eur: 'EUR',
};

// Devoluciones a proveedor (Fase 13)
export type CostosDevolucionEstatus = 'vigente' | 'cancelada';

export const DEVOLUCION_ESTATUS_LABELS: Record<CostosDevolucionEstatus, string> = {
    vigente: 'Vigente',
    cancelada: 'Cancelada',
};

export const DEVOLUCION_ESTATUS_COLORS: Record<CostosDevolucionEstatus, string> = {
    vigente: 'badge-success',
    cancelada: 'badge-error',
};

export type CostosDevolucion = {
    id: number;
    folio: string;
    entrega_detalle_id: number;
    cantidad: number;
    motivo: string;
    fecha: string;
    estatus: CostosDevolucionEstatus;
    motivo_cancelacion: string | null;
    creado_por: string | null;
    entrega_detalle?: {
        id: number;
        cantidad_recibida: number;
        entrega?: { id: number; orden_compra_id: number; fecha_entrega: string; orden_compra?: Pick<CostosOrdenCompra, 'id' | 'folio'> & { proveedor?: Pick<Proveedor, 'id' | 'razon_social'> } };
        orden_compra_detalle?: Pick<CostosOrdenCompraDetalle, 'id' | 'descripcion' | 'unidad'>;
    };
    creador?: Pick<Usuario, 'id' | 'name'>;
    media?: Media[];
    evidencia?: Media | null;
    activities?: CostosActivity[];
    created_at: string;
    updated_at: string;
};

// Notas de crédito (Fase 12)
export type CostosNotaCreditoEstatus = 'vigente' | 'cancelada';

export const NOTA_CREDITO_ESTATUS_LABELS: Record<CostosNotaCreditoEstatus, string> = {
    vigente: 'Vigente',
    cancelada: 'Cancelada',
};

export const NOTA_CREDITO_ESTATUS_COLORS: Record<CostosNotaCreditoEstatus, string> = {
    vigente: 'badge-success',
    cancelada: 'badge-error',
};

export type CostosNotaCredito = {
    id: number;
    folio: string;
    factura_id: number;
    uuid_fiscal: string | null;
    folio_fiscal: string | null;
    subtotal: number;
    iva_trasladado: number;
    monto: number;
    impuestos_detalle: CostosImpuestosDetalle | null;
    concepto: string;
    fecha_emision: string;
    estatus: CostosNotaCreditoEstatus;
    motivo_cancelacion: string | null;
    creado_por: string | null;
    factura?: Pick<CostosFactura, 'id' | 'folio' | 'total' | 'proveedor_id'> & { proveedor?: Pick<Proveedor, 'id' | 'razon_social'> };
    creador?: Pick<Usuario, 'id' | 'name'>;
    media?: Media[];
    media_xml?: Media | null;
    media_pdf?: Media | null;
    activities?: CostosActivity[];
    created_at: string;
    updated_at: string;
};

// Anticipos (Fase 11)
export type CostosAnticipoEstatus = 'vigente' | 'agotado' | 'cancelado';

export const ANTICIPO_ESTATUS_LABELS: Record<CostosAnticipoEstatus, string> = {
    vigente: 'Vigente',
    agotado: 'Agotado',
    cancelado: 'Cancelado',
};

export const ANTICIPO_ESTATUS_COLORS: Record<CostosAnticipoEstatus, string> = {
    vigente: 'badge-success',
    agotado: 'badge-neutral',
    cancelado: 'badge-error',
};

export type CostosAnticipo = {
    id: number;
    folio: string;
    proveedor_id: number;
    obra_id: number | null;
    monto: number;
    saldo_disponible: number;
    moneda: string;
    estatus: CostosAnticipoEstatus;
    referencia: string | null;
    fecha: string;
    notas: string | null;
    creado_por: string | null;
    locked_by: string | null;
    locked_at: string | null;
    proveedor?: Pick<Proveedor, 'id' | 'razon_social' | 'nombre_comercial'>;
    obra?: { id: number; descripcion: string };
    creador?: Pick<Usuario, 'id' | 'name'>;
    aplicaciones?: CostosAnticipoAplicacion[];
    pago?: CostosPago;
    activities?: CostosActivity[];
    created_at: string;
    updated_at: string;
};

export type CostosAnticipoAplicacion = {
    id: number;
    anticipo_id: number;
    factura_id: number;
    monto: number;
    fecha: string;
    usuario_id: string | null;
    notas: string | null;
    factura?: Pick<CostosFactura, 'id' | 'folio' | 'total'>;
    anticipo?: Pick<CostosAnticipo, 'id' | 'folio' | 'monto'>;
    usuario?: Pick<Usuario, 'id' | 'name'>;
    created_at: string;
    updated_at: string;
};

// Requisiciones (Fase 10.2)
export type CostosRequisicionEstatus =
    | 'borrador'
    | 'pendiente_aprobacion_interno'
    | 'aprobada_interna'
    | 'pendiente_aprobacion'
    | 'aprobada'
    | 'rechazada'
    | 'liberada'
    | 'cancelada';

export const REQUISICION_ESTATUS_LABELS: Record<CostosRequisicionEstatus, string> = {
    borrador: 'Borrador',
    pendiente_aprobacion_interno: 'Pendiente de aprobación interna',
    aprobada_interna: 'Aprobada interna',
    pendiente_aprobacion: 'Pendiente de aprobación',
    aprobada: 'Aprobada',
    rechazada: 'Rechazada',
    liberada: 'Liberada',
    cancelada: 'Cancelada',
};

export const REQUISICION_ESTATUS_COLORS: Record<CostosRequisicionEstatus, string> = {
    borrador: 'badge-ghost',
    pendiente_aprobacion_interno: 'badge-secondary',
    aprobada_interna: 'badge-info',
    pendiente_aprobacion: 'badge-warning',
    aprobada: 'badge-success',
    rechazada: 'badge-error',
    liberada: 'badge-primary',
    cancelada: 'badge-neutral',
};

export type ModoPago = 'contado' | 'credito';

export const MODO_PAGO_LABELS: Record<ModoPago, string> = {
    contado: 'Contado',
    credito: 'Crédito',
};

export type ObraRubroOption = {
    id: number;
    presupuesto_id: number;
    presupuesto_label: string;
    rubro_label: string;
    label: string;
    presupuestado: number;
    acumulado: number;
    apartado: number;
    comprometido: number;
    disponible: number;
    sobregiro: boolean;
    cerrado: boolean;
};

export type CostosUsoCfdi = {
    id: number;
    clave: string;
    descripcion: string;
    activo: boolean;
    requisicion_detalles_count?: number;
    created_at: string;
    updated_at: string;
};

export type CostosRequisicion = {
    id: number;
    folio: string;
    solicitante_id: string;
    firma_adicional_aprobador_id: string | null;
    departamento_id: number;
    obra_id: number | null;
    presupuesto_id: number | null;
    justificacion: string | null;
    tipo_cambio: number;
    fecha_requerida: string | null;
    estatus: CostosRequisicionEstatus;
    control_por: string | null;
    control_at: string | null;
    modo_dedazo: boolean;
    sobre_obra_cerrada: boolean;
    motivo_rechazo: string | null;
    locked_by: string | null;
    locked_at: string | null;
    solicitante?: Pick<Usuario, 'id' | 'name'>;
    firma_adicional_aprobador?: Pick<Usuario, 'id' | 'name'> | null;
    controlador?: Pick<Usuario, 'id' | 'name'> | null;
    departamento?: Pick<Departamento, 'id' | 'descripcion'>;
    obra?: { id: number; no: number | null; descripcion: string };
    presupuesto?: CostosPresupuesto;
    detalles?: CostosRequisicionDetalle[];
    cotizacion_opciones?: CostosRequisicionCotizacionOpcion[];
    aprobaciones?: CostosAprobacionSolicitud[];
    ocs?: CostosRequisicionOc[];
    ordenes_generadas?: Array<Pick<CostosOrdenCompra, 'id' | 'folio' | 'proveedor_id' | 'total' | 'estatus'> & { proveedor?: Pick<Proveedor, 'id' | 'razon_social'>; solicitudes_pago?: Array<Pick<CostosSolicitudPago, 'id' | 'folio' | 'estatus' | 'monto_total'>> }>;
    mejor_proveedor?: {
        id: number;
        razon_social: string;
        nombre_comercial: string | null;
        total: number;
    } | null;
    proveedores_cotizadores_count?: number;
    // Neto a pagar (subtotal + IVA - retenciones); 0 mientras no haya OC definida.
    total_neto?: number;
    tiene_sobregiro?: boolean;
    media?: Media[];
    activities?: CostosActivity[];
    created_at: string;
    updated_at: string;
};

export type CostosRequisicionDetalle = {
    id: number;
    requisicion_id: number;
    producto_id: number | null;
    obra_rubro_id: number | null;
    uso_cfdi_id: number | null;
    tipo_fiscal: CostosTipoFiscalPartida;
    descripcion: string;
    codigo_producto: string | null;
    unidad: string;
    cantidad: number;
    /** Partida de referencia (ej. flete variable): se cotiza pero no se adjudica, no entra al comparativo/PDF ni al neto. */
    solo_cotizacion: boolean;
    notas: string | null;
    uso_cfdi?: Pick<CostosUsoCfdi, 'id' | 'clave' | 'descripcion'>;
    obra_rubro?: {
        id: number;
        presupuestado: number | string;
        acumulado: number | string;
        obra?: { id: number; descripcion: string };
        presupuesto?: CostosPresupuesto;
        rubro?: { id: number; codigo: string; descripcion: string };
    };
    cotizaciones?: CostosRequisicionCotizacionPrecio[];
    selecciones?: CostosRequisicionSeleccion[];
    created_at: string;
    updated_at: string;
};

export type CostosRequisicionCotizacionPrecio = {
    id: number;
    requisicion_detalle_id: number;
    proveedor_id: number;
    opcion_id: number | null;
    precio_unitario: number;
    descripcion: string | null;
    codigo_producto: string | null;
    moneda: CostosTipoMoneda;
    tiempo_entrega_dias: number | null;
    observaciones: string | null;
    media_id: number | null;
    proveedor?: Pick<Proveedor, 'id' | 'razon_social' | 'nombre_comercial'>;
    created_at: string;
    updated_at: string;
};

export type CostosRequisicionCotizacionOpcion = {
    id: number;
    requisicion_id: number;
    proveedor_id: number;
    etiqueta: string | null;
    orden: number;
    proveedor?: Pick<Proveedor, 'id' | 'razon_social' | 'nombre_comercial'>;
    created_at: string;
    updated_at: string;
};

export type CostosRequisicionOcPago = {
    porcentaje: number;
    concepto: string | null;
};

export type CostosRequisicionOc = {
    id: number;
    requisicion_id: number;
    proveedor_id: number;
    numero_oc: number;
    modo_pago: ModoPago;
    metodo_pago: 'transferencia' | 'cheque' | 'efectivo';
    fecha_entrega: string | null;
    fecha_pago: string | null;
    notas: string | null;
    pagos: CostosRequisicionOcPago[] | null;
    created_at: string;
    updated_at: string;
};

export type CostosRequisicionSeleccion = {
    id: number;
    requisicion_detalle_id: number;
    cotizacion_precio_id: number;
    numero_oc: number;
    proveedor_id: number;
    cantidad: number;
    obra_rubro_id: number | null;
    orden_compra_detalle_id: number | null;
    cotizacion_precio?: CostosRequisicionCotizacionPrecio;
    proveedor?: Pick<Proveedor, 'id' | 'razon_social'>;
    created_at: string;
    updated_at: string;
};

export type CostosSolicitudPago = {
    id: number;
    folio: string;
    solicitante_id: string;
    firma_adicional_aprobador_id: string | null;
    departamento_id: number;
    proveedor_id: number | null;
    orden_compra_id: number | null;
    tipo_solicitud_id: number;
    concepto: string;
    comentarios: string | null;
    monto_total: number;
    tipo_pago: string;
    tipo_moneda: CostosTipoMoneda;
    fecha_pago_solicitada: string | null;
    fecha_pago_realizada: string | null;
    referencia_pago: string | null;
    estatus: CostosSolicitudPagoEstatus;
    confirmada_costos: boolean;
    confirmada_costos_por: string | null;
    confirmada_costos_at: string | null;
    confirmada_contabilidad: boolean;
    confirmada_contabilidad_por: string | null;
    confirmada_contabilidad_at: string | null;
    solicitante?: Usuario;
    firma_adicional_aprobador?: Pick<Usuario, 'id' | 'name'> | null;
    departamento?: Departamento;
    proveedor?: Proveedor;
    tipo_solicitud?: CostosTipoSolicitud;
    orden_compra?: Pick<CostosOrdenCompra, 'id' | 'folio'>;
    detalles?: CostosSolicitudPagoDetalle[];
    archivos?: CostosSolicitudArchivo[];
    aprobaciones?: CostosAprobacionSolicitud[];
    pago?: CostosPago;
    media?: Media;
    confirmador_costos?: Usuario;
    confirmador_contabilidad?: Usuario;
    tiene_sobregiro?: boolean;
    puede_reasignar?: boolean;
    activities?: CostosActivity[];
    locked_by: string | null;
    locked_at: string | null;
    locked_by_user?: Pick<Usuario, 'id' | 'name'> | null;
    created_at: string;
    updated_at: string;
};

/**
 * Fila de la bandeja "Por confirmar" (puntos de control Costos/Contabilidad).
 * Forma normalizada que sirve tanto para Solicitudes de Pago como Facturas.
 */
export type CostosPuntoControl = {
    tipo: 'solicitud_pago' | 'factura';
    paso: 'costos' | 'contabilidad';
    id: number;
    folio: string;
    proveedor: string | null;
    concepto: string | null;
    monto: number;
    moneda: string;
    fecha: string | null;
    accion_url: string;
    detalle_href: string;
};

export type CostosSolicitudPagoDetalle = {
    id: number;
    solicitud_id: number;
    obra_rubro_id: number;
    sobre_obra_cerrada: boolean;
    concepto: string;
    cantidad: number;
    precio_unitario: number;
    subtotal: number;
    obra_rubro?: CostosObraRubro;
    created_at: string;
    updated_at: string;
};

export type CostosSolicitudArchivo = {
    id: number;
    solicitud_id: number;
    media_id: number;
    archivo_id: number;
    texto_adicional: string | null;
    tags: Record<string, string> | null;
    documento?: CostosDocumento;
    media?: Media;
    created_at: string;
    updated_at: string;
};

export type CostosAprobacionSolicitud = {
    id: number;
    solicitud_id: number;
    nivel: number;
    es_adicional?: boolean;
    aprobador_id: string | null;
    estatus: string;
    fecha_respuesta: string | null;
    observaciones: string | null;
    ip: string | null;
    hostname: string | null;
    aprobador?: Usuario;
    // Discriminador para la bandeja polimórfica
    tipo?: 'solicitud_pago' | 'requisicion';
    aprobable_type?: string;
    aprobable_id?: number;
    solicitud?: CostosSolicitudPago;
    requisicion?: CostosRequisicion;
    // Total neto a pagar de la requisición (subtotal + IVA - retenciones)
    requisicion_total?: number;
    created_at: string;
    updated_at: string;
};

// Afectaciones Presupuestales Types
export type CostosAfectacionEstatus = 'borrador' | 'pendiente_firma' | 'aprobada' | 'cancelada';

export const AFECTACION_ESTATUS_LABELS: Record<CostosAfectacionEstatus, string> = {
    borrador: 'Borrador',
    pendiente_firma: 'Pendiente Firma',
    aprobada: 'Aprobada',
    cancelada: 'Cancelada',
};

export const AFECTACION_ESTATUS_COLORS: Record<CostosAfectacionEstatus, string> = {
    borrador: 'badge-ghost',
    pendiente_firma: 'badge-warning',
    aprobada: 'badge-success',
    cancelada: 'badge-error',
};

export type CostosAfectacionPresupuestal = {
    id: number;
    folio: string;
    fecha: string;
    tipo_origen: string;
    descripcion: string;
    monto_total: number;
    estatus: CostosAfectacionEstatus;
    proveedor_id: number | null;
    departamento_id: number | null;
    creado_por: string | Usuario;
    aprobado_por: string | Usuario | null;
    fecha_aprobacion: string | null;
    pdf_formato_path: string | null;
    pdf_firmado_path: string | null;
    departamento?: Departamento;
    proveedor?: Proveedor;
    media?: Media[];
    detalles?: CostosAfectacionDetalle[];
    historial?: CostosAfectacionHistorial[];
    rubros_afectados?: CostosRubroAfectado[];
    locked_by: string | null;
    locked_at: string | null;
    locked_by_user?: Pick<Usuario, 'id' | 'name'> | null;
    created_at: string;
    updated_at: string;
};

export type CostosAfectacionDetalle = {
    id: number;
    afectacion_id: number;
    obra_rubro_id: number;
    concepto: string | null;
    cantidad: number | null;
    precio_unitario: number | null;
    monto: number;
    obra_rubro?: CostosObraRubro;
    created_at: string;
    updated_at: string;
};

export type CostosAfectacionHistorial = {
    id: number;
    afectacion_id: number;
    estatus_anterior: string;
    estatus_nuevo: string;
    fecha: string;
    usuario_id: string | null;
    observaciones: string | null;
    usuario?: Usuario;
    created_at: string;
    updated_at: string;
};

export type CostosRubroAfectadoEstatus = 'apartado' | 'aplicado' | 'vencido' | 'cancelado';

export const RUBRO_AFECTADO_ESTATUS_LABELS: Record<CostosRubroAfectadoEstatus, string> = {
    apartado: 'Apartado',
    aplicado: 'Aplicado',
    vencido: 'Vencido',
    cancelado: 'Cancelado',
};

export const RUBRO_AFECTADO_ESTATUS_BADGE: Record<CostosRubroAfectadoEstatus, string> = {
    apartado: 'badge badge-info badge-outline',
    aplicado: 'badge badge-success badge-outline',
    vencido: 'badge badge-warning badge-outline',
    cancelado: 'badge badge-ghost',
};

export type CostosRubroAfectado = {
    id: number;
    entrada_type: string;
    entrada_id: number;
    obra_rubro_id: number;
    monto: number;
    sobre_giro: boolean;
    descripcion: string | null;
    tipo_movimiento: string;
    estatus: CostosRubroAfectadoEstatus;
    apartado_hasta: string | null;
    vencido_at: string | null;
    usuario_aplica_id: string | null;
    fecha_aplicacion: string | null;
    obra_rubro?: CostosObraRubro;
    created_at: string;
    updated_at: string;
};

// Ordenes de Compra Types
export type CostosOrdenCompraEstatus = 'pendiente_entrega' | 'pendiente_factura' | 'pendiente_aprobacion' | 'pendiente_pago' | 'pagada' | 'cancelada';

export type CostosOcEtapaProceso = 'recepcion' | 'espera_factura' | 'validacion_documentos' | 'pago_programado' | 'completada' | 'cancelada';

export const OC_ETAPA_LABELS: Record<CostosOcEtapaProceso, string> = {
    recepcion: 'Recepción',
    espera_factura: 'Espera de factura',
    validacion_documentos: 'Validación de documentos',
    pago_programado: 'Pago programado',
    completada: 'Completada',
    cancelada: 'Cancelada',
};

export const OC_ETAPA_BADGE: Record<CostosOcEtapaProceso, string> = {
    recepcion: 'badge badge-info badge-outline',
    espera_factura: 'badge badge-warning badge-outline',
    validacion_documentos: 'badge badge-accent badge-outline',
    pago_programado: 'badge badge-primary badge-outline',
    completada: 'badge badge-success badge-outline',
    cancelada: 'badge badge-ghost',
};

export const ORDEN_COMPRA_ESTATUS_LABELS: Record<CostosOrdenCompraEstatus, string> = {
    pendiente_entrega: 'Pend. Entrega',
    pendiente_factura: 'Pend. Factura',
    pendiente_aprobacion: 'Pend. Aprobación',
    pendiente_pago: 'Pend. Pago',
    pagada: 'Pagada',
    cancelada: 'Cancelada',
};

export const ORDEN_COMPRA_ESTATUS_COLORS: Record<CostosOrdenCompraEstatus, string> = {
    pendiente_entrega: 'badge-info',
    pendiente_factura: 'badge-warning',
    pendiente_aprobacion: 'badge-accent',
    pendiente_pago: 'badge-primary',
    pagada: 'badge-success',
    cancelada: 'badge-error',
};

export type CostosOrdenCompra = {
    id: number;
    folio: string;
    referencia: string | null;
    proveedor_id: number;
    obra_id: number | null;
    departamento_id: number;
    creado_por: string;
    moneda: CostosTipoMoneda;
    tipo_pago: ModoPago | null;
    dias_credito: number;
    forma_pago: string;
    total: number;
    fecha_entrega_esperada: string;
    notas: string | null;
    estatus: CostosOrdenCompraEstatus;
    retrasada?: boolean;
    etapa_proceso?: CostosOcEtapaProceso;
    monto_recibido?: number;
    porcentaje_recepcion?: number;
    porcentaje_facturacion?: number;
    porcentaje_pago?: number;
    pago_vencido?: boolean;
    tiene_devolucion?: boolean;
    pagada_anticipo_contado?: boolean;
    presupuesto_label?: string;
    detalles_count?: number;
    requisicion_id: number | null;
    requisicion?: { id: number; obra?: Pick<Obra, 'id' | 'no' | 'descripcion'> };
    proveedor?: Proveedor;
    obra?: Obra;
    departamento?: Departamento;
    creador?: Usuario;
    detalles?: CostosOrdenCompraDetalle[];
    facturas?: CostosFactura[];
    entregas?: CostosEntrega[];
    solicitudes_pago?: Array<Pick<CostosSolicitudPago, 'id' | 'folio' | 'estatus' | 'pago'>>;
    media?: Media[];
    rubros_afectados?: CostosRubroAfectado[];
    facturas_count?: number;
    entregas_count?: number;
    pagos_count?: number;
    total_facturado?: number;
    total_pagado?: number;
    saldo_pendiente?: number;
    activities?: CostosActivity[];
    created_at: string;
    updated_at: string;
};

export type CostosOrdenCompraDetalle = {
    id: number;
    orden_compra_id: number;
    requisicion_detalle_id: number | null;
    obra_rubro_id: number;
    tipo_fiscal: CostosTipoFiscalPartida;
    descripcion: string;
    unidad: string;
    cantidad: number;
    precio_unitario: number;
    subtotal: number;
    uso_cfdi_id: number | null;
    obra_rubro?: CostosObraRubro;
    uso_cfdi?: Pick<CostosUsoCfdi, 'id' | 'clave' | 'descripcion'>;
    created_at: string;
    updated_at: string;
};

export type CostosTipoFiscalPartida = 'mercancia' | 'flete' | 'servicio_profesional' | 'renta';

export const TIPO_FISCAL_LABELS: Record<CostosTipoFiscalPartida, string> = {
    mercancia: 'Mercancía',
    flete: 'Flete',
    servicio_profesional: 'Servicio profesional',
    renta: 'Renta',
};

export type CostosRetencion = {
    clave: string;
    concepto: string;
    tasa: number;
    base: number;
    monto: number;
};

export type CostosRetencionDesglose = {
    subtotal: number;
    iva: number;
    total_neto: number;
    retenciones: CostosRetencion[];
};

// Facturas Types
export type CostosFacturaEstatus = 'pendiente_recepcion' | 'pendiente_aprobacion' | 'pendiente_pago' | 'pagada' | 'cancelada';

export type CostosBaseDiasCredito = 'factura' | 'recepcion' | 'aprobacion';

export const BASE_DIAS_CREDITO_LABELS: Record<CostosBaseDiasCredito, string> = {
    factura: 'Fecha de factura',
    recepcion: 'Fecha de recepción',
    aprobacion: 'Fecha de aprobación',
};

// Clasificación canónica de archivos adjuntos del módulo Costos.
// Espejo de App\Enums\Costos\DocumentoTipo.
export type CostosDocumentoTipo =
    | 'xml_factura'
    | 'pdf_factura'
    | 'oc_archivo'
    | 'oc_pdf_formato'
    | 'oc_pdf_firmado'
    | 'evidencia_recepcion'
    | 'comprobante_recepcion'
    | 'comprobante_pago'
    | 'solicitud_archivo'
    | 'solicitud_firmada';

export const DOCUMENTO_TIPO_LABELS: Record<CostosDocumentoTipo, string> = {
    xml_factura: 'XML de factura',
    pdf_factura: 'PDF de factura',
    oc_archivo: 'Archivo de OC',
    oc_pdf_formato: 'Formato de OC (PDF)',
    oc_pdf_firmado: 'OC firmada (PDF)',
    evidencia_recepcion: 'Evidencia de recepción',
    comprobante_recepcion: 'Comprobante de recepción',
    comprobante_pago: 'Comprobante de pago',
    solicitud_archivo: 'Anexo de solicitud',
    solicitud_firmada: 'Solicitud firmada',
};

export const FACTURA_ESTATUS_LABELS: Record<CostosFacturaEstatus, string> = {
    pendiente_recepcion: 'Pendiente Recepción',
    pendiente_aprobacion: 'Pendiente Aprobación',
    pendiente_pago: 'Pendiente Pago',
    pagada: 'Pagada',
    cancelada: 'Cancelada',
};

// Labels de estatus de factura como los ve el proveedor en el portal.
// "pendiente_aprobacion" se muestra como "Contrarecibo pendiente".
export const FACTURA_ESTATUS_LABELS_PORTAL: Record<CostosFacturaEstatus, string> = {
    ...FACTURA_ESTATUS_LABELS,
    pendiente_aprobacion: 'Contrarecibo pendiente',
};

export const FACTURA_ESTATUS_COLORS: Record<CostosFacturaEstatus, string> = {
    pendiente_recepcion: 'badge-warning',
    pendiente_aprobacion: 'badge-accent',
    pendiente_pago: 'badge-primary',
    pagada: 'badge-success',
    cancelada: 'badge-error',
};

// Auditoria (spatie/laravel-activitylog)
export type CostosActivity = {
    id: number;
    log_name: string | null;
    event: string | null;
    description: string;
    attribute_changes: {
        attributes?: Record<string, unknown>;
        old?: Record<string, unknown>;
    } | null;
    /** Propiedades de logs manuales (p. ej. el motivo de una reasignación). */
    properties?: {
        motivo?: string;
        [key: string]: unknown;
    } | null;
    created_at: string;
    causer?: Usuario | null;
};

export type CostosFactura = {
    id: number;
    folio: string;
    orden_compra_id: number;
    proveedor_id: number;
    uuid_fiscal: string | null;
    folio_fiscal: string | null;
    subtotal: number;
    iva: number;
    iva_trasladado: number;
    iva_retenido: number;
    isr_retenido: number;
    impuestos_detalle: CostosImpuestosDetalle | null;
    total: number;
    moneda: string;
    metodo_pago?: 'PUE' | 'PPD' | null;
    forma_pago?: string | null;
    fecha_factura: string | null;
    estatus: CostosFacturaEstatus;
    completamente_entregada: boolean;
    notas: string | null;
    motivo_rechazo: string | null;
    dias_credito: number | null;
    base_dias_credito: CostosBaseDiasCredito;
    fecha_pago_calculada: string | null;
    aprobada_costos: boolean;
    aprobada_costos_por: string | null;
    aprobada_costos_at: string | null;
    aceptada_contabilidad: boolean;
    aceptada_contabilidad_por: string | null;
    aceptada_contabilidad_at: string | null;
    orden_compra?: CostosOrdenCompra;
    proveedor?: Proveedor;
    entregas?: CostosEntrega[];
    entregas_ligadas?: CostosEntrega[];
    detalles?: CostosFacturaDetalle[];
    media?: Media[];
    media_pdf?: Media | null;
    pago?: CostosPago;
    aprobada_costos_por_usuario?: Usuario;
    aceptada_contabilidad_por_usuario?: Usuario;
    activities?: CostosActivity[];
    locked_by: string | null;
    locked_at: string | null;
    locked_by_user?: Pick<Usuario, 'id' | 'name'> | null;
    cobertura_completa?: boolean;
    cobertura_por_partida?: Record<number, { disponible: number; cubierta: boolean }>;
    anticipos_aplicados?: CostosAnticipoAplicacion[];
    monto_anticipos?: number;
    notas_credito?: CostosNotaCredito[];
    monto_notas_credito?: number;
    saldo_facturado?: number;
    complementos_pago?: CostosComplementoPago[];
    created_at: string;
    updated_at: string;
};

export type CostosComplementoPagoEstatus = 'pendiente' | 'cumplido' | 'vencido';

export const COMPLEMENTO_PAGO_ESTATUS_LABELS: Record<CostosComplementoPagoEstatus, string> = {
    pendiente: 'Pendiente',
    cumplido: 'Cumplido',
    vencido: 'Vencido',
};

export const COMPLEMENTO_PAGO_ESTATUS_COLORS: Record<CostosComplementoPagoEstatus, string> = {
    pendiente: 'badge-warning',
    cumplido: 'badge-success',
    vencido: 'badge-error',
};

export type CostosComplementoPago = {
    id: number;
    folio: string;
    factura_id: number;
    pago_id: number;
    proveedor_id: number;
    monto_pago: number;
    fecha_pago: string;
    fecha_generacion: string;
    fecha_limite: string;
    estatus: CostosComplementoPagoEstatus;
    complemento_uuid: string | null;
    recibido_at: string | null;
    factura?: Pick<CostosFactura, 'id' | 'folio' | 'uuid_fiscal' | 'total'>;
    proveedor?: Pick<Proveedor, 'id' | 'razon_social'>;
    created_at: string;
    updated_at: string;
};

export type CostosImpuestosDetalle = {
    traslados: Array<{
        impuesto: string;
        tipo_factor: string;
        tasa: string;
        base: number;
        importe: number;
    }>;
    retenciones: Array<{
        impuesto: string;
        importe: number;
    }>;
    total_trasladados: number;
    total_retenidos: number;
};

export type CostosFacturaDetalle = {
    id: number;
    factura_id: number;
    orden_compra_detalle_id: number;
    cantidad: number;
    precio_unitario: number;
    subtotal: number;
    orden_compra_detalle?: CostosOrdenCompraDetalle;
    created_at: string;
    updated_at: string;
};

// Entregas Types
export type CostosEntregaTipo = 'parcial' | 'completa';

export const ENTREGA_TIPO_LABELS: Record<CostosEntregaTipo, string> = {
    parcial: 'Parcial',
    completa: 'Completa',
};

export type CostosEntrega = {
    id: number;
    folio: string | null;
    orden_compra_id: number;
    factura_id: number | null;
    recibido_por: string;
    fecha_entrega: string;
    tipo: CostosEntregaTipo;
    observaciones: string | null;
    media?: Media | null;
    recibidor?: Usuario;
    detalles?: CostosEntregaDetalle[];
    created_at: string;
    updated_at: string;
};

export type CostosEntregaDetalle = {
    id: number;
    entrega_id: number;
    orden_compra_detalle_id: number;
    cantidad_recibida: number;
    /** Precio recibido capturado en la recepción (para igualar factura); null = al precio de la OC. */
    precio_unitario: number | null;
    observaciones: string | null;
    orden_compra_detalle?: CostosOrdenCompraDetalle;
    devoluciones?: CostosDevolucion[];
    created_at: string;
    updated_at: string;
};

/**
 * Fila del listado global de recepciones (pantalla "Recepciones"). Forma
 * normalizada por el backend con la OC y las Solicitudes de Pago ligadas.
 */
export type CostosRecepcionRow = {
    id: number;
    folio: string | null;
    fecha_entrega: string | null;
    tipo: CostosEntregaTipo;
    recibido_por: string | null;
    oc: { id: number; folio: string; tipo_pago: string | null; url: string } | null;
    proveedor: string | null;
    obra: string | null;
    solicitudes_pago: { id: number; folio: string; estatus: string | null; url: string }[];
    factura: { id: number; folio: string | null } | null;
    pdf_url: string;
};

// Pagos Types
export type CostosPagoEstatus = 'pendiente' | 'programado' | 'parcial' | 'pagado';

export const PAGO_ESTATUS_LABELS: Record<CostosPagoEstatus, string> = {
    pendiente: 'Pendiente',
    programado: 'Programado',
    parcial: 'Parcial',
    pagado: 'Pagado',
};

export const PAGO_ESTATUS_COLORS: Record<CostosPagoEstatus, string> = {
    pendiente: 'badge-warning',
    programado: 'badge-info',
    parcial: 'badge-accent',
    pagado: 'badge-success',
};

export type CostosPagoTipoPago = 'contado' | 'credito';

export const PAGO_TIPO_PAGO_LABELS: Record<CostosPagoTipoPago, string> = {
    contado: 'Contado',
    credito: 'Crédito',
};

export type CostosPago = {
    id: number;
    folio: string;
    pagable_type: string;
    pagable_id: number;
    monto_pago: number;
    moneda: string;
    tipo_cambio: number;
    tipo_pago: CostosPagoTipoPago;
    fecha_pago_programada: string | null;
    fecha_pago_maxima: string | null;
    fecha_pago_realizada: string | null;
    referencia_pago: string | null;
    estatus: CostosPagoEstatus;
    notas: string | null;
    pago_padre_id: number | null;
    numero_parcialidad: number | null;
    pagable?: CostosSolicitudPago | CostosFactura;
    pago_padre?: CostosPago;
    pagos_parciales?: CostosPago[];
    media?: Media | null;
    created_at: string;
    updated_at: string;
};

// Cobranza Types

export type Cliente = {
    id: number;
    nombre: string;
    rfc: string | null;
    direccion: string | null;
    telefono: string | null;
    email: string | null;
    activo: boolean;
    contacto_principal_id: number | null;
    contacto_principal?: CobContacto;
    contactos?: CobContacto[];
    contactos_count?: number;
    created_at: string;
    updated_at: string;
};

export type CobContacto = {
    id: number;
    cliente_id: number;
    nombre: string;
    email: string | null;
    telefono: string | null;
    cargo: string | null;
    activo: boolean;
    created_at: string;
    updated_at: string;
};

export type CobPartidaTipo = 'suministro' | 'montaje';

export type CobPartida = {
    id: number;
    obra_id: number;
    tipo: CobPartidaTipo;
    estatus: ObraEstatus;
    descripcion: string;
    monto: number;
    moneda: string;
    created_at: string;
    updated_at: string;
};

export type CobEstimacionEstado = 'ingresada' | 'autorizada' | 'facturada' | 'pago_parcial' | 'pagado';

export const COB_ESTIMACION_ESTADO_LABELS: Record<CobEstimacionEstado, string> = {
    ingresada: 'Ingresada',
    autorizada: 'Autorizada',
    facturada: 'Facturada',
    pago_parcial: 'Pago Parcial',
    pagado: 'Pagado',
};

export const COB_ESTIMACION_ESTADO_COLORS: Record<CobEstimacionEstado, string> = {
    ingresada: 'badge-warning',
    autorizada: 'badge-primary',
    facturada: 'badge-secondary',
    pago_parcial: 'badge-warning',
    pagado: 'badge-success',
};

export type CobEstimacionNivel = 'proyecto' | 'obra' | 'partida';

export type CobEstimacion = {
    id: number;
    proyecto_id: number | null;
    obra_id: number | null;
    nivel: CobEstimacionNivel;
    numero_estimacion: number;
    folio: string | null;
    tipo: string | null;
    fecha_emision: string | null;
    inicio: string | null;
    fin: string | null;
    monto_estimado: number;
    monto_total: number;
    monto_pagado: number;
    moneda: string;
    estado: CobEstimacionEstado;
    fecha_ultimo_cambio_estado: string | null;
    comentarios: string | null;
    pagos?: CobEstimacionPago[];
    historial?: CobEstimacionEstadoHistorial[];
    retenciones?: CobRetencion[];
    partidas?: CobPartida[];
    obra?: Obra;
    created_at: string;
    updated_at: string;
};

export type CobEstimacionPago = {
    id: number;
    estimacion_id: number;
    monto_pagado: number;
    fecha_pago: string;
    folio: string | null;
    comprobantes?: Media[];
    created_at: string;
    updated_at: string;
};

export type CobEstimacionEstadoHistorial = {
    id: number;
    estimacion_id: number;
    estado_anterior: string | null;
    estado_nuevo: string;
    folio: string | null;
    usuario_id: string;
    comentario: string | null;
    fecha_cambio: string;
    usuario?: Usuario;
    created_at: string;
    updated_at: string;
};

export type CobAnticipoEstado = 'pendiente' | 'aplicado' | 'devuelto';

export const COB_ANTICIPO_ESTADO_LABELS: Record<CobAnticipoEstado, string> = {
    pendiente: 'Pendiente',
    aplicado: 'Aplicado',
    devuelto: 'Devuelto',
};

export type CobAnticipo = {
    id: number;
    obra_id: number;
    folio: string | null;
    fecha_emision: string | null;
    monto: number;
    moneda: string;
    estado: CobAnticipoEstado;
    comentarios: string | null;
    fecha_pagado: string | null;
    media?: Media | null;
    created_at: string;
    updated_at: string;
};

export type CobAdendaTipo = 'aumento' | 'reduccion' | 'cambio_especificacion' | 'ampliacion_plazo';

export const COB_ADENDA_TIPO_LABELS: Record<CobAdendaTipo, string> = {
    aumento: 'Aumento',
    reduccion: 'Reduccion',
    cambio_especificacion: 'Cambio Especificacion',
    ampliacion_plazo: 'Ampliacion Plazo',
};

export type CobAdendaEstado = 'borrador' | 'en_revision' | 'aprobada' | 'rechazada';

export const COB_ADENDA_ESTADO_LABELS: Record<CobAdendaEstado, string> = {
    borrador: 'Borrador',
    en_revision: 'En Revision',
    aprobada: 'Aprobada',
    rechazada: 'Rechazada',
};

export type CobAdenda = {
    id: number;
    obra_id: number;
    tipo: CobAdendaTipo;
    descripcion: string;
    monto_modificacion: number;
    fecha: string | null;
    estado: CobAdendaEstado;
    created_at: string;
    updated_at: string;
};

export type CobComparativoEstado = 'analisis' | 'aprobado' | 'implementado' | 'descartado';

export const COB_COMPARATIVO_ESTADO_LABELS: Record<CobComparativoEstado, string> = {
    analisis: 'Analisis',
    aprobado: 'Aprobado',
    implementado: 'Implementado',
    descartado: 'Descartado',
};

export type CobComparativo = {
    id: number;
    proyecto_id: number;
    obra_id: number | null;
    descripcion: string;
    monto_impacto: number;
    fecha_identificacion: string | null;
    estado: CobComparativoEstado;
    created_at: string;
    updated_at: string;
};

export type CobDeduccion = {
    id: number;
    obra_id: number;
    descripcion: string;
    monto: number;
    moneda: string;
    fecha: string | null;
    created_at: string;
    updated_at: string;
};

export type CobTipoRetencion = {
    id: number;
    nombre: string;
    descripcion: string | null;
    retenciones_count?: number;
    created_at: string;
    updated_at: string;
};

export type CobRetencion = {
    id: number;
    estimacion_id: number;
    tipo_retencion_id: number;
    monto: number;
    moneda: string;
    tipo_retencion?: CobTipoRetencion;
    created_at: string;
    updated_at: string;
};

export type CobEvento = {
    id: number;
    obra_id: number;
    parent_id: number | null;
    nombre: string;
    monto: number | null;
    inicio: string | null;
    fin: string | null;
    marcado: boolean;
    children?: CobEvento[];
    created_at: string;
    updated_at: string;
};

export type CobDisputaEstado = 'en_proceso' | 'resuelto' | 'cancelado';

export const COB_DISPUTA_ESTADO_LABELS: Record<CobDisputaEstado, string> = {
    en_proceso: 'En Proceso',
    resuelto: 'Resuelto',
    cancelado: 'Cancelado',
};

export type CobDisputa = {
    id: number;
    obra_id: number;
    descripcion: string;
    fecha_inicio: string | null;
    fecha_resolucion: string | null;
    estado: CobDisputaEstado;
    resultado: string | null;
    created_at: string;
    updated_at: string;
};

export type CobPenalizacion = {
    id: number;
    obra_id: number;
    descripcion: string;
    monto: number;
    moneda: string;
    tipo: string | null;
    fecha: string | null;
    created_at: string;
    updated_at: string;
};


export const COB_TIPO_CONTRATO_LABELS: Record<string, string> = {
    precio_alzado: 'Precio Alzado',
    precio_unitario: 'Precio Unitario',
};

// ===================== RH (Recursos Humanos) =====================

export type RhSkill = {
    id: number;
    nombre: string;
    tipo: 'hard' | 'soft';
    pivot?: { nivel_requerido: string };
    created_at: string;
    updated_at: string;
};

export type RhRequerimiento = {
    id: number;
    descripcion: string;
    valor: string | null;
    created_at: string;
    updated_at: string;
};

export type RhPuesto = {
    id: number;
    departamento_id: number;
    nombre: string;
    descripcion: string | null;
    codigo: string | null;
    ubicacion: string | null;
    hora_entrada: string | null;
    hora_salida: string | null;
    puesto_jefe_id: number | null;
    departamento?: Departamento;
    puesto_jefe?: RhPuesto;
    skills?: RhSkill[];
    requerimientos?: RhRequerimiento[];
    actividades?: RhActividad[];
    documentos_puesto?: RhDocumentoPuesto[];
    plantillas_onboarding?: RhOnboardingTareaPlantilla[];
    created_at: string;
    updated_at: string;
};

export type RhOnboardingTareaPlantilla = {
    id: number;
    puesto_id: number;
    titulo: string;
    descripcion: string | null;
    dias_desde_inicio: number | null;
    orden: number;
    created_at: string;
    updated_at: string;
};

export type RhActividad = {
    id: number;
    puesto_id: number;
    descripcion: string;
    created_at: string;
    updated_at: string;
};

export type RhDocumentoPuesto = {
    id: number;
    puesto_id: number;
    nombre_reporte: string;
    frecuencia_entrega: string | null;
    cargo_entrega: string | null;
    created_at: string;
    updated_at: string;
};

export type RhPersona = {
    id: number;
    nombre: string;
    apellido: string;
    email: string | null;
    telefono: string | null;
    fecha_nacimiento: string | null;
    cv_estado: 'pendiente' | 'procesando' | 'procesado' | 'error' | null;
    nombre_completo?: string;
    media?: Media | null;
    foto?: Media | null;
    imss: string | null;
    curp: string | null;
    rfc: string | null;
    numero_ine: string | null;
    estado_civil: string | null;
    hijos: number | null;
    domicilio: string | null;
    cp: string | null;
    localidad: string | null;
    nombre_padre: string | null;
    nombre_madre: string | null;
    cuenta_banco: string | null;
    banco_op: string | null;
    c_infonavit: string | null;
    c_fonacot: string | null;
    tramite_banco: boolean;
    texto_cv: string | null;
    contacto_emergencia_1_nombre: string | null;
    contacto_emergencia_1_telefono: string | null;
    contacto_emergencia_2_nombre: string | null;
    contacto_emergencia_2_telefono: string | null;
    documentos?: RhPersonaDocumento[];
    periodos_laborales?: RhPeriodoLaboral[];
    candidaturas?: RhCandidatura[];
    created_at: string;
    updated_at: string;
};

export type RhPersonaDocumento = {
    id: number;
    persona_id: number;
    media_id: number;
    tipo_documento: string;
    fecha_emision: string | null;
    fecha_vigencia: string | null;
    notas: string | null;
    media?: Media | null;
    created_at: string;
    updated_at: string;
};

export type RhPeriodoLaboral = {
    id: number;
    persona_id: number;
    puesto_id: number | null;
    requisicion_id: number | null;
    fecha_inicio: string;
    fecha_fin: string | null;
    estado: 'activo' | 'baja';
    salario_diario: number | null;
    sueldo_mensual: string | null;
    sueldo_real: number | null;
    periodicidad_pago: 'semanal' | 'catorcenal' | 'quincenal' | 'mensual' | null;
    tipo_salario: 'fijo' | 'destajo' | null;
    tipo_contrato: string | null;
    numero_empleado: string | null;
    numero_locker: string | null;
    tipo_empleado: 'planta' | 'contratista' | 'becario' | 'foraneo' | null;
    motivo_baja: string | null;
    persona?: RhPersona;
    puesto?: RhPuesto;
    requisicion?: RhRequisicion;
    onboarding?: RhOnboarding;
    created_at: string;
    updated_at: string;
};

export type RhRequisicion = {
    id: number;
    folio: string;
    puesto_id: number;
    cantidad: number;
    estado: 'borrador' | 'abierta' | 'en_proceso' | 'cerrada' | 'cancelada';
    tipo_requisicion: 'nueva' | 'reemplazo' | 'temporal';
    tipo_contrato_generado: 'planta' | 'obra';
    procesar_ia: boolean;
    justificacion: string | null;
    nombre_solicitante: string | null;
    puesto_solicitante: string | null;
    responsable_entrevista: string | null;
    salario: number | null;
    fecha_creacion: string | null;
    fecha_cierre: string | null;
    puesto?: RhPuesto;
    extra?: RhRequisicionExtra;
    candidaturas?: RhCandidatura[];
    created_at: string;
    updated_at: string;
};

export type RhRequisicionExtra = {
    id: number;
    requisicion_id: number;
    salario_mensual: number | null;
    salario_diario: number | null;
    periodicidad_pago: string | null;
    prestaciones: string | null;
    bonos: string | null;
    horario: string | null;
    tipo_jornada: string | null;
    beneficios_adicionales: string | null;
    observaciones: string | null;
    created_at: string;
    updated_at: string;
};

export type RhCandidatura = {
    id: number;
    requisicion_id: number;
    persona_id: number;
    fecha_aplicacion: string | null;
    porcentaje_match: number | null;
    porcentaje_skills: number | null;
    porcentaje_requisitos: number | null;
    notas: string | null;
    requisicion?: RhRequisicion;
    persona?: RhPersona;
    created_at: string;
    updated_at: string;
};

export type RhOnboarding = {
    id: number;
    periodo_id: number;
    fecha_inicio: string | null;
    progreso: number;
    periodo?: RhPeriodoLaboral;
    tareas?: RhOnboardingTarea[];
    created_at: string;
    updated_at: string;
};

export type RhOnboardingTarea = {
    id: number;
    onboarding_id: number;
    responsable_periodo_id: number | null;
    titulo: string;
    descripcion: string | null;
    completada: boolean;
    fecha_vencimiento: string | null;
    fecha_completada: string | null;
    media?: Media | null;
    responsable?: RhPeriodoLaboral;
    created_at: string;
    updated_at: string;
};

export type RhPermisoAusencia = {
    id: number;
    folio: string | null;
    numero_empleado: string | null;
    nombres: string;
    apellidos: string;
    departamento: string | null;
    gerente: string | null;
    tipo: string | null;
    modalidad: string | null;
    condicion: string | null;
    razon: string | null;
    fecha_permiso: string | null;
    fecha_elaboracion: string | null;
    created_at: string;
    updated_at: string;
};

// =========================================
// Drive
// =========================================

export type DriveExterno = {
    id: number;
    nombre: string;
    email: string;
    telefono: string | null;
    empresa: string | null;
    activo: boolean;
    ultimo_acceso: string | null;
    carpetas_count?: number;
    carpetas?: DriveCarpeta[];
    created_at: string;
    updated_at: string;
};

export type DriveCarpeta = {
    id: number;
    nombre: string;
    descripcion: string | null;
    usuario_id: string | null;
    usuario?: Usuario;
    archivos_count?: number;
    externos_count?: number;
    archivos_sum_size?: number | null;
    externos?: DriveExterno[];
    created_at: string;
    updated_at: string;
};

export type DriveArchivo = {
    id: number;
    carpeta_id: number;
    nombre_original: string;
    path: string;
    mime: string | null;
    size: number | null;
    descripcion: string | null;
    subido_por_type: string;
    subido_por_id: number;
    link_token: string | null;
    link_expira_en: string | null;
    auto_eliminar_en: string | null;
    link_publico: string | null;
    carpeta?: DriveCarpeta;
    created_at: string;
    updated_at: string;
};

// DG Notas (bloc de notas del Director General)

export type DgNota = {
    id: number;
    usuario_id: string;
    titulo: string;
    contenido: string | null;
    created_at: string;
    updated_at: string;
};

// DG Reportes Types

export type DgCarpeta = {
    id: number;
    nombre: string;
    descripcion: string | null;
    orden: number;
    usuarios?: (Usuario & { pivot: { puede_escribir: boolean } })[];
    created_at: string;
    updated_at: string;
};

export type DgReporte = {
    id: number;
    carpeta_id: number;
    anio: number;
    semana: number;
    creado_por_id: string | null;
    observaciones: string | null;
    etiqueta_semana: string;
    carpeta?: DgCarpeta;
    creado_por?: Usuario;
    archivos?: DgReporteArchivo[];
    archivos_count?: number;
    created_at: string;
    updated_at: string;
};

export type DgReporteArchivo = {
    id: number;
    reporte_id: number;
    nombre_original: string;
    path: string;
    mime: string | null;
    size: number | null;
    subido_por_id: string | null;
    subido_por?: Usuario;
    reporte?: DgReporte;
    notas: string | null;
    notas_editado_por_id: string | null;
    notas_actualizado_en: string | null;
    notas_editado_por?: Usuario;
    visto_por_dg_en: string | null;
    created_at: string;
    updated_at: string;
};

// Badge Config Types

export type BadgeConfig = {
    id: number;
    nombre: string;
    tabla: string;
    campo_estatus: string;
    operador: string;
    valor_estatus: string;
    condiciones_extra: Array<{ campo: string; operador: string; valor: unknown }> | null;
    rol: string;
    nav_href: string;
    filter_href: string | null;
    activo: boolean;
    created_at: string;
    updated_at: string;
};
