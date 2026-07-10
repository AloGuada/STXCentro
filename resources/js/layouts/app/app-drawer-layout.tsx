import { Link, usePage } from '@inertiajs/react';
import {
    BadgeDollarSign,
    BookOpen,
    Briefcase,
    Building,
    Building2,
    Calculator,
    CalendarCheck,
    CalendarRange,
    CheckSquare,
    ChevronDown,
    ClipboardCheck,
    ClipboardList,
    DollarSign,
    Factory,
    File,
    FileText,
    FileCheck,
    Folder,
    FolderTree,
    Globe,
    Image,
    Layers,
    LayoutGrid,
    KeyRound,
    Landmark,
    LogOut,
    MenuIcon,
    Monitor,
    Network,
    Package,
    PanelLeftClose,
    PanelLeftOpen,
    PenTool,
    Puzzle,
    Receipt,
    Settings,
    Shield,
    ShoppingCart,
    Tag,
    Ticket,
    TrendingDown,
    Users,
    HardDrive,
    HardHat,
    UserCheck,
    Wrench,
} from 'lucide-react';
import { type ReactNode, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import AppLogo from '@/components/app-logo';
import { Breadcrumbs } from '@/components/breadcrumbs';
import CambiarPasswordModal from '@/components/cambiar-password-modal';
import { UserInfo } from '@/components/user-info';
import { useCan } from '@/hooks/use-can';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { BreadcrumbItem, NavGroup, NavItem, SharedData } from '@/types';

type Props = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
};

