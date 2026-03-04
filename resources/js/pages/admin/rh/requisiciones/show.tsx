import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { RhRequisicion } from '@/types/models';
import { Head, Link } from '@inertiajs/react';

type Props = {
    requisicion: RhRequisicion;
};

const estadoVariant = (estado: string) => {
    switch (estado) {
        case 'abierta':
            return 'default';
        case 'borrador':
            return 'secondary';
        case 'en_proceso':
            return 'warning';
        case 'cerrada':
            return 'secondary';
        case 'cancelada':
            return 'destructive';
        default:
            return 'secondary';
    }
};

const TIPO_LABELS: Record<string, string> = {
    nueva: 'Nueva',
    reemplazo: 'Reemplazo',
    temporal: 'Temporal',
};

export default function RequisicionShow({ requisicion }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'RH', href: '/admin/rh/skills' },
        { title: 'Requisiciones', href: '/admin/rh/requisiciones' },
        { title: requisicion.folio, href: `/admin/rh/requisiciones/${requisicion.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Requisicion ${requisicion.folio}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <div className="mb-6 flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold">Requisicion {requisicion.folio}</h1>
                            <Badge variant={estadoVariant(requisicion.estado)}>{requisicion.estado}</Badge>
                        </div>
                        <div className="flex gap-2">
                            <Button variant="outline" asChild>
                                <Link href={`/admin/rh/requisiciones/${requisicion.id}/candidatos`}>Candidatos</Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={`/admin/rh/requisiciones/${requisicion.id}/edit`}>Editar</Link>
                            </Button>
                        </div>
                    </div>

                    {/* Informacion General */}
                    <div className="mb-8 rounded border p-4">
                        <h2 className="mb-4 text-lg font-semibold">Informacion General</h2>
                        <dl className="grid grid-cols-2 gap-x-6 gap-y-3">
                            <div>
                                <dt className="text-muted-foreground text-sm">Folio</dt>
                                <dd>{requisicion.folio}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-sm">Tipo</dt>
                                <dd>{TIPO_LABELS[requisicion.tipo_requisicion] ?? requisicion.tipo_requisicion}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-sm">Cantidad</dt>
                                <dd>{requisicion.cantidad}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-sm">Salario</dt>
                                <dd>{requisicion.salario ? `$${Number(requisicion.salario).toLocaleString()}` : '-'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-sm">Solicitante</dt>
                                <dd>{requisicion.nombre_solicitante ?? '-'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-sm">Fecha Creacion</dt>
                                <dd>{requisicion.fecha_creacion ?? '-'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-sm">Fecha Cierre</dt>
                                <dd>{requisicion.fecha_cierre ?? '-'}</dd>
                            </div>
                        </dl>
                        {requisicion.justificacion && (
                            <div className="mt-4">
                                <dt className="text-muted-foreground text-sm">Justificacion</dt>
                                <dd className="mt-1">{requisicion.justificacion}</dd>
                            </div>
                        )}
                    </div>

                    {/* Puesto */}
                    {requisicion.puesto && (
                        <div className="mb-8 rounded border p-4">
                            <h2 className="mb-4 text-lg font-semibold">Puesto</h2>
                            <dl className="grid grid-cols-2 gap-x-6 gap-y-3">
                                <div>
                                    <dt className="text-muted-foreground text-sm">Nombre</dt>
                                    <dd>{requisicion.puesto.nombre}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm">Departamento</dt>
                                    <dd>{requisicion.puesto.departamento?.descripcion ?? '-'}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm">Codigo</dt>
                                    <dd>{requisicion.puesto.codigo ?? '-'}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm">Ubicacion</dt>
                                    <dd>{requisicion.puesto.ubicacion ?? '-'}</dd>
                                </div>
                            </dl>
                        </div>
                    )}

                    {/* Candidaturas */}
                    <div className="mb-8 rounded border p-4">
                        <h2 className="mb-4 text-lg font-semibold">Candidaturas ({(requisicion.candidaturas ?? []).length})</h2>
                        {(requisicion.candidaturas ?? []).length > 0 ? (
                            <ul className="space-y-2">
                                {(requisicion.candidaturas ?? []).map((cand) => (
                                    <li key={cand.id} className="flex items-center justify-between rounded border px-3 py-2">
                                        <div>
                                            <span className="font-medium">
                                                {cand.persona ? `${cand.persona.nombre} ${cand.persona.apellido}` : `Persona #${cand.persona_id}`}
                                            </span>
                                            {cand.fecha_aplicacion && (
                                                <span className="text-muted-foreground ml-2 text-sm">Aplicado: {cand.fecha_aplicacion}</span>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {cand.porcentaje_match !== null && (
                                                <Badge variant="outline">Match: {cand.porcentaje_match}%</Badge>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-muted-foreground text-sm">No hay candidaturas registradas.</p>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
