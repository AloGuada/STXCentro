import { Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { DosierDeObra, EstatusDosier, PlantillaDosier } from './tipos';

export const ESTATUS_DOSIER: Record<EstatusDosier, { texto: string; clase: string }> = {
    borrador: { texto: 'Borrador', clase: 'badge-ghost' },
    en_revision: { texto: 'En revisión', clase: 'badge-warning' },
    entregado: { texto: 'Entregado', clase: 'badge-success' },
};

type Props = {
    dosieres: DosierDeObra[];
    obras: { id: number; no: string | null; descripcion: string | null }[];
    plantillas: PlantillaDosier[];
    puedeEditar: boolean;
};

/**
 * Los dosieres de obra: uno por obra, con cuántas secciones ya tienen PDF.
 * Uno nuevo nace de una plantilla activa y se abre para empezar a subir.
 */
export function ListaDeDosieres({ dosieres, obras, plantillas, puedeEditar }: Props) {
    const activas = plantillas.filter((p) => p.activo);
    const nuevo = useForm({ obra_id: '', plantilla_id: activas[0] ? String(activas[0].id) : '' });

    const crear = (e: React.FormEvent) => {
        e.preventDefault();
        nuevo.post('/admin/calidad/dosier');
    };

    return (
        <div className="space-y-4">
            {puedeEditar && (
                <form onSubmit={crear} className="rounded-box border-base-300 grid gap-2 border p-3 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto]">
                    <label className="flex min-w-0 flex-col gap-1">
                        <span className="text-base-content/60 text-[11px] font-medium">Obra</span>
                        <select
                            className={`select select-sm select-bordered w-full ${nuevo.errors.obra_id ? 'select-error' : ''}`}
                            value={nuevo.data.obra_id}
                            onChange={(e) => nuevo.setData('obra_id', e.target.value)}
                        >
                            <option value="">{obras.length ? 'Elige la obra' : 'Todas las obras activas ya tienen dosier'}</option>
                            {obras.map((o) => (
                                <option key={o.id} value={String(o.id)}>
                                    {[o.no, o.descripcion].filter(Boolean).join(' — ')}
                                </option>
                            ))}
                        </select>
                        {nuevo.errors.obra_id && <span className="text-error text-xs">{nuevo.errors.obra_id}</span>}
                    </label>
                    <label className="flex min-w-0 flex-col gap-1">
                        <span className="text-base-content/60 text-[11px] font-medium">Nace de la plantilla</span>
                        <select
                            className={`select select-sm select-bordered w-full ${nuevo.errors.plantilla_id ? 'select-error' : ''}`}
                            value={nuevo.data.plantilla_id}
                            onChange={(e) => nuevo.setData('plantilla_id', e.target.value)}
                        >
                            {activas.map((p) => (
                                <option key={p.id} value={String(p.id)}>
                                    {p.nombre} · {p.secciones} secciones
                                </option>
                            ))}
                        </select>
                        {nuevo.errors.plantilla_id && <span className="text-error text-xs">{nuevo.errors.plantilla_id}</span>}
                    </label>
                    <div className="flex items-end">
                        <Button type="submit" size="sm" disabled={nuevo.processing || !nuevo.data.obra_id}>
                            Nuevo dosier
                        </Button>
                    </div>
                </form>
            )}

            <div className="rounded-box border-base-300 overflow-x-auto border">
                <table className="table table-sm">
                    <thead className="bg-base-200">
                        <tr>
                            <th>Obra</th>
                            <th>Plantilla</th>
                            <th>Estatus</th>
                            <th>Avance</th>
                            <th className="text-right">PDF</th>
                            <th>Actualizado</th>
                        </tr>
                    </thead>
                    <tbody>
                        {dosieres.length === 0 ? (
                            <tr>
                                <td colSpan={6} className="text-base-content/50 py-8 text-center">
                                    Todavía no hay dosieres.{puedeEditar && ' Crea el primero arriba.'}
                                </td>
                            </tr>
                        ) : (
                            dosieres.map((d) => {
                                const porcentaje = d.secciones ? Math.round((d.con_archivo * 100) / d.secciones) : 0;

                                return (
                                    <tr key={d.id} className="hover">
                                        <td>
                                            <Link href={`/admin/calidad/dosier/${d.id}`} className="link link-hover font-semibold">
                                                {d.obra}
                                            </Link>
                                        </td>
                                        <td>{d.plantilla}</td>
                                        <td>
                                            <span className={`badge badge-sm ${ESTATUS_DOSIER[d.estatus].clase}`}>{ESTATUS_DOSIER[d.estatus].texto}</span>
                                            {d.entregado_at && <span className="text-base-content/50 ml-1 text-xs">{d.entregado_at}</span>}
                                        </td>
                                        <td className="min-w-40">
                                            <div className="flex items-center gap-2">
                                                <progress className="progress progress-primary w-24" value={porcentaje} max={100} />
                                                <span className="text-xs">
                                                    {d.con_archivo} / {d.secciones} secciones
                                                </span>
                                            </div>
                                        </td>
                                        <td className="text-right">
                                            {d.archivos}
                                            {d.no_compatibles > 0 && (
                                                <span className="badge badge-sm badge-warning ml-1" title="El motor de unión actual no los abre">
                                                    {d.no_compatibles} fuera
                                                </span>
                                            )}
                                        </td>
                                        <td className="text-xs">{d.actualizado}</td>
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