// Navegación principal con grupos
const navGroups: NavGroup[] = [
    {
        title: 'Administración',
        icon: Shield,
        defaultOpen: true,
        items: [
            {
                title: 'Usuarios',
                href: '/admin/usuarios',
                icon: Users,
                permission: 'usuarios.ver',
            },
            {
                title: 'Roles',
                href: '/admin/roles',
                icon: Shield,
                permission: 'roles.ver',
            },
            {
                title: 'Departamentos',
                href: '/admin/departamentos',
                icon: Building,
                permission: 'departamentos.ver',
            },
            {
                title: 'Badge Configs',
                href: '/admin/badge-configs',
                icon: Settings,
                permission: 'badge-configs.ver',
            },
        ],
    },
    {
        title: 'Catálogos',
        icon: Folder,
        items: [
            {
                title: 'Obras',
                href: '/admin/obras',
                icon: Briefcase,
                permission: 'obras.ver',
            },
            {
                title: 'Conceptos',
                href: '/admin/prod/conceptos',
                icon: Puzzle,
                permission: 'prod.conceptos.ver',
            },
            {
                title: 'Media',
                href: '/admin/media',
                icon: Image,
                role: 'super-admin',
            },
            {
                title: 'Tags',
                href: '/admin/tags',
                icon: Tag,
                role: 'super-admin',
            },
        ],
    },
    {
        title: 'Intranet',
        icon: Globe,
        items: [
            {
                title: 'Secciones',
                href: '/admin/intra/secciones',
                icon: File,
                permission: 'intra.secciones.ver',
            },
            {
                title: 'Áreas',
                href: '/admin/intra/areas',
                icon: FolderTree,
                permission: 'intra.areas.ver',
            },
            {
                title: 'Documentos',
                href: '/admin/intra/documentos',
                icon: File,
                permission: 'intra.documentos.ver',
            },
        ],
    },
    {
        title: 'Produccion',
        icon: Factory,
        items: [
            {
                title: 'Registros',
                href: '/admin/prod/registros',
                icon: ClipboardList,
                permission: 'prod.registros.ver',
            },
            {
                title: 'Cortes',
                href: '/admin/prod/cortes',
                icon: DollarSign,
                permission: 'prod.cortes.ver',
            },
            {
                title: 'Grupos Trabajo',
                href: '/admin/prod/grupos-trabajo',
                icon: Users,
                permission: 'prod.grupos-trabajo.ver',
            },
            {
                title: 'Grupo Precios',
                href: '/admin/prod/grupo-precios',
                icon: Layers,
                permission: 'prod.grupo-precios.ver',
            },
            {
                title: 'Tipos Pago Extra',
                href: '/admin/prod/tipos-pago-extra',
                icon: Layers,
                permission: 'prod.tipos-pago-extra.ver',
            },
        ],
    },
    {
        title: 'Costos',
        icon: BadgeDollarSign,
        items: [
            {
                title: 'Presupuestos',
                href: '/admin/costos/presupuestos',
                icon: Calculator,
                permission: 'costos.obra-rubros.ver',
            },
            {
                title: 'Obras activas',
                href: '/admin/costos/obras-activas',
                icon: Building2,
                permission: 'costos.obra-rubros.ver',
            },
            {
                title: 'Requisiciones',
                href: '/admin/costos/requisiciones',
                icon: FileText,
                permission: 'costos.requisiciones.ver',
            },
            {
                title: 'Solicitudes Pago',
                href: '/admin/costos/solicitudes-pago',
                icon: FileText,
                permission: 'costos.solicitudes-pago.ver',
            },
            {
                title: 'Mis Aprobaciones',
                href: '/admin/costos/aprobaciones',
                icon: ClipboardCheck,
            },
            {
                title: 'Afectaciones',
                href: '/admin/costos/afectaciones',
                icon: TrendingDown,
                permission: 'costos.afectaciones.ver',
            },
            {
                title: 'Ordenes Compra',
                href: '/admin/costos/ordenes-compra',
                icon: ShoppingCart,
                permission: 'costos.ordenes-compra.ver',
            },
            {
                title: 'Facturas',
                href: '/admin/costos/facturas',
                icon: Receipt,
                permission: 'costos.facturas.ver',
            },
            {
                title: 'Pagos',
                href: '/admin/costos/pagos',
                icon: DollarSign,
                permission: 'costos.pagos.ver',
            },
            {
                title: 'Cuentas Internas',
                href: '/admin/costos/cuentas-internas',
                icon: Users,
                permission: 'costos.cuentas-internas.ver',
            },
            {
                title: 'Catálogos',
                href: '/admin/proveedores',
                icon: FolderTree,
                children: [
                    {
                        title: 'Proveedores',
                        href: '/admin/proveedores',
                        icon: Building,
                        permission: 'costos.proveedores.ver',
                    },
                    {
                        title: 'Regímenes Fiscales',
                        href: '/admin/regimenes-fiscales',
                        icon: Layers,
                        permission: 'costos.regimenes-fiscales.ver',
                    },
                    {
                        title: 'Bancos',
                        href: '/admin/bancos',
                        icon: Landmark,
                        permission: 'costos.bancos.ver',
                    },
                    {
                        title: 'Usos CFDI',
                        href: '/admin/costos/usos-cfdi',
                        icon: File,
                        permission: 'costos.usos-cfdi.ver',
                    },
                    {
                        title: 'Tipos de Centro de Costos',
                        href: '/admin/costos/tipo-rubros',
                        icon: Layers,
                        permission: 'costos.tipo-rubros.ver',
                    },
                    {
                        title: 'Centros de Costos',
                        href: '/admin/costos/rubros',
                        icon: BookOpen,
                        permission: 'costos.rubros.ver',
                    },
                    {
                        title: 'Productos',
                        href: '/admin/costos/productos',
                        icon: Package,
                        permission: 'costos.productos.ver',
                    },
                    {
                        title: 'Tipo Solicitudes',
                        href: '/admin/costos/tipo-solicitudes',
                        icon: File,
                        permission: 'costos.tipo-solicitudes.ver',
                    },
                    {
                        title: 'Niveles Aprobacion',
                        href: '/admin/costos/permisos',
                        icon: CheckSquare,
                        permission: 'costos.aprobaciones.ver',
                    },
                    {
                        title: 'Bandeja de aprobador',
                        href: '/admin/costos/aprobaciones/bandeja',
                        icon: UserCheck,
                        role: 'super-admin',
                    },
                    {
                        title: 'Configuración',
                        href: '/admin/costos/configuracion',
                        icon: Settings,
                        permission: 'costos.aprobaciones.ver',
                    },
                ],
            },
        ],
    },
    {
        title: 'Cotización',
        icon: Calculator,
        items: [
            {
                title: 'Obras',
                href: '/admin/cotiz/obras',
                icon: Briefcase,
                permission: 'cotiz.obras.ver',
            },
            {
                title: 'Catálogos',
                href: '/admin/cotiz/insumos',
                icon: FolderTree,
                children: [
                    {
                        title: 'Insumos',
                        href: '/admin/cotiz/insumos',
                        icon: BookOpen,
                        permission: 'cotiz.insumos.ver',
                    },
                    {
                        title: 'Mermas',
                        href: '/admin/cotiz/mermas',
                        icon: TrendingDown,
                        permission: 'cotiz.mermas.ver',
                    },
                    {
                        title: 'Factores',
                        href: '/admin/cotiz/factores',
                        icon: Calculator,
                        permission: 'cotiz.factores.ver',
                    },
                    {
                        title: 'Centros de costo',
                        href: '/admin/cotiz/centros-costo',
                        icon: DollarSign,
                        permission: 'cotiz.centros-costo.ver',
                    },
                    {
                        title: 'Categorías de tarjeta',
                        href: '/admin/cotiz/categorias-tarjeta',
                        icon: Tag,
                        permission: 'cotiz.categorias-tarjeta.ver',
                    },
                    {
                        title: 'Fórmulas de pintura',
                        href: '/admin/cotiz/pintura-formulas',
                        icon: PenTool,
                        permission: 'cotiz.pintura-formulas.ver',
                    },
                    {
                        title: 'Categorías de kilos reales',
                        href: '/admin/cotiz/kilos-reales-categorias',
                        icon: Layers,
                        permission: 'cotiz.kilos-reales-categorias.ver',
                    },
                    {
                        title: 'Cuadrillas',
                        href: '/admin/cotiz/cuadrillas',
                        icon: Users,
                        permission: 'cotiz.cuadrillas.ver',
                    },
                    {
                        title: 'Categorías de personal',
                        href: '/admin/cotiz/personal',
                        icon: UserCheck,
                        permission: 'cotiz.personal.ver',
                    },
                    {
                        title: 'Fases de montaje',
                        href: '/admin/cotiz/fases-montaje',
                        icon: HardHat,
                        permission: 'cotiz.fases-montaje.ver',
                    },
                    {
                        title: 'Fletes y viáticos',
                        href: '/admin/cotiz/fletes-viaticos',
                        icon: Package,
                        permission: 'cotiz.fletes-viaticos.ver',
                    },
                    {
                        title: 'Filas de resumen',
                        href: '/admin/cotiz/resumen-filas',
                        icon: ClipboardList,
                        permission: 'cotiz.resumen-filas.ver',
                    },
                ],
            },
        ],
    },
    {
        title: 'Cobranza',
        icon: Receipt,
        items: [
            {
                title: 'Dashboard',
                href: '/admin/cob/dashboard',
                icon: LayoutGrid,
                permission: 'cob.dashboard.ver',
            },
            {
                title: 'Reporte semanal',
                href: '/admin/cob/reportes',
                icon: Receipt,
                permission: 'cob.reportes.ver',
            },
            {
                title: 'Proyectos',
                href: '/admin/cob/proyectos',
                icon: FolderTree,
                permission: 'cob.obras.ver',
            },
            {
                title: 'Clientes',
                href: '/admin/cob/clientes',
                icon: Building,
                permission: 'cob.clientes.ver',
            },
            {
                title: 'Tipos Retencion',
                href: '/admin/cob/tipos-retenciones',
                icon: Layers,
                permission: 'cob.tipos-retenciones.ver',
            },
            {
                title: 'Secciones Doc.',
                href: '/admin/cob/documento-secciones',
                icon: FolderTree,
                permission: 'cob.documentos.gestionar',
            },
        ],
    },
    {
        title: 'Infraestructura',
        icon: HardHat,
        items: [
            {
                title: 'Recorridos',
                href: '/admin/infra/recorridos',
                icon: ClipboardList,
                permission: 'infra.recorridos.ver',
            },
            {
                title: 'Turnos',
                href: '/admin/infra/turnos',
                icon: Settings,
                permission: 'infra.recorridos.ver',
            },
        ],
    },
    {
        title: 'Soporte TI',
        icon: Wrench,
        items: [
            {
                title: 'Dashboard',
                href: '/admin/sti/dashboard',
                icon: LayoutGrid,
                permission: 'sti.tickets.ver',
            },
            {
                title: 'Tickets',
                href: '/admin/sti/tickets',
                icon: Ticket,
                permission: 'sti.tickets.ver',
            },
            {
                title: 'Equipos',
                href: '/admin/sti/equipos',
                icon: Monitor,
                permission: 'sti.equipos.ver',
            },
            {
                title: 'Técnicos',
                href: '/admin/sti/tecnicos',
                icon: Users,
                permission: 'sti.tecnicos.ver',
            },
            {
                title: 'Planes',
                href: '/admin/sti/planes',
                icon: CalendarCheck,
                permission: 'sti.mantenimientos.ver',
            },
            {
                title: 'Mantenimientos',
                href: '/admin/sti/mantenimientos',
                icon: Settings,
                permission: 'sti.mantenimientos.ver',
            },
            {
                title: 'Programacion',
                href: '/admin/sti/mantenimientos/programacion',
                icon: CalendarRange,
                permission: 'sti.mantenimientos.programar',
            },
            {
                title: 'Inventario',
                href: '/admin/sti/items',
                icon: Package,
                permission: 'sti.equipos.ver',
            },
            {
                title: 'Tipos Item',
                href: '/admin/sti/items-tipos',
                icon: Layers,
                permission: 'sti.equipos.ver',
            },
            {
                title: 'Asignaciones',
                href: '/admin/sti/asignacion-activos',
                icon: ClipboardList,
                permission: 'sti.equipos.ver',
            },
            {
                title: 'Estados',
                href: '/admin/sti/status',
                icon: Tag,
                permission: 'sti.equipos.ver',
            },
        ],
    },
    {
        title: 'Recursos Humanos',
        icon: UserCheck,
        items: [
            {
                title: 'Organigrama',
                href: '/admin/rh/dashboard',
                icon: Network,
                permission: 'rh.puestos.ver',
            },
            {
                title: 'Puestos',
                href: '/admin/rh/puestos',
                icon: Briefcase,
                permission: 'rh.puestos.ver',
            },
            {
                title: 'Requisiciones',
                href: '/admin/rh/requisiciones',
                icon: FileCheck,
                permission: 'rh.requisiciones.ver',
            },
            {
                title: 'Personas',
                href: '/admin/rh/personas',
                icon: Users,
                permission: 'rh.personas.ver',
            },
            {
                title: 'Permisos Ausencia',
                href: '/admin/rh/permisos-ausencia',
                icon: CalendarCheck,
                permission: 'rh.permisos-ausencia.ver',
            },
        ],
    },
    {
        title: 'Drive',
        icon: HardDrive,
        items: [
            {
                title: 'Dashboard',
                href: '/admin/drive',
                icon: LayoutGrid,
                permission: 'drive.gestionar',
            },
            {
                title: 'Carpetas',
                href: '/admin/drive/carpetas',
                icon: FolderTree,
                permission: 'drive.gestionar',
            },
            {
                title: 'Usuarios Externos',
                href: '/admin/drive/externos',
                icon: Users,
                permission: 'drive.gestionar',
            },
        ],
    },
    {
        title: 'Dirección General',
        icon: Building2,
        items: [
            {
                title: 'Reportes semanales',
                href: '/admin/dg',
                icon: LayoutGrid,
                permission: 'dg.reportes.ver',
            },
            {
                title: 'Mis reportes',
                href: '/admin/dg/mis-reportes',
                icon: FileText,
            },
            {
                title: 'Mi Libreta',
                href: '/admin/dg/notas',
                icon: PenTool,
                permission: 'dg.reportes.notas',
            },
        ],
    },
];

