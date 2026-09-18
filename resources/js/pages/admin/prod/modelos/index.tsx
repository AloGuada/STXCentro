/**
 * Modelo 3D de la obra — las versiones de su IFC convertidas en marcas con sus
 * cordones.
 *
 * Es una opción del catálogo de Producción de cada obra: aquí se sube el IFC y
 * se ve cómo quedó cada versión. La conversión tarda minutos, así que la
 * pantalla se refresca sola mientras haya alguna en cola o procesando. Sobre
 * los cordones de la versión reporta Calidad cada junta.
 */

import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { UploadIcon } from 'lucide-react';
import { useEffect, type FormEvent } from 'react';
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
        juntas?: number;
        orificios?: number;
        soldadura_mm?: number | null;
        marcas_sin_catalogo?: number;
        progreso?: { marcas_hechas: number; marcas_total: number } | null;
    } | null;
    marcas_count: number | null;
    procesado_at: string | null;
    subido_at: string | null;
};

/** El catálogo de la obra, para volver a él. */
export type CatalogoDelModelo = {
    id: number;
    nombre: string;
    version: number;
    obra_id: number;
    obra: string | null;
};

type Props = {
    catalogo: CatalogoDelModelo;
    modelos: ModeloResumen[];
};

/** Cada cuánto se pregunta mientras algo se convierte. */
const REFRESCO_MS = 10000;

export function migasDelModelo(catalogo: CatalogoDelModelo | null): BreadcrumbItem[] {
    return [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/destajos' },
        { title: 'Catalogos', href: '/admin/prod/catalogos' },
        ...(catalogo
            ? [
                  { title: `${catalogo.nombre} v${catalogo.version}`, href: `/admin/prod/catalogos/${catalogo.id}` },
                  { title: 'Modelo 3D', href: `/admin/prod/catalogos/${catalogo.id}/modelos` },
              ]
            : []),
    ];
}

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

export default function ModelosDeLaObra({ catalogo, modelos }: Props) {
    const { can } = useCan();
    const { props } = usePage<SharedData & { flash?: { success?: string | null } }>();
    const formulario = useForm<{ obra_id: number; archivo: File | null }>({ obra_id: catalogo.obra_id, archivo: null });
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
        formulario.post('/admin/prod/modelos', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => formulario.reset('archivo'),
        });
    };

    return (
        <AppLayout breadcrumbs={migasDelModelo(catalogo)}>
            <Head title={`Modelo 3D — ${catalogo.nombre}`} />

            <div className="space-y-4 p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Modelo 3D</h1>
                    <p className="text-base-content/60 text-sm">
                        {catalogo.obra ? `Obra ${catalogo.obra}. ` : ''}El IFC de la obra convertido en marcas con sus
                        cordones de soldadura; sobre ellos Calidad reporta cada junta. Cada IFC que se sube es una versión
                        nueva y la anterior se conserva.
                    </p>
                </div>

                {props.flash?.success && <div className="alert alert-success text-sm">{props.flash.success}</div>}

                {can('qal.modelos.crear') && (
                    <form onSubmit={subir} className="border-base-300 bg-base-100 flex flex-wrap items-end gap-3 rounded-xl border p-4">
                        <label className="flex flex-col gap-1">
                            <span className="text-base-content/60 text-xs font-medium">Archivo IFC</span>
                            <input
                                type="file"
                                accept=".ifc"
                                onChange={(e) => formulario.setData('archivo', e.target.files?.[0] ?? null)}
                                className="file-input file-input-bordered file-input-sm w-72"
                            />
                        </label>
                        <button type="submit" disabled={formulario.processing || !formulario.data.archivo} className="btn btn-sm btn-primary">
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
                        Todavía no hay modelo de esta obra. Sube el IFC exportado de Tekla.
                    </div>
                ) : (
                    <div className="border-base-300 bg-base-100 overflow-x-auto rounded-xl border">
                        <table className="table-sm table w-full whitespace-nowrap">
                            <thead>
                                <tr className="bg-base-200 text-xs">
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
                                            <Link href={`/admin/prod/modelos/${modelo.id}`} className="btn btn-xs btn-ghost">
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
