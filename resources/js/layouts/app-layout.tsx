import AppDrawerLayout from '@/layouts/app/app-drawer-layout';
import type { AppLayoutProps } from '@/types';

export default ({ children, breadcrumbs, ...props }: AppLayoutProps) => (
    <AppDrawerLayout breadcrumbs={breadcrumbs} {...props}>
        {children}
    </AppDrawerLayout>
);