// Items individuales sin grupo
const mainNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Permisos de Ausencia',
        href: '/rh/permisos',
        icon: CalendarCheck,
    },
    {
        title: 'Documentación Costos',
        href: '/admin/documentacion/costos',
        icon: BookOpen,
    },
    {
        title: 'Documentación RH',
        href: '/admin/documentacion/rh',
        icon: BookOpen,
    },
];

function SidebarBadge({ href }: { href: string }) {
    const { auth } = usePage<SharedData>().props;
    const badge = auth.badges?.[href];

    if (!badge || badge.count <= 0) return null;

    return (
        <span className="ml-auto badge badge-sm badge-primary">
            {badge.count > 99 ? '99+' : badge.count}
        </span>
    );
}

function SidebarMenuItem({
    item,
    isActive,
    collapsed,
}: {
    item: NavItem;
    isActive: boolean;
    collapsed?: boolean;
}) {
    const { isCurrentUrl } = useCurrentUrl();

    // Colapsado: solo el ícono, con tooltip. (Los ítems con hijos solo aparecen dentro de un
    // grupo expandido, así que aquí basta el caso simple.)
    if (collapsed) {
        return (
            <li>
                <Link
                    href={item.href}
                    className={cn(
                        'tooltip tooltip-right justify-center',
                        isActive && 'active',
                    )}
                    data-tip={item.title}
                    prefetch
                >
                    {item.icon && <item.icon className="size-5" />}
                    <SidebarBadge href={String(item.href)} />
                </Link>
            </li>
        );
    }

    if (item.children && item.children.length > 0) {
        const hasActiveChild = item.children.some((c) => isCurrentUrl(c.href));

        return (
            <li>
                <details open={isActive || hasActiveChild}>
                    <summary
                        className={cn('cursor-pointer', isActive && 'active')}
                    >
                        {item.icon && <item.icon className="size-4" />}
                        <Link
                            href={item.href}
                            prefetch
                            onClick={(e) => e.stopPropagation()}
                        >
                            {item.title}
                        </Link>
                        <SidebarBadge href={String(item.href)} />
                    </summary>
                    <ul className="ml-2 border-l border-base-300">
                        {item.children.map((child) => (
                            <li key={child.title}>
                                <Link
                                    href={String(child.href)}
                                    className={cn(
                                        'text-xs',
                                        isCurrentUrl(child.href) && 'active',
                                    )}
                                    prefetch
                                >
                                    {child.title}
                                    <SidebarBadge href={String(child.href)} />
                                </Link>
                            </li>
                        ))}
                    </ul>
                </details>
            </li>
        );
    }

    return (
        <li>
            <Link
                href={item.href}
                className={cn(isActive && 'active')}
                prefetch
            >
                {item.icon && <item.icon className="size-4" />}
                {item.title}
                <SidebarBadge href={String(item.href)} />
            </Link>
        </li>
    );
}

