import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Briefcase,
    Building,
    CalendarCheck,
    CalendarRange,
    ChevronDown,
    ClipboardList,
    DollarSign,
    Factory,
    File,
    Folder,
    FolderTree,
    Globe,
    Image,
    Layers,
    LayoutGrid,
    LogOut,
    MenuIcon,
    Monitor,
    Package,
    Puzzle,
    Settings,
    Shield,
    Tag,
    Ticket,
    Users,
    Wrench,
} from 'lucide-react';
import type { ReactNode } from 'react';
import AppLogo from '@/components/app-logo';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { UserInfo } from '@/components/user-info';
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
            { title: 'Usuarios', href: '/admin/usuarios', icon: Users },
            { title: 'Roles', href: '/admin/roles', icon: Shield },
            { title: 'Departamentos', href: '/admin/departamentos', icon: Building },
        ],
    },
    {
        title: 'Catálogos',
        icon: Folder,
        items: [
            { title: 'Obras', href: '/admin/obras', icon: Briefcase },
            { title: 'Piezas', href: '/admin/prod/piezas', icon: Puzzle },
            { title: 'Media', href: '/admin/media', icon: Image },
            { title: 'Tags', href: '/admin/tags', icon: Tag },
        ],
    },
    {
        title: 'Intranet',
        icon: Globe,
        items: [
            { title: 'Secciones', href: '/admin/intra/secciones', icon: File },
            { title: 'Áreas', href: '/admin/intra/areas', icon: FolderTree },
            { title: 'Documentos', href: '/admin/intra/documentos', icon: File },
        ],
    },
    {
        title: 'Produccion',
        icon: Factory,
        items: [
            { title: 'Destajos', href: '/admin/prod/destajos', icon: DollarSign },
            { title: 'Grupos', href: '/admin/prod/grupos', icon: Users },
            { title: 'Grupo Precios', href: '/admin/prod/grupo-precios', icon: Layers },
            { title: 'Tipos Pago', href: '/admin/prod/tipos', icon: Tag },
        ],
    },
    {
        title: 'Soporte TI',
        icon: Wrench,
        items: [
            { title: 'Tickets', href: '/admin/sti/tickets', icon: Ticket },
            { title: 'Equipos', href: '/admin/sti/equipos', icon: Monitor },
            { title: 'Técnicos', href: '/admin/sti/tecnicos', icon: Users },
            { title: 'Planes', href: '/admin/sti/planes', icon: CalendarCheck },
            { title: 'Mantenimientos', href: '/admin/sti/mantenimientos', icon: Settings },
            { title: 'Programacion', href: '/admin/sti/mantenimientos/programacion', icon: CalendarRange },
            { title: 'Inventario', href: '/admin/sti/items', icon: Package },
            { title: 'Tipos Item', href: '/admin/sti/items-tipos', icon: Layers },
            { title: 'Asignaciones', href: '/admin/sti/asignacion-activos', icon: ClipboardList },
            { title: 'Estados', href: '/admin/sti/status', icon: Tag },
        ],
    },
];

// Items individuales sin grupo
const mainNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
];

const footerNavItems: NavItem[] = [
    { title: '---', href: 'https://github.com/laravel/react-starter-kit', icon: Folder },
];

function SidebarMenuItem({ item, isActive }: { item: NavItem; isActive: boolean }) {
    return (
        <li>
            <Link
                href={item.href}
                className={cn(isActive && 'active')}
                prefetch
            >
                {item.icon && <item.icon className="size-4" />}
                {item.title}
            </Link>
        </li>
    );
}

function SidebarMenuGroup({ group }: { group: NavGroup }) {
    const { isCurrentUrl } = useCurrentUrl();
    const hasActiveItem = group.items.some((item) => isCurrentUrl(item.href));

    return (
        <li>
            <details open={group.defaultOpen || hasActiveItem}>
                <summary>
                    {group.icon && <group.icon className="size-4" />}
                    {group.title}
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

                {/* Grupos con submenús */}
                {navGroups.map((group) => (
                    <SidebarMenuGroup key={group.title} group={group} />
                ))}

                {/* Divider */}
                <li className="menu-title mt-4 pt-4 border-t border-base-300">
                    <span>Enlaces</span>
                </li>

                {/* Footer items */}
                {footerNavItems.map((item) => (
                    <li key={item.title}>
                        <a href={String(item.href)} target="_blank" rel="noopener noreferrer">
                            {item.icon && <item.icon className="size-4" />}
                            {item.title}
                        </a>
                    </li>
                ))}
            </ul>

            {/* Usuario */}
            <div className="border-t border-base-300 p-4">
                <div className="dropdown dropdown-top w-full">
                    <div tabIndex={0} role="button" className="btn btn-ghost w-full justify-start gap-2">
                        <UserInfo user={auth.user} />
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
