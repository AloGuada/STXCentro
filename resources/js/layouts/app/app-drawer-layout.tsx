import { Link, usePage } from '@inertiajs/react';
import {
    BadgeDollarSign,
    BookOpen,
    Briefcase,
    Building,
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
    LogOut,
    MenuIcon,
    Monitor,
    Network,
    Package,
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
import { type ReactNode, useState } from 'react';
import AppLogo from '@/components/app-logo';
import { Breadcrumbs } from '@/components/breadcrumbs';
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
            { title: 'Usuarios', href: '/admin/usuarios', icon: Users, permission: 'usuarios.ver' },
            { title: 'Roles', href: '/admin/roles', icon: Shield, permission: 'roles.ver' },
            { title: 'Departamentos', href: '/admin/departamentos', icon: Building, permission: 'departamentos.ver' },
            { title: 'Badge Configs', href: '/admin/badge-configs', icon: Settings, permission: 'badge-configs.ver' },
        ],
    },
    {
        title: 'Catálogos',
        icon: Folder,
        items: [
            { title: 'Obras', href: '/admin/obras', icon: Briefcase, permission: 'obras.ver' },
            { title: 'Conceptos', href: '/admin/prod/conceptos', icon: Puzzle, permission: 'prod.conceptos.ver' },
            { title: 'Media', href: '/admin/media', icon: Image },
            { title: 'Tags', href: '/admin/tags', icon: Tag },
        ],
    },
    {
        title: 'Intranet',
        icon: Globe,
        items: [
            { title: 'Secciones', href: '/admin/intra/secciones', icon: File, permission: 'intra.secciones.ver' },
            { title: 'Áreas', href: '/admin/intra/areas', icon: FolderTree, permission: 'intra.areas.ver' },
            { title: 'Documentos', href: '/admin/intra/documentos', icon: File, permission: 'intra.documentos.ver' },
        ],
    },
    {
        title: 'Produccion',
        icon: Factory,
        items: [
            { title: 'Registros', href: '/admin/prod/registros', icon: ClipboardList, permission: 'prod.registros.ver' },
            { title: 'Cortes', href: '/admin/prod/cortes', icon: DollarSign, permission: 'prod.cortes.ver' },
            { title: 'Grupos Trabajo', href: '/admin/prod/grupos-trabajo', icon: Users, permission: 'prod.grupos-trabajo.ver' },
            { title: 'Grupo Precios', href: '/admin/prod/grupo-precios', icon: Layers, permission: 'prod.grupo-precios.ver' },
            { title: 'Tipos Pago Extra', href: '/admin/prod/tipos-pago-extra', icon: Layers, permission: 'prod.tipos-pago-extra.ver' },
        ],
    },
    {
        title: 'Costos',
        icon: BadgeDollarSign,
        items: [
            { title: 'Proveedores', href: '/admin/proveedores', icon: Building, permission: 'costos.proveedores.ver' },
            { title: 'Tipo Rubros', href: '/admin/costos/tipo-rubros', icon: Layers, permission: 'costos.tipo-rubros.ver' },
            { title: 'Rubros', href: '/admin/costos/rubros', icon: BookOpen, permission: 'costos.rubros.ver' },
            { title: 'Tipo Solicitudes', href: '/admin/costos/tipo-solicitudes', icon: File, permission: 'costos.tipo-solicitudes.ver' },
            { title: 'Presupuestos', href: '/admin/costos/presupuestos', icon: Calculator, permission: 'costos.obra-rubros.ver' },
            { title: 'Solicitudes Pago', href: '/admin/costos/solicitudes-pago', icon: FileText, permission: 'costos.solicitudes-pago.ver' },
            { title: 'Niveles Aprobacion', href: '/admin/costos/permisos', icon: CheckSquare, permission: 'costos.aprobaciones.ver' },
            { title: 'Mis Aprobaciones', href: '/admin/costos/aprobaciones', icon: ClipboardCheck, permission: 'costos.aprobaciones.ver' },
            { title: 'Mi Firma', href: '/admin/costos/firma', icon: PenTool },
            { title: 'Afectaciones', href: '/admin/costos/afectaciones', icon: TrendingDown, permission: 'costos.afectaciones.ver' },
            { title: 'Ordenes Compra', href: '/admin/costos/ordenes-compra', icon: ShoppingCart, permission: 'costos.ordenes-compra.ver' },
            { title: 'Facturas', href: '/admin/costos/facturas', icon: Receipt, permission: 'costos.facturas.ver' },
            { title: 'Pagos', href: '/admin/costos/pagos', icon: DollarSign, permission: 'costos.pagos.ver' },
            { title: 'Cuentas Internas', href: '/admin/costos/cuentas-internas', icon: Users, permission: 'costos.cuentas-internas.ver' },
        ],
    },
    {
        title: 'Cobranza',
        icon: Receipt,
        items: [
            { title: 'Dashboard', href: '/admin/cob/dashboard', icon: LayoutGrid, permission: 'cob.dashboard.ver' },
            { title: 'Obras', href: '/admin/cob/obras', icon: Briefcase, permission: 'cob.obras.ver' },
            { title: 'Clientes', href: '/admin/cob/clientes', icon: Building, permission: 'cob.clientes.ver' },
            { title: 'Tipos Retencion', href: '/admin/cob/tipos-retenciones', icon: Layers, permission: 'cob.tipos-retenciones.ver' },
        ],
    },
    {
        title: 'Infraestructura',
        icon: HardHat,
        items: [
            { title: 'Recorridos', href: '/admin/infra/recorridos', icon: ClipboardList, permission: 'infra.recorridos.ver' },
            { title: 'Turnos', href: '/admin/infra/turnos', icon: Settings, permission: 'infra.recorridos.ver' },
        ],
    },
    {
        title: 'Soporte TI',
        icon: Wrench,
        items: [
            { title: 'Dashboard', href: '/admin/sti/dashboard', icon: LayoutGrid, permission: 'sti.tickets.ver' },
            { title: 'Tickets', href: '/admin/sti/tickets', icon: Ticket, permission: 'sti.tickets.ver' },
            { title: 'Equipos', href: '/admin/sti/equipos', icon: Monitor, permission: 'sti.equipos.ver' },
            { title: 'Técnicos', href: '/admin/sti/tecnicos', icon: Users, permission: 'sti.tecnicos.ver' },
            { title: 'Planes', href: '/admin/sti/planes', icon: CalendarCheck, permission: 'sti.mantenimientos.ver' },
            { title: 'Mantenimientos', href: '/admin/sti/mantenimientos', icon: Settings, permission: 'sti.mantenimientos.ver' },
            { title: 'Programacion', href: '/admin/sti/mantenimientos/programacion', icon: CalendarRange, permission: 'sti.mantenimientos.programar' },
            { title: 'Inventario', href: '/admin/sti/items', icon: Package, permission: 'sti.equipos.ver' },
            { title: 'Tipos Item', href: '/admin/sti/items-tipos', icon: Layers, permission: 'sti.equipos.ver' },
            { title: 'Asignaciones', href: '/admin/sti/asignacion-activos', icon: ClipboardList, permission: 'sti.equipos.ver' },
            { title: 'Estados', href: '/admin/sti/status', icon: Tag, permission: 'sti.equipos.ver' },
        ],
    },
    {
        title: 'Recursos Humanos',
        icon: UserCheck,
        items: [
            { title: 'Organigrama', href: '/admin/rh/dashboard', icon: Network, permission: 'rh.puestos.ver' },
            { title: 'Puestos', href: '/admin/rh/puestos', icon: Briefcase, permission: 'rh.puestos.ver' },
            { title: 'Requisiciones', href: '/admin/rh/requisiciones', icon: FileCheck, permission: 'rh.requisiciones.ver' },
            { title: 'Personas', href: '/admin/rh/personas', icon: Users, permission: 'rh.personas.ver' },
            { title: 'Periodos Laborales', href: '/admin/rh/periodos-laborales', icon: CalendarRange, permission: 'rh.periodos-laborales.ver' },
            { title: 'Permisos Ausencia', href: '/admin/rh/permisos-ausencia', icon: CalendarCheck, permission: 'rh.permisos-ausencia.ver' },
        ],
    },
    {
        title: 'Drive',
        icon: HardDrive,
        items: [
            { title: 'Dashboard', href: '/admin/drive', icon: LayoutGrid, permission: 'drive.gestionar' },
            { title: 'Carpetas', href: '/admin/drive/carpetas', icon: FolderTree, permission: 'drive.gestionar' },
            { title: 'Usuarios Externos', href: '/admin/drive/externos', icon: Users, permission: 'drive.gestionar' },
        ],
    },
];