/**
 * Sidebar colapsado: ícono del grupo con un flyout (popover) al hover que lista sus opciones.
 * Se renderiza en un portal con posición fija para que no lo recorte el ancho del sidebar.
 */
function CollapsedGroupFlyout({
    group,
    hasNotifications,
    onExpandGroup,
}: {
    group: NavGroup;
    hasNotifications: boolean;
    onExpandGroup?: () => void;
}) {
    const { isCurrentUrl } = useCurrentUrl();
    const liRef = useRef<HTMLLIElement>(null);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const [open, setOpen] = useState(false);
    const [pos, setPos] = useState<{ top: number; left: number }>({
        top: 0,
        left: 0,
    });

    const show = () => {
        if (timer.current) clearTimeout(timer.current);
        const rect = liRef.current?.getBoundingClientRect();
        if (rect) setPos({ top: rect.top, left: rect.right + 6 });
        setOpen(true);
    };
    const scheduleHide = () => {
        timer.current = setTimeout(() => setOpen(false), 120);
    };

    useEffect(
        () => () => {
            if (timer.current) clearTimeout(timer.current);
        },
        [],
    );

    const Icon = group.icon;

    return (
        <li ref={liRef} onMouseEnter={show} onMouseLeave={scheduleHide}>
            <button
                type="button"
                className="justify-center"
                onClick={onExpandGroup}
            >
                {Icon && <Icon className="size-5" />}
                {hasNotifications && (
                    <span className="absolute top-1 right-1 flex size-2">
                        <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-75"></span>
                        <span className="relative inline-flex size-2 rounded-full bg-primary"></span>
                    </span>
                )}
            </button>

            {open &&
                createPortal(
                    <div
                        style={{
                            position: 'fixed',
                            top: pos.top,
                            left: pos.left,
                            zIndex: 60,
                        }}
                        onMouseEnter={show}
                        onMouseLeave={scheduleHide}
                    >
                        <ul className="menu max-h-[80vh] w-60 flex-nowrap overflow-y-auto rounded-box bg-base-200 p-2 shadow-lg ring-1 ring-base-300">
                            <li className="flex-row items-center gap-2 menu-title">
                                {Icon && <Icon className="size-4" />}
                                <span>{group.title}</span>
                            </li>
                            {group.items.map((item) =>
                                item.children && item.children.length > 0 ? (
                                    <li key={item.title}>
                                        <details open>
                                            <summary>
                                                {item.icon && (
                                                    <item.icon className="size-4" />
                                                )}
                                                {item.title}
                                            </summary>
                                            <ul>
                                                {item.children.map((child) => (
                                                    <li key={child.title}>
                                                        <Link
                                                            href={String(
                                                                child.href,
                                                            )}
                                                            className={cn(
                                                                'text-xs',
                                                                isCurrentUrl(
                                                                    child.href,
                                                                ) && 'active',
                                                            )}
                                                            onClick={() =>
                                                                setOpen(false)
                                                            }
                                                            prefetch
                                                        >
                                                            {child.title}
                                                            <SidebarBadge
                                                                href={String(
                                                                    child.href,
                                                                )}
                                                            />
                                                        </Link>
                                                    </li>
                                                ))}
                                            </ul>
                                        </details>
                                    </li>
                                ) : (
                                    <li key={item.title}>
                                        <Link
                                            href={item.href}
                                            className={cn(
                                                isCurrentUrl(item.href) &&
                                                    'active',
                                            )}
                                            onClick={() => setOpen(false)}
                                            prefetch
                                        >
                                            {item.icon && (
                                                <item.icon className="size-4" />
                                            )}
                                            {item.title}
                                            <SidebarBadge
                                                href={String(item.href)}
                                            />
                                        </Link>
                                    </li>
                                ),
                            )}
                        </ul>
                    </div>,
                    document.body,
                )}
        </li>
    );
}

