import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Bell, FileText, FolderOpen, FolderPlus, Pencil, Trash2 } from 'lucide-react';
import { useCan } from '@/hooks/use-can';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Dirección General', href: '/admin/dg' },
];

type CarpetaResumen = {
    id: number;
    nombre: string;
    descripcion: string | null;
    puede_escribir: boolean;
    no_leidos: number;
    semana_actual: { subido: boolean; archivos: number };
    semana_anterior: { subido: boolean; archivos: number };
};

type Props = {
    carpetas: CarpetaResumen[];
    periodo: {
        anio_actual: number;
        semana_actual: number;
        anio_anterior: number;
        semana_anterior: number;
    };
};

export default function DgDashboard({ carpetas, periodo }: Props) {
    const { can } = useCan();
    const esAdmin = can('dg.reportes.administrar');
    const [showCrear, setShowCrear] = useState(false);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dirección General" />

            <div className="p-6 space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Reportes a Dirección General</h1>
                        <p className="text-base-content/60 mt-1">
                            Semana <span className="font-semibold">({periodo.semana_actual}) · {periodo.anio_actual}</span>
                        </p>
                    </div>
                    {esAdmin && (
                        <button type="button" onClick={() => setShowCrear(true)} className="btn btn-primary">
                            <FolderPlus className="size-4" /> Nueva carpeta
                        </button>
                    )}
                </div>

                {carpetas.length === 0 ? (
                    <div className="bg-base-200 rounded-lg p-12 text-center text-base-content/60">
                        No tienes acceso a ninguna carpeta. Contacta al administrador.
                    </div>
                ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        {carpetas.map((carpeta) => {
                            const tieneNoLeidos = carpeta.no_leidos > 0;
                            return (
                            <Link
                                key={carpeta.id}
                                href={`/admin/dg/carpetas/${carpeta.id}`}
                                className={`card relative transition hover:shadow-lg border ${
                                    tieneNoLeidos
                                        ? 'bg-blue-500/10 border-blue-500/70 hover:border-blue-500'
                                        : 'bg-base-100 border-base-300 hover:border-primary'
                                }`}
                            >
                                {tieneNoLeidos && (
                                    <span className="absolute -top-2 -right-2 flex">
                                        <span className="absolute inline-flex h-full w-full rounded-full bg-blue-500 opacity-75 animate-ping" />
                                        <span className="relative inline-flex items-center justify-center min-w-6 h-6 px-1.5 rounded-full bg-blue-500 text-white text-xs font-bold shadow">
                                            <Bell className="size-3 mr-0.5" />
                                            {carpeta.no_leidos}
                                        </span>
                                    </span>
                                )}
                                <div className="card-body p-5">
                                    <div className="flex items-start gap-3">
                                        <div className={`p-2 rounded-lg ${tieneNoLeidos ? 'bg-blue-100 text-blue-600' : 'bg-primary/10 text-primary'}`}>
                                            <FolderOpen className="size-6" />
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <h2 className="font-semibold truncate">{carpeta.nombre}</h2>
                                            {carpeta.descripcion && (
                                                <p className="text-xs text-base-content/60 truncate">
                                                    {carpeta.descripcion}
                                                </p>
                                            )}
                                        </div>
                                        {carpeta.puede_escribir && (
                                            <span className="tooltip" data-tip="Puedes subir archivos">
                                                <Pencil className="size-3.5 text-success" />
                                            </span>
                                        )}
                                        {esAdmin && (
                                            <button
                                                type="button"
                                                onClick={(e) => {
                                                    e.preventDefault();
                                                    e.stopPropagation();
                                                    if (confirm(`¿Eliminar la carpeta "${carpeta.nombre}" y todos sus archivos?`)) {
                                                        router.delete(`/admin/dg/carpetas/${carpeta.id}`, { preserveScroll: true });
                                                    }
                                                }}
                                                className="btn btn-xs btn-ghost text-error"
                                                title="Eliminar carpeta"
                                            >
                                                <Trash2 className="size-3.5" />
                                            </button>
                                        )}
                                    </div>

                                    <div className="mt-3 space-y-2">
                                        <EstadoFila
                                            label={`Semana (${periodo.semana_actual})`}
                                            subido={carpeta.semana_actual.subido}
                                            archivos={carpeta.semana_actual.archivos}
                                        />
                                        <EstadoFila
                                            label={`Semana (${periodo.semana_anterior})`}
                                            subido={carpeta.semana_anterior.subido}
                                            archivos={carpeta.semana_anterior.archivos}
                                            muted
                                        />
                                    </div>
                                </div>
                            </Link>
                            );
                        })}
                    </div>
                )}
            </div>

            {showCrear && <CrearCarpetaModal onClose={() => setShowCrear(false)} />}
        </AppLayout>
    );
}

