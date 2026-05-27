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
        <div className="min-h-screen bg-base-200 text-base-content">
            {/* Header with breadcrumbs */}
            {breadcrumbs.length > 0 && (
                <header className="border-b border-base-300 bg-base-100/60 px-6 py-4">
                    <nav className="flex items-center gap-2 text-sm text-base-content/70">
                        <Link href="/intra" className="hover:text-base-content">
                            <Home className="size-4" />
                        </Link>
                        {breadcrumbs.map((item, index) => (
                            <span key={index} className="flex items-center gap-2">
                                <span className="text-base-content/40">&gt;</span>
                                {index === breadcrumbs.length - 1 ? (
                                    <span className="text-base-content font-medium">{item.title}</span>
                                ) : (
                                    <Link href={item.href} className="hover:text-base-content">
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