function SidebarMenuGroup({
    group,
    isOpen,
    onToggle,
    collapsed,
    onExpandGroup,
}: {
    group: NavGroup;
    isOpen: boolean;
    onToggle: () => void;
    collapsed?: boolean;
    onExpandGroup?: () => void;
}) {
    const { isCurrentUrl } = useCurrentUrl();
    const { auth } = usePage<SharedData>().props;

    const hasNotifications = group.items.some((item) => {
        const itemBadge = auth.badges?.[String(item.href)];
        if (itemBadge && itemBadge.count > 0) return true;
        return item.children?.some((child) => {
            const childBadge = auth.badges?.[String(child.href)];
            return childBadge && childBadge.count > 0;
        });
    });

    // Colapsado: ícono con flyout (popover) al pasar el mouse que muestra las opciones del grupo.
    if (collapsed) {
        return (
            <CollapsedGroupFlyout
                group={group}
                hasNotifications={!!hasNotifications}
                onExpandGroup={onExpandGroup}
            />
        );
    }

    return (
        <li>
            <details open={isOpen}>
                <summary
                    onClick={(e) => {
                        e.preventDefault();
                        onToggle();
                    }}
                >
                    {group.icon && <group.icon className="size-4" />}
                    {group.title}
                    {hasNotifications && (
                        <span className="relative ml-auto flex size-2">
                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-75"></span>
                            <span className="relative inline-flex size-2 rounded-full bg-primary"></span>
                        </span>
                    )}
                </summary>
                <ul>
                    {group.items.map((item) => (
                        <SidebarMenuItem
                            key={item.title}
                            item={item}
                            isActive={isCurrentUrl(item.href)}
                        />
                    ))}
                </ul>
            </details>
        </li>
    );
}

