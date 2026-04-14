import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { DgReporte } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { CalendarDays, Download, FileSpreadsheet, FileText, FileType, Presentation } from 'lucide-react';
import { useState } from 'react';
import ArchivoViewerModal from '@/components/dg/archivo-viewer-modal';
import type { DgReporteArchivo } from '@/types/models';

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
    reportes: Paginated<DgReporte>;
    carpetas: { id: number; nombre: string }[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Mis reportes', href: '/admin/dg/mis-reportes' },
];

function iconoParaMime(mime: string | null) {
    const m = mime ?? '';
    if (m.includes('pdf')) return <FileText className="size-4 text-rose-500" />;
    if (m.includes('presentationml') || m.includes('powerpoint')) return <Presentation className="size-4 text-orange-500" />;
    if (m.includes('spreadsheetml') || m.includes('sheet') || m.includes('excel')) return <FileSpreadsheet className="size-4 text-emerald-500" />;
    if (m.includes('wordprocessingml') || m.includes('msword')) return <FileType className="size-4 text-blue-500" />;
    return <FileText className="size-4 text-base-content/60" />;
}

function formatearSize(bytes: number | null): string {
    if (!bytes) return '—';
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    let size = bytes;
    while (size >= 1024 && i < units.length - 1) {
        size /= 1024;
        i++;
    }
    return `${size.toFixed(1)} ${units[i]}`;
}

function formatearFecha(iso: string | null): string {
    if (!iso) return '';
    try {
        return new Date(iso).toLocaleString('es-MX', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return '';
    }
}

export default function MisReportes({ reportes, carpetas }: Props) {
    const [verArchivo, setVerArchivo] = useState<DgReporteArchivo | null>(null);
    const totalArchivos = reportes.data.reduce((sum, r) => sum + (r.archivos?.length ?? 0), 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mis reportes" />

            <div className="p-6 space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Mis reportes</h1>
                        <p className="text-base-content/60 mt-1">
                            {carpetas.length > 0 ? (
                                <>Carpeta(s): {carpetas.map((c) => c.nombre).join(', ')}</>
                            ) : (
                                'No tienes carpetas con permiso de escritura.'
                            )}
                        </p>
                    </div>
                    <div className="stats bg-base-200 shadow-sm">
                        <div className="stat py-2 px-4">
                            <div className="stat-title text-xs">Archivos en esta página</div>
                            <div className="stat-value text-2xl text-primary">{totalArchivos}</div>
                        </div>
                    </div>
                </div>

                {reportes.data.length === 0 ? (
                    <div className="bg-base-200 rounded-lg p-12 text-center text-base-content/60">
                        Aún no has subido ningún reporte.
                    </div>
                ) : (
                    <div className="bg-base-100 border border-base-300 rounded-lg overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="table">
                                <thead className="bg-base-200/50">
                                    <tr>
                                        <th className="w-[14%]">Semana</th>
                                        <th className="w-[16%]">Carpeta</th>
                                        <th>Archivo</th>
                                        <th className="w-[10%]">Tamaño</th>
                                        <th className="w-[18%]">Subido</th>
                                        <th className="w-[14%]">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {reportes.data.map((reporte) => {
                                        const archivos = reporte.archivos ?? [];
                                        if (archivos.length === 0) {
                                            return (
                                                <tr key={reporte.id} className="text-base-content/50">
                                                    <td className="font-semibold">
                                                        <div className="flex items-center gap-2">
                                                            <CalendarDays className="size-4" />
                                                            {reporte.etiqueta_semana}
                                                        </div>
                                                    </td>
                                                    <td>{reporte.carpeta?.nombre}</td>
                                                    <td colSpan={4} className="italic">Sin archivos</td>
                                                </tr>
                                            );
                                        }
                                        return archivos.map((archivo, idx) => (
                                            <tr key={archivo.id} className="hover">
                                                {idx === 0 ? (
                                                    <>
                                                        <td
                                                            rowSpan={archivos.length}
                                                            className="font-semibold align-top bg-base-200/30"
                                                        >
                                                            <div className="flex items-center gap-2">
                                                                <CalendarDays className="size-4 text-primary" />
                                                                {reporte.etiqueta_semana}
                                                            </div>
                                                        </td>
                                                        <td rowSpan={archivos.length} className="align-top bg-base-200/30">
                                                            {reporte.carpeta?.nombre}
                                                        </td>
                                                    </>
                                                ) : null}
                                                <td>
                                                    <div className="flex items-center gap-2 min-w-0">
                                                        {iconoParaMime(archivo.mime)}
                                                        <span className="truncate" title={archivo.nombre_original}>
                                                            {archivo.nombre_original}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="text-sm text-base-content/70">{formatearSize(archivo.size)}</td>
                                                <td className="text-xs text-base-content/70">
                                                    <div>{formatearFecha(archivo.created_at)}</div>
                                                    {archivo.subido_por?.name && (
                                                        <div className="text-base-content/50">{archivo.subido_por.name}</div>
                                                    )}
                                                </td>
                                                <td>
                                                    <div className="flex items-center gap-1">
                                                        <button
                                                            type="button"
                                                            onClick={() => setVerArchivo(archivo)}
                                                            className="btn btn-xs btn-primary"
                                                        >
                                                            Ver
                                                        </button>
                                                        <Link
                                                            href={`/admin/dg/archivos/${archivo.id}/descargar`}
                                                            className="btn btn-xs btn-ghost"
                                                            title="Descargar"
                                                        >
                                                            <Download className="size-3.5" />
                                                        </Link>
                                                    </div>
                                                </td>
                                            </tr>
                                        ));
                                    })}
                                </tbody>
                            </table>
                        </div>

                        {reportes.last_page > 1 && (
                            <div className="p-3 border-t border-base-300 flex justify-center">
                                <div className="join">
                                    {reportes.links.map((link, i) => (
                                        <button
                                            key={i}
                                            type="button"
                                            disabled={!link.url}
                                            onClick={() => link.url && router.visit(link.url, { preserveScroll: true })}
                                            className={`join-item btn btn-sm ${link.active ? 'btn-primary' : ''}`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>

            <ArchivoViewerModal
                archivo={verArchivo}
                onClose={() => setVerArchivo(null)}
                puedeVerNotas={true}
            />
        </AppLayout>
    );
}
