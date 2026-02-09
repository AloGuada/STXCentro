export type Usuario = {
    id: string;
    empleado: number | null;
    name: string;
    email: string;
    email_verified_at: string | null;
    roles?: Role[];
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

export type Obra = {
    id: number;
    no: string;
    descripcion: string;
    created_at: string;
    updated_at: string;
};

export type Media = {
    id: number;
    descripcion: string;
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
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
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
    media_id: number;
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
    tipo?: StiItemTipo;
    grupo?: StiGrupo;
    historial?: StiItemHistorial[];
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
