import { DataTable, type Column } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, RhPeriodoLaboral, RhPersona, RhPersonaDocumento } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { BadgeCheckIcon, CreditCardIcon, FileIcon, FileTextIcon, PencilIcon, SearchIcon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'RH', href: '/admin/rh/skills' },
    { title: 'Periodos Laborales', href: '/admin/rh/periodos-laborales' },
];

const estadoVariant = (estado: string) => {
    switch (estado) {
        case 'activo':
            return 'success';
        case 'terminado':
            return 'secondary';
        case 'baja':
            return 'ghost';
        default:
            return 'secondary';
    }
};

const ESTADOS = [
    { value: '', label: 'Todos' },
    { value: 'activo', label: 'Activo' },
    { value: 'terminado', label: 'Terminado' },
    { value: 'baja', label: 'Baja' },
];

type Props = {
    periodos: PaginatedData<RhPeriodoLaboral>;
    filters: { search?: string; estado?: string };
};

export default function PeriodosLaboralesIndex({ periodos, filters }: Props) {
    const [selectedPersona, setSelectedPersona] = useState<RhPersona | null>(null);

    const handleEstadoChange = (estado: string) => {
        router.get('/admin/rh/periodos-laborales', { ...filters, estado: estado || undefined }, { preserveState: true, preserveScroll: true });
    };

    const columns: Column<RhPeriodoLaboral>[] = [
        {
            key: 'numero_empleado',
            label: 'No. Empleado',
            render: (periodo) => periodo.numero_empleado ?? '-',
        },
        {
            key: 'persona_id',
            label: 'Persona',
            render: (periodo) => periodo.persona ? `${periodo.persona.nombre} ${periodo.persona.apellido}` : '-',
        },
        {
            key: 'puesto_id',
            label: 'Puesto',
            render: (periodo) => periodo.puesto?.nombre ?? '-',
        },
        {
            key: 'departamento',
            label: 'Departamento',
            render: (periodo) => periodo.puesto?.departamento?.nombre ?? '-',
        },
        {
            key: 'requisicion_id',
            label: 'Requisición',
            render: (periodo) => periodo.requisicion?.folio ?? '-',
        },
        { key: 'fecha_inicio', label: 'Fecha Inicio' },
        {
            key: 'estado',
            label: 'Estado',
            render: (periodo) => <Badge variant={estadoVariant(periodo.estado)}>{periodo.estado}</Badge>,
        },
        {
            key: 'acciones',
            label: 'Acciones',
            render: (periodo) => (
                <div className="flex items-center gap-1">
                    {periodo.persona && (
                        <button
                            type="button"
                            className="btn btn-ghost btn-xs"
                            title="Ver información de persona"
                            onClick={(e) => {
                                e.stopPropagation();
                                e.preventDefault();
                                setSelectedPersona(periodo.persona!);
                            }}
                        >
                            <SearchIcon className="size-4" />
                        </button>
                    )}
                    <a
                        href={`/admin/rh/periodos-laborales/${periodo.id}/contrato-pdf`}
                        target="_blank"
                        rel="noopener noreferrer"
                        onClick={(e) => e.stopPropagation()}
                        className="btn btn-ghost btn-xs"
                        title="Descargar Contrato"
                    >
                        <FileTextIcon className="size-4" />
                    </a>
                    <a
                        href={`/admin/rh/periodos-laborales/${periodo.id}/gafete-pdf`}
                        target="_blank"
                        rel="noopener noreferrer"
                        onClick={(e) => e.stopPropagation()}
                        className="btn btn-ghost btn-xs"
                        title="Gafete"
                    >
                        <BadgeCheckIcon className="size-4" />
                    </a>
                    <a
                        href={`/admin/rh/periodos-laborales/${periodo.id}/tarjeta-pdf`}
                        target="_blank"
                        rel="noopener noreferrer"
                        onClick={(e) => e.stopPropagation()}
                        className="btn btn-ghost btn-xs"
                        title="Tarjeta"
                    >
                        <CreditCardIcon className="size-4" />
                    </a>
                </div>
            ),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Periodos Laborales" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={periodos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar periodos laborales..."
                    createHref="/admin/rh/periodos-laborales/create"
                    createLabel="Nuevo Periodo Laboral"
                    emptyMessage="No hay periodos laborales registrados"
                    getRowHref={(periodo) => `/admin/rh/periodos-laborales/${periodo.id}/edit`}
                >
                    <select
                        className="select select-bordered select-sm"
                        value={filters.estado ?? ''}
                        onChange={(e) => handleEstadoChange(e.target.value)}
                    >
                        {ESTADOS.map((e) => (
                            <option key={e.value} value={e.value}>{e.label}</option>
                        ))}
                    </select>
                </DataTable>
            </div>

            <PersonaModal persona={selectedPersona} onClose={() => setSelectedPersona(null)} />
        </AppLayout>
    );
}

function InfoRow({ label, value }: { label: string; value: string | number | null | undefined }) {
    return (
        <div className="flex justify-between border-b py-1.5 last:border-b-0">
            <span className="text-muted-foreground text-sm">{label}</span>
            <span className="text-sm font-medium">{value || '-'}</span>
        </div>
    );
}

function PersonaModal({ persona, onClose }: { persona: RhPersona | null; onClose: () => void }) {
    const extras = persona?.datos_extra;
    const periodos = persona?.periodos_laborales ?? [];
    const documentos = persona?.documentos ?? [];

    return (
        <Dialog open={!!persona} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {persona ? `${persona.nombre} ${persona.apellido}` : 'Persona'}
                    </DialogTitle>
                </DialogHeader>

                {persona && (
                    <div className="max-h-[70vh] space-y-4 overflow-y-auto">
                        {/* Foto */}
                        {persona.foto?.path && (
                            <div className="flex justify-center">
                                <img
                                    src={`/storage/${persona.foto.path}`}
                                    alt={`${persona.nombre} ${persona.apellido}`}
                                    className="size-24 rounded-full object-cover"
                                />
                            </div>
                        )}

                        {/* Datos personales */}
                        <div>
                            <div className="mb-2 flex items-center justify-between">
                                <h4 className="text-sm font-semibold uppercase tracking-wide">Datos Personales</h4>
                                <Link href={`/admin/rh/personas/${persona.id}/edit`} className="btn btn-ghost btn-xs" title="Editar datos personales">
                                    <PencilIcon className="size-3.5" />
                                </Link>
                            </div>
                            <div className="rounded border p-3">
                                <InfoRow label="Nombre" value={`${persona.nombre} ${persona.apellido}`} />
                                <InfoRow label="Email" value={persona.email} />
                                <InfoRow label="Teléfono" value={persona.telefono} />
                                <InfoRow label="Fecha de Nacimiento" value={persona.fecha_nacimiento ? new Date(persona.fecha_nacimiento).toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric', timeZone: 'UTC' }) : null} />
                            </div>
                        </div>

                        {/* Datos extra */}
                        {extras && (
                            <div>
                                <div className="mb-2 flex items-center justify-between">
                                    <h4 className="text-sm font-semibold uppercase tracking-wide">Datos Adicionales</h4>
                                    <Link href={`/admin/rh/personas/${persona.id}/edit`} className="btn btn-ghost btn-xs" title="Editar datos adicionales">
                                        <PencilIcon className="size-3.5" />
                                    </Link>
                                </div>
                                <div className="rounded border p-3">
                                    <InfoRow label="CURP" value={extras.curp} />
                                    <InfoRow label="RFC" value={extras.rfc} />
                                    <InfoRow label="No. IMSS" value={extras.imss} />
                                    <InfoRow label="No. INE" value={extras.numero_ine} />
                                    <InfoRow label="Estado Civil" value={extras.estado_civil} />
                                    <InfoRow label="Hijos" value={extras.hijos} />
                                    <InfoRow label="Domicilio" value={extras.domicilio} />
                                    <InfoRow label="Localidad" value={extras.localidad} />
                                    <InfoRow label="C.P." value={extras.cp} />
                                    <InfoRow label="Cuenta Banco" value={extras.cuenta_banco} />
                                    <InfoRow label="Banco" value={extras.banco_op} />
                                    <InfoRow label="C. Infonavit" value={extras.c_infonavit} />
                                    <InfoRow label="C. Fonacot" value={extras.c_fonacot} />
                                    <InfoRow label="Nombre Padre" value={extras.nombre_padre} />
                                    <InfoRow label="Nombre Madre" value={extras.nombre_madre} />
                                </div>
                            </div>
                        )}

                        {/* Periodos Laborales */}
                        <div>
                            <div className="mb-2 flex items-center justify-between">
                                <h4 className="text-sm font-semibold uppercase tracking-wide">Periodos Laborales</h4>
                            </div>
                            {periodos.length > 0 ? (
                                <div className="overflow-auto rounded border">
                                    <table className="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Puesto</th>
                                                <th>Fecha Inicio</th>
                                                <th>Fecha Fin</th>
                                                <th>Estado</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {periodos.map((p) => (
                                                <tr key={p.id}>
                                                    <td>{p.puesto?.nombre ?? '-'}</td>
                                                    <td>{p.fecha_inicio}</td>
                                                    <td>{p.fecha_fin ?? '-'}</td>
                                                    <td>
                                                        <Badge variant={p.estado === 'activo' ? 'success' : p.estado === 'baja' ? 'ghost' : 'secondary'}>
                                                            {p.estado}
                                                        </Badge>
                                                    </td>
                                                    <td>
                                                        <Link href={`/admin/rh/periodos-laborales/${p.id}/edit`} className="btn btn-ghost btn-xs" title="Editar periodo">
                                                            <PencilIcon className="size-3.5" />
                                                        </Link>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <p className="text-muted-foreground text-sm">Sin periodos laborales.</p>
                            )}
                        </div>

                        {/* Documentos */}
                        <div>
                            <div className="mb-2 flex items-center justify-between">
                                <h4 className="text-sm font-semibold uppercase tracking-wide">Documentos</h4>
                                <Link href={`/admin/rh/personas/${persona.id}`} className="btn btn-ghost btn-xs" title="Gestionar documentos">
                                    <PencilIcon className="size-3.5" />
                                </Link>
                            </div>
                            {documentos.length > 0 ? (
                                <div className="flex flex-wrap gap-2">
                                    {documentos.map((doc) => (
                                        <a
                                            key={doc.id}
                                            href={doc.media?.path ? `/storage/${doc.media.path}` : '#'}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="btn btn-outline btn-sm"
                                        >
                                            <FileIcon className="size-4" />
                                            {doc.tipo_documento}
                                        </a>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-muted-foreground text-sm">Sin documentos registrados.</p>
                            )}
                        </div>
                    </div>
                )}

                <DialogFooter>
                    <DialogClose>Cerrar</DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
