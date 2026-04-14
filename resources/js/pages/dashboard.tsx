import AppLogoIcon from '@/components/app-logo-icon';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

export default function Dashboard() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 items-center justify-center p-8">
                <div className="flex flex-col items-center gap-4 opacity-80">
                    <AppLogoIcon className="h-32 max-w-full text-primary dark:text-white" />
                    <p className="text-sm text-base-content/60">Sistema integrador empresarial</p>
                </div>
            </div>
        </AppLayout>
    );
}