function SidebarContent({
    collapsed,
    setCollapsed,
}: {
    collapsed: boolean;
    setCollapsed: (v: boolean) => void;
}) {
    const { auth } = usePage<SharedData>().props;
    const { isCurrentUrl } = useCurrentUrl();
    const { can, hasRole } = useCan();

    const dgPuedeSubir = auth?.dg_puede_subir ?? false;

    const filteredGroups = navGroups
        .map((g) => ({
            ...g,
            items: g.items
                .map((i) => ({
                    ...i,
                    children: i.children?.filter(
                        (c) =>
                            (!c.permission || can(c.permission)) &&
                            (!c.role || hasRole(c.role)),
                    ),
                }))
                .filter((i) => {
                    // "Mis reportes": depende de tener al menos una carpeta con puede_escribir
                    if (i.href === '/admin/dg/mis-reportes') {
                        return dgPuedeSubir;
                    }
                    // "Mis Aprobaciones": solo para aprobadores (asignados en
                    // costos_aprobacion_departamento), no por permiso de rol.
                    if (i.href === '/admin/costos/aprobaciones') {
                        return auth?.es_aprobador_costos ?? false;
                    }
                    // Ítem contenedor (submenú): se oculta si no quedó ningún hijo visible.
                    if (Array.isArray(i.children)) {
                        return i.children.length > 0;
                    }
                    return (
                        (!i.permission || can(i.permission)) &&
                        (!i.role || hasRole(i.role))
                    );
                }),
        }))
        .filter((g) => g.items.length > 0);

    // Determinar grupo inicial abierto: el que tiene un item activo (o child activo), o el defaultOpen
    const initialGroup =
        filteredGroups.find((g) =>
            g.items.some(
                (i) =>
                    isCurrentUrl(i.href) ||
                    i.children?.some((c) => isCurrentUrl(c.href)),
            ),
        )?.title ??
        filteredGroups.find((g) => g.defaultOpen)?.title ??
        null;

    const [openGroup, setOpenGroup] = useState<string | null>(initialGroup);
    const [showCambiarPassword, setShowCambiarPassword] = useState(false);

    const expandAndOpen = (title: string) => {
        setCollapsed(false);
        setOpenGroup(title);
    };

    return (
        <div className="flex h-full flex-col">
            {/* Logo + botón colapsar/expandir */}
            {collapsed ? (
                <div className="flex justify-center p-3">
                    <button
                        type="button"
                        className="tooltip btn tooltip-right btn-square btn-ghost btn-sm"
                        data-tip="Expandir menú"
                        onClick={() => setCollapsed(false)}
                    >
                        <PanelLeftOpen className="size-5" />
                    </button>
                </div>
            ) : (
                <div className="flex items-center gap-2 p-4">
                    <Link
                        href={dashboard()}
                        className="flex items-center gap-2"
                        prefetch
                    >
                        <AppLogo />
                    </Link>
                    <button
                        type="button"
                        className="btn ml-auto btn-square btn-ghost btn-sm"
                        title="Colapsar menú"
                        onClick={() => setCollapsed(true)}
                    >
                        <PanelLeftClose className="size-5" />
                    </button>
                </div>
            )}

            {/* Menu principal */}
            <ul className={cn('menu flex-1', collapsed ? 'px-2' : 'px-4')}>
                {/* Items principales */}
                {mainNavItems.map((item) => (
                    <SidebarMenuItem
                        key={item.title}
                        item={item}
                        isActive={isCurrentUrl(item.href)}
                        collapsed={collapsed}
                    />
                ))}

                {/* Grupos con submenús — accordion: solo uno abierto */}
                {filteredGroups.map((group) => (
                    <SidebarMenuGroup
                        key={group.title}
                        group={group}
                        isOpen={openGroup === group.title}
                        onToggle={() =>
                            setOpenGroup(
                                openGroup === group.title ? null : group.title,
                            )
                        }
                        collapsed={collapsed}
                        onExpandGroup={() => expandAndOpen(group.title)}
                    />
                ))}

                {/* Divider */}
                {collapsed ? (
                    <li className="mx-2 my-3 border-t border-base-300"></li>
                ) : (
                    <li className="mt-4 border-t border-base-300 menu-title pt-4">
                        <span>Enlaces</span>
                    </li>
                )}

                {/* Footer items */}
                {footerNavItems.map((item) => {
                    const href = String(item.href);
                    const isInternal = href.startsWith('/');
                    const inner = (
                        <>
                            {item.icon && <item.icon className="size-4" />}
                            {!collapsed && item.title}
                        </>
                    );
                    const linkClass = collapsed
                        ? 'tooltip tooltip-right justify-center'
                        : '';
                    return (
                        <li key={item.title}>
                            {isInternal ? (
                                <Link
                                    href={href}
                                    className={linkClass}
                                    data-tip={
                                        collapsed ? item.title : undefined
                                    }
                                >
                                    {inner}
                                </Link>
                            ) : (
                                <a
                                    href={href}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className={linkClass}
                                    data-tip={
                                        collapsed ? item.title : undefined
                                    }
                                >
                                    {inner}
                                </a>
                            )}
                        </li>
                    );
                })}
            </ul>

            {/* Usuario */}
            <div
                className={cn(
                    'border-t border-base-300',
                    collapsed ? 'p-2' : 'p-4',
                )}
            >
                <div
                    className={cn(
                        'dropdown dropdown-top',
                        collapsed ? 'dropdown-end' : 'w-full',
                    )}
                >
                    <div
                        tabIndex={0}
                        role="button"
                        className={cn(
                            'btn gap-2 btn-ghost',
                            collapsed ? 'btn-square' : 'w-full justify-start',
                        )}
                    >
                        {collapsed ? (
                            <div className="placeholder avatar">
                                <div className="w-8 rounded-full bg-neutral text-neutral-content">
                                    <span className="text-xs">
                                        {auth.user.name
                                            .split(' ')
                                            .map((n) => n[0])
                                            .join('')
                                            .toUpperCase()
                                            .slice(0, 2)}
                                    </span>
                                </div>
                            </div>
                        ) : (
                            <>
                                <UserInfo user={auth.user} roles={auth.roles} />
                                <ChevronDown className="ml-auto size-4" />
                            </>
                        )}
                    </div>
                    <ul
                        tabIndex={0}
                        className={cn(
                            'dropdown-content menu z-50 mb-2 rounded-box bg-base-200 p-2 shadow-lg',
                            collapsed ? 'w-56' : 'w-full',
                        )}
                    >
                        <li>
                            <button
                                type="button"
                                onClick={() => setShowCambiarPassword(true)}
                            >
                                <KeyRound className="size-4" />
                                Cambiar contraseña
                            </button>
                        </li>
                        {auth.es_aprobador_costos && (
                            <li>
                                <Link href="/admin/costos/firma" prefetch>
                                    <PenTool className="size-4" />
                                    Mi firma
                                </Link>
                            </li>
                        )}
                        <li>
                            <Link
                                href="/logout"
                                method="post"
                                as="button"
                                className="text-error"
                            >
                                <LogOut className="size-4" />
                                Cerrar sesión
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>

            {showCambiarPassword && (
                <CambiarPasswordModal
                    onClose={() => setShowCambiarPassword(false)}
                />
            )}
        </div>
    );
}

