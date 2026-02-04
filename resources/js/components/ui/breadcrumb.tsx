import { cn } from '@/lib/utils';
import type { ComponentProps, ReactNode } from 'react';

function Breadcrumb({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div className={cn('breadcrumbs text-sm', className)} {...props} />
    );
}

function BreadcrumbList({ className, ...props }: ComponentProps<'ul'>) {
    return <ul className={className} {...props} />;
}

function BreadcrumbItem({ className, ...props }: ComponentProps<'li'>) {
    return <li className={className} {...props} />;
}

function BreadcrumbLink({
    className,
    children,
    ...props
}: ComponentProps<'a'> & { children?: ReactNode }) {
    return (
        <a className={cn('hover:underline', className)} {...props}>
            {children}
        </a>
    );
}

function BreadcrumbPage({ className, ...props }: ComponentProps<'span'>) {
    return <span className={className} {...props} />;
}

export {
    Breadcrumb,
    BreadcrumbList,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbPage,
};
