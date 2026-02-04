import { Breadcrumbs } from '@/components/breadcrumbs';
import type { BreadcrumbItem } from '@/types';
import { Link } from '@inertiajs/react';
import { Home } from 'lucide-react';
import type { ReactNode } from 'react';

type Props = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
};

export default function IntraLayout({ children, breadcrumbs = [] }: Props) {
    // Add Home breadcrumb at the start
    const fullBreadcrumbs: BreadcrumbItem[] = [
        { title: 'Home', href: '/intra' },
        ...breadcrumbs,
    ];

    return (
        <div className="min-h-screen bg-gradient-to-b from-slate-900 via-slate-800 to-slate-900">
            {/* Header with breadcrumbs */}
            {breadcrumbs.length > 0 && (
                <header className="border-b border-slate-700 bg-slate-900/50 px-6 py-4">
                    <nav className="flex items-center gap-2 text-sm text-slate-300">
                        <Link href="/intra" className="hover:text-white">
                            <Home className="size-4" />
                        </Link>
                        {breadcrumbs.map((item, index) => (
                            <span key={index} className="flex items-center gap-2">
                                <span className="text-slate-500">&gt;</span>
                                {index === breadcrumbs.length - 1 ? (
                                    <span className="text-white">{item.title}</span>
                                ) : (
                                    <Link href={item.href} className="hover:text-white">
                                        {item.title}
                                    </Link>
                                )}
                            </span>
                        ))}
                    </nav>
                </header>
            )}

            {/* Main content */}
            <main className="flex-1">{children}</main>
        </div>
    );
}