export default function AppDrawerLayout({ children, breadcrumbs = [] }: Props) {
    // Estado colapsado del sidebar, persistido en localStorage (queda como lo dejó el usuario).
    const [collapsed, setCollapsed] = useState<boolean>(
        () =>
            typeof window !== 'undefined' &&
            localStorage.getItem('sidebar-collapsed') === '1',
    );
    useEffect(() => {
        localStorage.setItem('sidebar-collapsed', collapsed ? '1' : '0');
    }, [collapsed]);

    return (
        <div className="drawer lg:drawer-open">
            <input
                id="sidebar-drawer"
                type="checkbox"
                className="drawer-toggle"
            />

            {/* Contenido principal */}
            <div className="drawer-content flex flex-col">
                {/* Header móvil */}
                <header className="navbar border-b border-base-300 bg-base-100 lg:hidden">
                    <div className="flex-none">
                        <label
                            htmlFor="sidebar-drawer"
                            className="btn btn-square btn-ghost"
                        >
                            <MenuIcon className="size-5" />
                        </label>
                    </div>
                    <div className="flex-1">
                        <Link
                            href={dashboard()}
                            className="btn text-xl btn-ghost"
                        >
                            <AppLogo />
                        </Link>
                    </div>
                </header>

                {/* Breadcrumbs */}
                {breadcrumbs.length > 0 && (
                    <div className="border-b border-base-300 px-4 py-3">
                        <Breadcrumbs breadcrumbs={breadcrumbs} />
                    </div>
                )}

                {/* Content */}
                <main className="flex-1 overflow-auto">{children}</main>
            </div>

            {/* Sidebar */}
            <div className="drawer-side z-40">
                <label
                    htmlFor="sidebar-drawer"
                    aria-label="close sidebar"
                    className="drawer-overlay"
                ></label>
                <aside
                    className={cn(
                        'min-h-full bg-base-200 transition-[width] duration-200',
                        collapsed ? 'w-16' : 'w-64',
                    )}
                >
                    <SidebarContent
                        collapsed={collapsed}
                        setCollapsed={setCollapsed}
                    />
                </aside>
            </div>
        </div>
    );
}
