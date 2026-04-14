import AppLogoIcon from '@/components/app-logo-icon';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { FileText, LayoutGrid, LogOut, MenuIcon, Receipt, ShoppingCart, Wallet } from 'lucide-react';
import type { ReactNode } from 'react';

type Props = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
};

const navItems = [
    { title: 'Dashboard', href: '/portal', icon: LayoutGrid },
    { title: 'Ordenes de Compra', href: '/portal/ordenes-compra', icon: ShoppingCart },
    { title: 'Facturas', href: '/portal/facturas', icon: Receipt },
    { title: 'Pagos', href: '/portal/pagos', icon: Wallet },
];

function isActive(href: string): boolean {
    const current = window.location.pathname;
    if (href === '/portal') return current === '/portal';
    return current.startsWith(href);
}

function SidebarContent() {
    const { auth } = usePage<{ auth: { user: { razon_social: string; nombre_comercial?: string } } }>().props;

    return (
        <div className="flex h-full flex-col">
            <div className="p-4">
                <Link href="/portal" className="flex items-center gap-2">
                    <AppLogoIcon className="h-8 text-[var(--foreground)] dark:text-white" />
                    <span className="text-lg font-semibold">Portal</span>
                </Link>
            </div>

            <ul className="menu flex-1 px-4">
                {navItems.map((item) => (
                    <li key={item.title}>
                        <Link href={item.href} className={cn(isActive(item.href) && 'active')} prefetch>
                            <item.icon className="size-4" />
                            {item.title}
                        </Link>
                    </li>
                ))}
            </ul>

            <div className="border-t border-base-300 p-4">
                <div className="mb-2 px-2">
                    <p className="text-sm font-medium truncate">{auth.user.nombre_comercial || auth.user.razon_social}</p>
                    <p className="text-xs text-base-content/60 truncate">{auth.user.razon_social}</p>
                </div>
                <button
                    onClick={() => router.post('/portal/logout')}
                    className="btn btn-ghost btn-sm w-full justify-start text-error"
                >
                    <LogOut className="size-4" />
                    Cerrar sesión
                </button>
            </div>
        </div>
    );
}

export default function PortalLayout({ children, breadcrumbs = [] }: Props) {
    return (
        <div className="drawer lg:drawer-open">
            <input id="portal-sidebar" type="checkbox" className="drawer-toggle" />

            <div className="drawer-content flex flex-col">
                <header className="navbar bg-base-100 border-b border-base-300 lg:hidden">
                    <div className="flex-none">
                        <label htmlFor="portal-sidebar" className="btn btn-square btn-ghost">
                            <MenuIcon className="size-5" />
                        </label>
                    </div>
                    <div className="flex-1">
                        <Link href="/portal" className="btn btn-ghost text-xl">
                            Portal Proveedores
                        </Link>
                    </div>
                </header>

                {breadcrumbs.length > 0 && (
                    <div className="border-b border-base-300 px-4 py-3">
                        <Breadcrumbs breadcrumbs={breadcrumbs} />
                    </div>
                )}

                <main className="flex-1 overflow-auto">{children}</main>
            </div>

            <div className="drawer-side z-40">
                <label htmlFor="portal-sidebar" aria-label="close sidebar" className="drawer-overlay"></label>
                <aside className="bg-base-200 min-h-full w-64">
                    <SidebarContent />
                </aside>
            </div>
        </div>
    );
}
