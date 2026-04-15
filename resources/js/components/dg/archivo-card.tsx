import { Link, router } from '@inertiajs/react';
import { Download, Eye, FileSpreadsheet, FileText, FileType, Presentation, Trash2 } from 'lucide-react';
import type { DgReporteArchivo } from '@/types/models';
import { useCan } from '@/hooks/use-can';
import NotasPopover from '@/components/dg/notas-popover';

type Props = {
    archivo: DgReporteArchivo;
    onVer: (archivo: DgReporteArchivo) => void;
    puedeEliminar?: boolean;
};

function iconoParaMime(mime: string | null) {
    const m = mime ?? '';
    if (m.includes('pdf')) return <FileText className="size-6 text-rose-500" />;
    if (m.includes('presentationml') || m.includes('powerpoint')) return <Presentation className="size-6 text-orange-500" />;
    if (m.includes('spreadsheetml') || m.includes('sheet') || m.includes('excel')) return <FileSpreadsheet className="size-6 text-emerald-500" />;
    if (m.includes('wordprocessingml') || m.includes('msword')) return <FileType className="size-6 text-blue-500" />;
    return <FileText className="size-6 text-base-content/60" />;
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

export default function ArchivoCard({ archivo, onVer, puedeEliminar = false }: Props) {
    const { can } = useCan();
    const esDg = can('dg.reportes.ver');
    const noLeido = esDg && archivo.visto_por_dg_en === null;

    const handleEliminar = () => {
        if (!confirm('¿Eliminar este archivo? Esta acción no se puede deshacer.')) return;
        router.delete(`/admin/dg/archivos/${archivo.id}`, { preserveScroll: true });
    };

    return (
        <div
            className={`relative flex items-center gap-3 bg-base-100 rounded-lg px-3 py-2 transition border ${
                noLeido
                    ? 'border-warning ring-2 ring-warning/40 animate-pulse-soft'
                    : 'border-base-300 hover:border-primary'
            }`}
        >
            {noLeido && (
                <span className="absolute -top-1 -left-1 flex size-3">
                    <span className="absolute inline-flex h-full w-full rounded-full bg-warning opacity-75 animate-ping" />
                    <span className="relative inline-flex size-3 rounded-full bg-warning" />
                </span>
            )}

            <div className="shrink-0">{iconoParaMime(archivo.mime)}</div>
            <div className="flex-1 min-w-0">
                <p className="font-medium truncate flex items-center gap-1.5" title={archivo.nombre_original}>
                    {archivo.nombre_original}
                    {noLeido && (
                        <span className="badge badge-warning badge-xs font-semibold">Nuevo</span>
                    )}
                </p>
                <p className="text-xs text-base-content/60 mt-0.5 truncate">
                    {formatearFecha(archivo.created_at)}
                    {archivo.subido_por?.name ? ` · ${archivo.subido_por.name}` : ''}
                    {` · ${formatearSize(archivo.size)}`}
                </p>
            </div>

            <div className="flex items-center gap-1 shrink-0">
                <NotasPopover notas={archivo.notas} />
                <button type="button" onClick={() => onVer(archivo)} className="btn btn-xs btn-primary">
                    <Eye className="size-3.5" /> Ver
                </button>
                <Link href={`/admin/dg/archivos/${archivo.id}/descargar`} className="btn btn-xs btn-ghost" title="Descargar">
                    <Download className="size-3.5" />
                </Link>
                {puedeEliminar && (
                    <button type="button" onClick={handleEliminar} className="btn btn-xs btn-ghost text-error" title="Eliminar">
                        <Trash2 className="size-3.5" />
                    </button>
                )}
            </div>
        </div>
    );
}
