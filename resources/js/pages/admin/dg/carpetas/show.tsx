import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { DgCarpeta, DgReporte, DgReporteArchivo } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { CalendarClock, CalendarDays, ChevronLeft, History, Settings, Upload } from 'lucide-react';
import ArchivoCard from '@/components/dg/archivo-card';
import ArchivoViewerModal from '@/components/dg/archivo-viewer-modal';
import { useCan } from '@/hooks/use-can';

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
    carpeta: Pick<DgCarpeta, 'id' | 'nombre' | 'descripcion'>;
    reporte_actual: DgReporte | null;
    reporte_anterior: DgReporte | null;
    historial: Paginated<DgReporte>;
    periodo: {
        anio_actual: number;
        semana_actual: number;
        anio_anterior: number;
        semana_anterior: number;
    };
    puede_subir: boolean;
    puede_administrar: boolean;
};

export default function DgCarpetaShow({
    carpeta,
    reporte_actual,
    reporte_anterior,
    historial,
    periodo,
    puede_subir,
    puede_administrar,
}: Props) {
    const [verArchivo, setVerArchivo] = useState<DgReporteArchivo | null>(null);
    const { can } = useCan();
    const puedeEditarNotas = can('dg.reportes.notas');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Dirección General', href: '/admin/dg' },
        { title: carpeta.nombre, href: `/admin/dg/carpetas/${carpeta.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`DG · ${carpeta.nombre}`} />

            <div className="p-6 space-y-6">
                <div className="flex flex-wrap items-start gap-3 justify-between">
                    <div className="flex items-start gap-3">
                        <Link href="/admin/dg" className="btn btn-ghost btn-sm">
                            <ChevronLeft className="size-4" /> Volver
                        </Link>
                        <div>
                            <h1 className="text-2xl font-semibold">{carpeta.nombre}</h1>
                            {carpeta.descripcion && (
                                <p className="text-base-content/60 mt-1">{carpeta.descripcion}</p>
                            )}
                        </div>
                    </div>
                    {puede_administrar && (
                        <Link
                            href={`/admin/dg/carpetas/${carpeta.id}/accesos`}
                            className="btn btn-sm btn-ghost"
                        >
                            <Settings className="size-4" /> Configurar accesos
                        </Link>
                    )}
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <SeccionPanel
                        icono={<CalendarClock className="size-5 text-base-content/70" />}
                        titulo={`Semana (${periodo.semana_anterior})`}
                        subtitulo={`${periodo.anio_anterior}`}
                    >
                        <SeccionSemana
                            reporte={reporte_anterior}
                            anio={periodo.anio_anterior}
                            semana={periodo.semana_anterior}
                            carpetaId={carpeta.id}
                            puedeSubir={false}
                            onVer={setVerArchivo}
                        />
                    </SeccionPanel>

                    <SeccionPanel
                        icono={<CalendarDays className="size-5 text-primary" />}
                        titulo={`Semana (${periodo.semana_actual})`}
                        subtitulo={`${periodo.anio_actual}`}
                        acentoClase="border-primary/30"
                    >
                        <SeccionSemana
                            reporte={reporte_actual}
                            anio={periodo.anio_actual}
                            semana={periodo.semana_actual}
                            carpetaId={carpeta.id}
                            puedeSubir={puede_subir}
                            onVer={setVerArchivo}
                        />
                    </SeccionPanel>
                </div>

                <SeccionPanel
                    icono={<History className="size-5 text-base-content/70" />}
                    titulo="Historial"
                    subtitulo={`${historial.data.length} semana(s) en esta página`}
                >
                    <Historial historial={historial} onVer={setVerArchivo} puedeEliminar={puede_subir} />
                </SeccionPanel>
            </div>

            <ArchivoViewerModal archivo={verArchivo} onClose={() => setVerArchivo(null)} puedeEditarNotas={puedeEditarNotas} />
        </AppLayout>
    );
}

function SeccionPanel({
    icono,
    titulo,
    subtitulo,
    acentoClase = 'border-base-300',
    children,
}: {
    icono: React.ReactNode;
    titulo: string;
    subtitulo?: string;
    acentoClase?: string;
    children: React.ReactNode;
}) {
    return (
        <section className={`bg-base-100 border ${acentoClase} rounded-lg overflow-hidden`}>
            <header className="px-4 py-3 border-b border-base-300 flex items-center gap-3 bg-base-200/40">
                {icono}
                <div className="flex-1 min-w-0">
                    <h2 className="font-semibold">{titulo}</h2>
                    {subtitulo && <p className="text-xs text-base-content/60">{subtitulo}</p>}
                </div>
            </header>
            <div className="p-4">{children}</div>
        </section>
    );
}

function SeccionSemana({
    reporte,
    anio,
    semana,
    carpetaId,
    puedeSubir,
    onVer,
}: {
    reporte: DgReporte | null;
    anio: number;
    semana: number;
    carpetaId: number;
    puedeSubir: boolean;
    onVer: (a: DgReporteArchivo) => void;
}) {
    return (
        <div className="space-y-4">
            {puedeSubir && (
                <UploadZone reporte={reporte} anio={anio} semana={semana} carpetaId={carpetaId} />
            )}

            {reporte && reporte.archivos && reporte.archivos.length > 0 ? (
                <div className="flex flex-col gap-2">
                    {reporte.archivos.map((archivo) => (
                        <ArchivoCard
                            key={archivo.id}
                            archivo={archivo}
                            onVer={onVer}
                            puedeEliminar={puedeSubir}
                        />
                    ))}
                </div>
            ) : (
                <div className="bg-base-200 rounded-lg p-8 text-center text-base-content/60">
                    No hay reportes para esta semana.
                </div>
            )}
        </div>
    );
}

function UploadZone({
    reporte,
    anio,
    semana,
    carpetaId,
}: {
    reporte: DgReporte | null;
    anio: number;
    semana: number;
    carpetaId: number;
}) {
    const [archivos, setArchivos] = useState<File[]>([]);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const onSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (archivos.length === 0) return;

        const formData = new FormData();
        archivos.forEach((file, i) => {
            formData.append(`archivos[${i}]`, file);
        });

        let url: string;
        if (reporte) {
            url = `/admin/dg/reportes/${reporte.id}/archivos`;
        } else {
            url = '/admin/dg/reportes';
            formData.append('carpeta_id', String(carpetaId));
            formData.append('anio', String(anio));
            formData.append('semana', String(semana));
        }

        setProcessing(true);
        setError(null);
        router.post(url, formData, {
            preserveScroll: true,
            onSuccess: () => setArchivos([]),
            onError: (errors) => setError(Object.values(errors)[0] ?? 'Error al subir'),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <form onSubmit={onSubmit} className="bg-base-200 rounded-lg p-4">
            <div className="flex flex-wrap items-center gap-3">
                <label className="flex-1 min-w-[240px]">
                    <input
                        type="file"
                        multiple
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx"
                        className="file-input file-input-bordered w-full"
                        onChange={(e) => setArchivos(Array.from(e.target.files ?? []))}
                    />
                </label>
                <button type="submit" disabled={processing || archivos.length === 0} className="btn btn-primary">
                    <Upload className="size-4" />
                    Subir {archivos.length > 0 ? `(${archivos.length})` : ''}
                </button>
            </div>
            {error && <p className="text-error text-sm mt-2">{error}</p>}
        </form>
    );
}

function Historial({
    historial,
    onVer,
    puedeEliminar,
}: {
    historial: Paginated<DgReporte>;
    onVer: (a: DgReporteArchivo) => void;
    puedeEliminar: boolean;
}) {
    if (historial.data.length === 0) {
        return (
            <div className="bg-base-200 rounded-lg p-8 text-center text-base-content/60">
                Sin historial.
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {historial.data.map((reporte) => (
                <div key={reporte.id} className="bg-base-100 border border-base-300 rounded-lg">
                    <div className="px-4 py-3 border-b border-base-300 flex items-center justify-between">
                        <div>
                            <p className="font-semibold">{reporte.etiqueta_semana}</p>
                            {reporte.creado_por?.name && (
                                <p className="text-xs text-base-content/60">Subido por {reporte.creado_por.name}</p>
                            )}
                        </div>
                        <span className="badge badge-ghost">{reporte.archivos?.length ?? 0} archivo(s)</span>
                    </div>
                    <div className="p-4 flex flex-col gap-2">
                        {reporte.archivos?.map((archivo) => (
                            <ArchivoCard
                                key={archivo.id}
                                archivo={archivo}
                                onVer={onVer}
                                puedeEliminar={puedeEliminar}
                            />
                        ))}
                    </div>
                </div>
            ))}

            {historial.last_page > 1 && (
                <div className="join">
                    {historial.links.map((link, i) => (
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
            )}
        </div>
    );
}
