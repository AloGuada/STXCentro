import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { RhPersona } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { ExternalLinkIcon } from 'lucide-react';

function formatDate(date: string | null): string {
    if (!date) return '-';
    return new Date(date + 'T00:00:00').toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

type Props = {
    persona: RhPersona;
};

export default function PersonaShow({ persona }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'RH', href: '/admin/rh/skills' },
        { title: 'Personas', href: '/admin/rh/personas' },
        { title: `${persona.nombre} ${persona.apellido}`, href: `/admin/rh/personas/${persona.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${persona.nombre} ${persona.apellido}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <div className="mb-6 flex items-center justify-between">
                        <h1 className="text-2xl font-semibold">{persona.nombre} {persona.apellido}</h1>
                        <Button variant="outline" asChild>
                            <Link href={`/admin/rh/personas/${persona.id}/edit`}>Editar</Link>
                        </Button>
                    </div>

                    {/* Datos Generales */}
                    <div className="mb-8 rounded border p-4">
                        <h2 className="mb-4 text-lg font-semibold">Datos Generales</h2>
                        <dl className="grid grid-cols-2 gap-x-6 gap-y-3">
                            <div>
                                <dt className="text-muted-foreground text-sm">Nombre</dt>
                                <dd>{persona.nombre}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-sm">Apellido</dt>
                                <dd>{persona.apellido}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-sm">Email</dt>
                                <dd>{persona.email ?? '-'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-sm">Telefono</dt>
                                <dd>{persona.telefono ?? '-'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-sm">Fecha de Nacimiento</dt>
                                <dd>{formatDate(persona.fecha_nacimiento)}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-sm">CV</dt>
                                <dd className="flex items-center gap-2">
                                    {persona.media?.path ? (
                                        <>
                                            <Button variant="outline" size="sm" asChild>
                                                <a href={`/storage/${persona.media.path}`} target="_blank" rel="noopener noreferrer">
                                                    <ExternalLinkIcon className="size-4" />
                                                    {persona.media.nombre_original ?? 'Ver CV'}
                                                </a>
                                            </Button>
                                            {persona.cv_estado && (
                                                <Badge variant={persona.cv_estado === 'procesado' ? 'default' : persona.cv_estado === 'error' ? 'destructive' : 'secondary'}>
                                                    {persona.cv_estado}
                                                </Badge>
                                            )}
                                        </>
                                    ) : (
                                        <span className="text-muted-foreground">Sin CV</span>
                                    )}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    {/* Datos Extra */}
                    {persona.datos_extra && (
                        <div className="mb-8 rounded border p-4">
                            <h2 className="mb-4 text-lg font-semibold">Datos Extra</h2>
                            <dl className="grid grid-cols-2 gap-x-6 gap-y-3">
                                <div>
                                    <dt className="text-muted-foreground text-sm">Estado Civil</dt>
                                    <dd>{persona.datos_extra.estado_civil ?? '-'}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm">Hijos</dt>
                                    <dd>{persona.datos_extra.hijos ?? '-'}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm">Localidad</dt>
                                    <dd>{persona.datos_extra.localidad ?? '-'}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm">Domicilio</dt>
                                    <dd>{persona.datos_extra.domicilio ?? '-'}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm">CP</dt>
                                    <dd>{persona.datos_extra.cp ?? '-'}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm">CURP</dt>
                                    <dd>{persona.datos_extra.curp ?? '-'}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm">RFC</dt>
                                    <dd>{persona.datos_extra.rfc ?? '-'}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm">IMSS</dt>
                                    <dd>{persona.datos_extra.imss ?? '-'}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm">Cuenta Banco</dt>
                                    <dd>{persona.datos_extra.cuenta_banco ?? '-'}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm">Banco</dt>
                                    <dd>{persona.datos_extra.banco_op ?? '-'}</dd>
                                </div>
                            </dl>
                        </div>
                    )}

                    {/* Documentos */}
                    <div className="mb-8 rounded border p-4">
                        <h2 className="mb-4 text-lg font-semibold">Documentos</h2>
                        {(persona.documentos ?? []).length > 0 ? (
                            <ul className="space-y-2">
                                {(persona.documentos ?? []).map((doc) => (
                                    <li key={doc.id} className="flex items-center justify-between rounded border px-3 py-2">
                                        <div>
                                            <span className="font-medium">{doc.tipo_documento}</span>
                                            {doc.notas && <span className="text-muted-foreground ml-2 text-sm">{doc.notas}</span>}
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {doc.fecha_emision && (
                                                <span className="text-muted-foreground text-sm">Emision: {formatDate(doc.fecha_emision)}</span>
                                            )}
                                            {doc.fecha_vigencia && (
                                                <span className="text-muted-foreground text-sm">Vigencia: {formatDate(doc.fecha_vigencia)}</span>
                                            )}
                                            {doc.media?.path && (
                                                <Button variant="outline" size="sm" asChild>
                                                    <a href={`/storage/${doc.media.path}`} target="_blank" rel="noopener noreferrer">
                                                        <ExternalLinkIcon className="size-4" />
                                                        Ver
                                                    </a>
                                                </Button>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-muted-foreground text-sm">No hay documentos registrados.</p>
                        )}
                    </div>

                    {/* Periodos Laborales */}
                    <div className="mb-8 rounded border p-4">
                        <h2 className="mb-4 text-lg font-semibold">Periodos Laborales</h2>
                        {(persona.periodos_laborales ?? []).length > 0 ? (
                            <ul className="space-y-2">
                                {(persona.periodos_laborales ?? []).map((periodo) => (
                                    <li key={periodo.id} className="flex items-center justify-between rounded border px-3 py-2">
                                        <div>
                                            <span className="font-medium">{periodo.puesto?.nombre ?? 'Puesto'}</span>
                                            <span className="text-muted-foreground ml-2 text-sm">
                                                {formatDate(periodo.fecha_inicio)} - {periodo.fecha_fin ? formatDate(periodo.fecha_fin) : 'Actual'}
                                            </span>
                                        </div>
                                        <Badge variant={periodo.estado === 'activo' ? 'default' : periodo.estado === 'terminado' ? 'secondary' : 'destructive'}>
                                            {periodo.estado}
                                        </Badge>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-muted-foreground text-sm">No hay periodos laborales registrados.</p>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