function CrearCarpetaModal({ onClose }: { onClose: () => void }) {
    const [nombre, setNombre] = useState('');
    const [descripcion, setDescripcion] = useState('');
    const [orden, setOrden] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!nombre.trim()) return;
        setSaving(true);
        setError(null);
        router.post(
            '/admin/dg/carpetas',
            {
                nombre: nombre.trim(),
                descripcion: descripcion.trim() || null,
                orden: orden ? Number(orden) : 0,
            },
            {
                preserveScroll: true,
                onSuccess: () => onClose(),
                onError: (errs) => setError(Object.values(errs)[0] ?? 'No se pudo crear'),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-black/50" onClick={onClose} />
            <div className="relative bg-base-100 rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
                <div className="flex items-center gap-3 px-5 py-4 border-b border-base-300 bg-base-200/40">
                    <div className="p-2 rounded-lg bg-primary/10 text-primary">
                        <FolderPlus className="size-5" />
                    </div>
                    <div>
                        <h3 className="font-semibold">Nueva carpeta</h3>
                        <p className="text-xs text-base-content/60">Agrega una nueva carpeta a Dirección General.</p>
                    </div>
                </div>
                <form onSubmit={submit} className="p-5 space-y-4">
                    <div>
                        <label htmlFor="dg-nombre" className="block text-sm font-medium mb-1.5">
                            Nombre <span className="text-error">*</span>
                        </label>
                        <input
                            id="dg-nombre"
                            type="text"
                            value={nombre}
                            onChange={(e) => setNombre(e.target.value)}
                            placeholder="Ej. Marketing"
                            className="input input-bordered w-full"
                            required
                            autoFocus
                        />
                    </div>
                    <div>
                        <label htmlFor="dg-desc" className="block text-sm font-medium mb-1.5">
                            Descripción <span className="text-base-content/40 font-normal">(opcional)</span>
                        </label>
                        <input
                            id="dg-desc"
                            type="text"
                            value={descripcion}
                            onChange={(e) => setDescripcion(e.target.value)}
                            placeholder="Breve descripción del propósito"
                            className="input input-bordered w-full"
                        />
                    </div>
                    <div>
                        <label htmlFor="dg-orden" className="block text-sm font-medium mb-1.5">
                            Orden <span className="text-base-content/40 font-normal">(menor = aparece primero)</span>
                        </label>
                        <input
                            id="dg-orden"
                            type="number"
                            value={orden}
                            onChange={(e) => setOrden(e.target.value)}
                            placeholder="0"
                            className="input input-bordered w-32"
                            min={0}
                        />
                    </div>
                    {error && (
                        <div className="alert alert-error py-2 text-sm">
                            <span>{error}</span>
                        </div>
                    )}
                    <div className="flex justify-end gap-2 pt-2 border-t border-base-300 -mx-5 px-5 -mb-5 pb-4">
                        <button type="button" onClick={onClose} className="btn btn-ghost btn-sm">
                            Cancelar
                        </button>
                        <button type="submit" disabled={saving || !nombre.trim()} className="btn btn-primary btn-sm">
                            {saving ? 'Creando...' : 'Crear carpeta'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function EstadoFila({
    label,
    subido,
    archivos,
    muted = false,
}: {
    label: string;
    subido: boolean;
    archivos: number;
    muted?: boolean;
}) {
    return (
        <div className={`flex items-center justify-between text-sm ${muted ? 'text-base-content/60' : ''}`}>
            <span>{label}</span>
            {subido ? (
                <span className="badge badge-success gap-1">
                    <FileText className="size-3" />
                    {archivos}
                </span>
            ) : (
                <span className="badge badge-ghost gap-1 text-base-content/60">
                    <FileText className="size-3" />
                    0
                </span>
            )}
        </div>
    );
}
