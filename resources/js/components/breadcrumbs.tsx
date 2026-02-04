import { Link } from '@inertiajs/react';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function Breadcrumbs({
    breadcrumbs,
}: {
    breadcrumbs: BreadcrumbItemType[];
}) {
    if (breadcrumbs.length === 0) return null;

    return (
        <div className="breadcrumbs text-sm">
            <ul>
                {breadcrumbs.map((item, index) => {
                    const isLast = index === breadcrumbs.length - 1;
                    return (
                        <li key={index}>
                            {isLast ? (
                                <span>{item.title}</span>
                            ) : (
                                <Link href={item.href}>{item.title}</Link>
                            )}
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
