/**
 * Modelos 3D — el IFC de cada obra convertido en marcas con sus cordones.
 *
 * El IFC se sube normalmente desde el catálogo de Producción de la obra; aquí
 * también, para quien vive en Calidad. Cada archivo es una versión nueva y la
 * conversión tarda minutos: la pantalla se refresca sola mientras haya alguna
 * en cola o procesando.
 */

import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { UploadIcon } from 'lucide-react';
import { useEffect, type FormEvent } from 'react';
import { Select, SelectItem } from '@/components/ui/select';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, SharedData } from '@/types';

export type ModeloResumen = {
    id: number;
    obra_id: number;
    obra: string | null;
    version: number;
    nombre_original: string;
    tamano_bytes: number;
    estatus: 'pendiente' | 'procesando' | 'listo' | 'error';
    estatus_etiqueta: string;
    error: string | null;
    welds_version: string | null;
    resumen: {
        marcas?: number;
        cordones?: number;
        soldadura_mm?: number | null;
        marcas_sin_catalogo?: number;
        progreso?: { marcas_hechas: number; marcas_total: number } | null;
    } | null;
    marcas_count: number | null;
    procesado_at: string | null;
    subido_at: string | null;
};

type Props = {
    obras: { id: number; no: string | null; descripcion: string | null }[];
    obraId: number | null;
    modelos: ModeloResumen[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Calidad', href: '/admin/calidad/catalogos' },
    { title: 'Modelos 3D', href: '/admin/calidad/modelos' },
];

/** Cada cuánto se pregunta mientras algo se convierte. */
const REFRESCO_MS = 10000;

export function PastillaModelo({ modelo }: { modelo: Pick<ModeloResumen, 'estatus' | 'estatus_etiqueta' | 'resumen'> }) {
    const tono =
        modelo.estatus === 'listo' ? 'badge-success' : modelo.estatus === 'error' ? 'badge-error' : 'badge-warning';
    const progreso = modelo.resumen?.progreso;

    return (
        <span className={`badge badge-sm font-semibold ${tono}`}>
            {modelo.estatus_etiqueta}
            {modelo.estatus === 'procesando' && progreso && progreso.marcas_total > 0 && ` · ${progreso.marcas_hechas}/${progreso.marcas_total}`}
        </span>
    );
}

export default function ModelosIndex({ obras, obraId, modelos }: Props) {
    const { can } = useCan();
    const { props } = usePage<SharedData & { flash?: { success?: string | null } }>();
    const formulario = useForm<{ obra_id: string; archivo: File | null }>({ obra_id: obraId ? String(obraId) : '', archivo: null });
    const enCurso = modelos.some((modelo) => modelo.estatus === 'pendiente' || modelo.estatus === 'procesando');

    useEffect(() => {
        if (!enCurso) {
            return;
        }
        const reloj = window.setInterval(() => router.reload({ only: ['modelos'] }), REFRESCO_MS);
        return () => window.clearInterval(reloj);
    }, [enCurso]);

    const subir = (evento: FormEvent) => {
        evento.preventDefault();
        formulario.post('/admin/calidad/modelos', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => formulario.reset('archivo'),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Calidad — Modelos 3D" />

            <div className="space-y-4 p-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Modelos 3D</h1>
                        <p className="text-base-content/60 text-sm">
                            El IFC de cada obra convertido en marcas con sus cordones de soldadura. Sobre ellos se
                            reporta cada junta en la captura de soldado.
                        </p>
                    </div>
                    <label className="flex flex-col gap-1">
                        <span className="text-base-content/60 text-xs font-medium">Obra</span>
                        <Select
                            value={obraId ? String(obraId) : ''}
                            onValueChange={(valor) => router.get('/admin/calidad/modelos', valor ? { obra: valor } : {}, { replace: true })}
                            className="select-sm w-64"
                        >
                            <SelectItem value="">Todas</SelectItem>
                            {obras.map((obra) => (
                                <SelectItem key={obra.id} value={String(obra.id)}>
                                    {[obra.no, obra.descripcion].filter(Boolean).join(' — ')}
                                </SelectItem>
                            ))}
                        </Select>
                    </label>
                </div>

                {props.flash?.success && <div className="alert alert-success text-sm">{props.flash.success}</div>}

                {can('qal.modelos.crear') && (
                    <form onSubmit={subir} className="border-base-300 bg-base-100 flex flex-wrap items-end gap-3 rounded-xl border p-4">
                        <label className="flex flex-col gap-1">
                            <span className="text-base-content/60 text-xs font-medium">Obra del modelo</span>
                            <Select value={formulario.data.obra_id} onValueChange={(valor) => formulario.setData('obra_id', valor)} className="select-sm w-64">
                                <SelectItem value="">—</SelectItem>
                                {obras.map((obra) => (
                                    <SelectItem key={obra.id} value={String(obra.id)}>
                                        {[obra.no, obra.descripcion].filter(Boolean).join(' — ')}
                                    </SelectItem>
                                ))}
                            </Select>
                        </label>
                        <label className="flex flex-col gap-1">
                            <span className="text-base-content/60 text-xs font-medium">Archivo IFC</span>
                            <input
                                type="file"
                                accept=".ifc"
                                onChange={(e) => formulario.setData('archivo', e.target.files?.[0] ?? null)}
                                className="file-input file-input-bordered file-input-sm w-72"
                            />
                        </label>
                        <button
                            type="submit"
                            disabled={formulario.processing || !formulario.data.archivo || !formulario.data.obra_id}
                            className="btn btn-sm btn-primary"
                        >
                            <UploadIcon className="size-4" />
                            {formulario.processing ? 'Subiendo…' : 'Añadir IFC'}
                        </button>
                        {(formulario.errors.archivo || formulario.errors.obra_id) && (
                            <span className="text-error text-sm">{formulario.errors.archivo ?? formulario.errors.obra_id}</span>
                        )}
                    </form>
                )}

                {modelos.length === 0 ? (
                    <div className="border-base-300 bg-base-100 text-base-content/60 rounded-xl border p-10 text-center">
                        Todavía no hay modelos{obraId ? ' de esta obra' : ''}. El IFC se sube desde el catálogo de
                        Producción de la obra.
                    </div>
                ) : (
                    <div className="border-base-300 bg-base-100 overflow-x-auto rounded-xl border">
                        <table className="table-sm table w-full whitespace-nowrap">
                            <thead>
                                <tr className="bg-base-200 text-xs">
                                    <th>Obra</th>
                                    <th>Versión</th>
                                    <th>Archivo</th>
                                    <th>Estatus</th>
                                    <th>Marcas</th>
                                    <th>Cordones</th>
                                    <th>Subido</th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                {modelos.map((modelo) => (
                                    <tr key={modelo.id} className="hover:bg-base-200/50">
                                        <td className="text-sm">{modelo.obra}</td>
                                        <td className="font-mono">v{modelo.version}</td>
                                        <td className="text-sm">{modelo.nombre_original}</td>
                                        <td>
                                            <PastillaModelo modelo={modelo} />
                                            {modelo.error && <div className="text-error max-w-xs truncate text-xs" title={modelo.error}>{modelo.error}</div>}
                                        </td>
                                        <td className="font-mono">{modelo.marcas_count ?? '—'}</td>
                                        <td className="font-mono">{modelo.resumen?.cordones ?? '—'}</td>
                                        <td className="font-mono text-sm">{modelo.subido_at?.slice(0, 16)}</td>
                                        <td className="text-right">
                                            <Link href={`/admin/calidad/modelos/${modelo.id}`} className="btn btn-xs btn-ghost">
                                                Ver
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