// Items individuales sin grupo
const mainNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
];

const footerNavItems: NavItem[] = [
    { title: 'Documentación Costos', href: '/admin/documentacion/costos', icon: BookOpen },
    { title: 'Documentación RH', href: '/admin/documentacion/rh', icon: BookOpen },
];

function SidebarBadge({ href }: { href: string }) {
    const { auth } = usePage<SharedData>().props;
    const badge = auth.badges?.[href];

    if (!badge || badge.count <= 0) return null;

    return (
        <span className="badge badge-sm badge-primary ml-auto">
            {badge.count > 99 ? '99+' : badge.count}
        </span>
    );
}

function SidebarMenuItem({ item, isActive }: { item: NavItem; isActive: boolean }) {
    const { isCurrentUrl } = useCurrentUrl();

    if (item.children && item.children.length > 0) {
        const hasActiveChild = item.children.some((c) => isCurrentUrl(c.href));

        return (
            <li>
                <details open={isActive || hasActiveChild}>
                    <summary className={cn('cursor-pointer', isActive && 'active')}>
                        {item.icon && <item.icon className="size-4" />}
                        <Link href={item.href} prefetch onClick={(e) => e.stopPropagation()}>
                            {item.title}
                        </Link>
                        <SidebarBadge href={String(item.href)} />
                    </summary>
                    <ul className="border-l border-base-300 ml-2">
                        {item.children.map((child) => (
                            <li key={child.title}>
                                <Link
                                    href={String(child.href)}
                                    className={cn('text-xs', isCurrentUrl(child.href) && 'active')}
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

function SidebarMenuGroup({ group, isOpen, onToggle }: { group: NavGroup; isOpen: boolean; onToggle: () => void }) {
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

function SidebarContent() {
    const { auth } = usePage<SharedData>().props;
    const { isCurrentUrl } = useCurrentUrl();
    const { can } = useCan();

    const filteredGroups = navGroups
        .map((g) => ({
            ...g,
            items: g.items
                .map((i) => ({
                    ...i,
                    children: i.children?.filter((c) => !c.permission || can(c.permission)),
                }))
                .filter((i) => !i.permission || can(i.permission)),
        }))
        .filter((g) => g.items.length > 0);

    // Determinar grupo inicial abierto: el que tiene un item activo (o child activo), o el defaultOpen
    const initialGroup = filteredGroups.find((g) =>
        g.items.some((i) => isCurrentUrl(i.href) || i.children?.some((c) => isCurrentUrl(c.href)))
    )?.title
        ?? filteredGroups.find((g) => g.defaultOpen)?.title
        ?? null;

    const [openGroup, setOpenGroup] = useState<string | null>(initialGroup);

    return (
        <div className="flex h-full flex-col">
            {/* Logo */}
            <div className="p-4">
                <Link href={dashboard()} className="flex items-center gap-2" prefetch>
                    <AppLogo />
                </Link>
            </div>

            {/* Menu principal */}
            <ul className="menu flex-1 px-4">
                {/* Items principales */}
                {mainNavItems.map((item) => (
                    <SidebarMenuItem
                        key={item.title}
                        item={item}
                        isActive={isCurrentUrl(item.href)}
                    />
                ))}

                {/* Grupos con submenús — accordion: solo uno abierto */}
                {filteredGroups.map((group) => (
                    <SidebarMenuGroup
                        key={group.title}
                        group={group}
                        isOpen={openGroup === group.title}
                        onToggle={() => setOpenGroup(openGroup === group.title ? null : group.title)}
                    />
                ))}

                {/* Divider */}
                <li className="menu-title mt-4 pt-4 border-t border-base-300">
                    <span>Enlaces</span>
                </li>

                {/* Footer items */}
                {footerNavItems.map((item) => {
                    const href = String(item.href);
                    const isInternal = href.startsWith('/');
                    return (
                        <li key={item.title}>
                            {isInternal ? (
                                <Link href={href}>
                                    {item.icon && <item.icon className="size-4" />}
                                    {item.title}
                                </Link>
                            ) : (
                                <a href={href} target="_blank" rel="noopener noreferrer">
                                    {item.icon && <item.icon className="size-4" />}
                                    {item.title}
                                </a>
                            )}
                        </li>
                    );
                })}
            </ul>

            {/* Usuario */}
            <div className="border-t border-base-300 p-4">
                <div className="dropdown dropdown-top w-full">
                    <div tabIndex={0} role="button" className="btn btn-ghost w-full justify-start gap-2">
                        <UserInfo user={auth.user} roles={auth.roles} />
                        <ChevronDown className="ml-auto size-4" />
                    </div>
                    <ul tabIndex={0} className="dropdown-content menu bg-base-200 rounded-box z-50 w-full p-2 shadow-lg mb-2">
                        <li>
                            <Link href="/logout" method="post" as="button" className="text-error">
                                <LogOut className="size-4" />
                                Cerrar sesión
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    );
}

export default function AppDrawerLayout({ children, breadcrumbs = [] }: Props) {
    return (
        <div className="drawer lg:drawer-open">
            <input id="sidebar-drawer" type="checkbox" className="drawer-toggle" />

            {/* Contenido principal */}
            <div className="drawer-content flex flex-col">
                {/* Header móvil */}
                <header className="navbar bg-base-100 border-b border-base-300 lg:hidden">
                    <div className="flex-none">
                        <label htmlFor="sidebar-drawer" className="btn btn-square btn-ghost">
                            <MenuIcon className="size-5" />
                        </label>
                    </div>
                    <div className="flex-1">
                        <Link href={dashboard()} className="btn btn-ghost text-xl">
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
                <main className="flex-1 overflow-auto">
                    {children}
                </main>
            </div>

            {/* Sidebar */}
            <div className="drawer-side z-40">
                <label htmlFor="sidebar-drawer" aria-label="close sidebar" className="drawer-overlay"></label>
                <aside className="bg-base-200 min-h-full w-64">
                    <SidebarContent />
                </aside>
            </div>
        </div>
    );
}
