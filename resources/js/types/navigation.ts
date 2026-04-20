import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: string;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    permission?: string;
    role?: string;
    children?: NavItem[];
};

export type NavGroup = {
    title: string;
    icon?: LucideIcon | null;
    items: NavItem[];
    defaultOpen?: boolean;
};
