import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { FileText, FolderOpen, HardDrive, Users } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Drive', href: '/admin/drive' },
];

type Props = {
    stats: {
        total_carpetas: number;
        total_archivos: number;
        total_externos: number;
        espacio_usado: number;
    };
    esAdmin: boolean;
};

function formatSize(bytes: number | null): string {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    let size = bytes;
    while (size >= 1024 && i < units.length - 1) {
        size /= 1024;
        i++;
    }
    return `${size.toFixed(1)} ${units[i]}`;
}

export default function DriveDashboard({ stats, esAdmin }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Drive - Dashboard" />

            <div className="p-6">
                <h1 className="text-2xl font-semibold mb-6">Drive</h1>

                <div className={`grid grid-cols-1 md:grid-cols-2 gap-4 mb-8 ${esAdmin ? 'lg:grid-cols-4' : 'lg:grid-cols-3'}`}>
                    <div className="stat bg-base-200 rounded-lg">
                        <div className="stat-figure text-primary">
                            <FolderOpen className="size-8" />
                        </div>
                        <div className="stat-title">Carpetas</div>
                        <div className="stat-value text-primary">{stats.total_carpetas}</div>
                    </div>
                    <div className="stat bg-base-200 rounded-lg">
                        <div className="stat-figure text-secondary">
                            <FileText className="size-8" />
                        </div>
                        <div className="stat-title">Archivos</div>
                        <div className="stat-value text-secondary">{stats.total_archivos}</div>
                    </div>
                    {esAdmin && (
                        <div className="stat bg-base-200 rounded-lg">
                            <div className="stat-figure text-accent">
                                <Users className="size-8" />
                            </div>
                            <div className="stat-title">Usuarios Externos</div>
                            <div className="stat-value text-accent">{stats.total_externos}</div>
                        </div>
                    )}
                    <div className="stat bg-base-200 rounded-lg">
                        <div className="stat-figure text-info">
                            <HardDrive className="size-8" />
                        </div>
                        <div className="stat-title">Espacio Usado</div>
                        <div className="stat-value text-info text-2xl">{formatSize(stats.espacio_usado)}</div>
                    </div>
                </div>

                <div className="flex gap-4">
                    <Link href="/admin/drive/carpetas" className="btn btn-primary">
                        <FolderOpen className="size-4" />
                        Gestionar Carpetas
                    </Link>
                    {esAdmin && (
                        <Link href="/admin/drive/externos" className="btn btn-secondary">
                            <Users className="size-4" />
                            Gestionar Externos
                        </Link>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
