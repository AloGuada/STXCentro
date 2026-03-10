import AppLogoIcon from '@/components/app-logo-icon';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { FolderOpen, LayoutGrid, LogOut, MenuIcon } from 'lucide-react';
import type { ReactNode } from 'react';

type Props = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
};

const navItems = [
    { title: 'Dashboard', href: '/drive', icon: LayoutGrid },
];

function isActive(href: string): boolean {
    const current = window.location.pathname;
    if (href === '/drive') return current === '/drive';
    return current.startsWith(href);
}

function SidebarContent() {
    const { auth } = usePage<{ auth: { user: { nombre: string; empresa?: string; carpetas?: { id: number; nombre: string }[] } } }>().props;

    return (
        <div className="flex h-full flex-col">
            <div className="p-4">
                <Link href="/drive" className="flex items-center gap-2">
                    <AppLogoIcon className="size-8 fill-current text-[var(--foreground)] dark:text-white" />
                    <span className="text-lg font-semibold">Drive</span>
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

                {auth.user.carpetas && auth.user.carpetas.length > 0 && (
                    <>
                        <li className="menu-title mt-4">Carpetas</li>
                        {auth.user.carpetas.map((carpeta) => (
                            <li key={carpeta.id}>
                                <Link
                                    href={`/drive/carpetas/${carpeta.id}`}
                                    className={cn(isActive(`/drive/carpetas/${carpeta.id}`) && 'active')}
                                    prefetch
                                >
                                    <FolderOpen className="size-4" />
                                    {carpeta.nombre}
                                </Link>
                            </li>
                        ))}
                    </>
                )}
            </ul>

            <div className="border-t border-base-300 p-4">
                <div className="mb-2 px-2">
                    <p className="text-sm font-medium truncate">{auth.user.nombre}</p>
                    {auth.user.empresa && (
                        <p className="text-xs text-base-content/60 truncate">{auth.user.empresa}</p>
                    )}
                </div>
                <button
                    onClick={() => router.post('/drive/logout')}
                    className="btn btn-ghost btn-sm w-full justify-start text-error"
                >
                    <LogOut className="size-4" />
                    Cerrar sesión
                </button>
            </div>
        </div>
    );
}

export default function DriveLayout({ children, breadcrumbs = [] }: Props) {
    return (
        <div className="drawer lg:drawer-open">
            <input id="drive-sidebar" type="checkbox" className="drawer-toggle" />

            <div className="drawer-content flex flex-col">
                <header className="navbar bg-base-100 border-b border-base-300 lg:hidden">
                    <div className="flex-none">
                        <label htmlFor="drive-sidebar" className="btn btn-square btn-ghost">
                            <MenuIcon className="size-5" />
                        </label>
                    </div>
                    <div className="flex-1">
                        <Link href="/drive" className="btn btn-ghost text-xl">
                            Drive
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
                <label htmlFor="drive-sidebar" aria-label="close sidebar" className="drawer-overlay"></label>
                <aside className="bg-base-200 min-h-full w-64">
                    <SidebarContent />
                </aside>
            </div>
        </div>
    );
}
