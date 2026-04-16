import { DataTable, type Column } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, RhPersona, RhPeriodoLaboral } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { BadgeCheckIcon, CreditCardIcon, FileIcon, FileTextIcon, PencilIcon, SearchIcon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'RH', href: '/admin/rh/skills' },
    { title: 'Personas', href: '/admin/rh/personas' },
];

const getPeriodoActivo = (persona: RhPersona): RhPeriodoLaboral | undefined =>
    persona.periodos_laborales?.find((p) => p.estado === 'activo') ?? persona.periodos_laborales?.[0];

const calcularEdad = (fechaNacimiento: string | null): string => {
    if (!fechaNacimiento) return '-';
    const hoy = new Date();
    const nacimiento = new Date(fechaNacimiento);
    let edad = hoy.getFullYear() - nacimiento.getFullYear();
    const m = hoy.getMonth() - nacimiento.getMonth();
    if (m < 0 || (m === 0 && hoy.getDate() < nacimiento.getDate())) edad--;
    return `${edad} años`;
};

type Props = {
    personas: PaginatedData<RhPersona>;
    filters: { search?: string; sort_by?: string; sort_dir?: 'asc' | 'desc'; estado?: string };
};

export default function PersonasIndex({ personas, filters }: Props) {
    const [selectedPersona, setSelectedPersona] = useState<RhPersona | null>(null);

    const handleEstadoChange = (value: string) => {
        const params = Object.fromEntries(new URLSearchParams(window.location.search));
        router.get(window.location.pathname, { ...params, estado: value || undefined, page: undefined }, { preserveState: true, replace: true });
    };

    const columns: Column<RhPersona>[] = [
        {
            key: 'numero_empleado',
            label: 'No. Emp.',
            render: (persona) => getPeriodoActivo(persona)?.numero_empleado ?? '-',
        },
        { key: 'nombre', label: 'Nombre', sortable: true },
        { key: 'apellido', label: 'Apellido', sortable: true },
        {
            key: 'puesto',
            label: 'Puesto',
            render: (persona) => {
                const periodo = getPeriodoActivo(persona);
                return (
                    <div>
                        <div>{periodo?.puesto?.nombre ?? '-'}</div>
                        {periodo?.puesto?.departamento?.descripcion && (
                            <div className="text-muted-foreground text-xs">{periodo.puesto.departamento.descripcion}</div>
                        )}
                    </div>
                );
            },
        },
        {
            key: 'edad',
            label: 'Edad',
            sortable: true,
            sortKey: 'fecha_nacimiento',
            render: (persona) => calcularEdad(persona.fecha_nacimiento),
        },
        {
            key: 'fecha_inicio',
            label: 'Inicio',
            render: (persona) => getPeriodoActivo(persona)?.fecha_inicio ?? '-',
        },
        {
            key: 'estado',
            label: 'Estado',
            render: (persona) => {
                const activo = persona.periodos_laborales?.some((p) => p.estado === 'activo');
                return (
                    <Badge variant={activo ? 'success' : 'ghost'}>
                        {activo ? 'Activo' : 'Inactivo'}
                    </Badge>
                );
            },
        },
        {
            key: 'acciones',
            label: 'Acciones',
            render: (persona) => {
                const periodo = getPeriodoActivo(persona);
                return (
                    <div className="flex items-center gap-1">
                        <button
                            type="button"
                            className="btn btn-ghost btn-xs"
                            title="Ver información"
                            onClick={(e) => {
                                e.stopPropagation();
                                e.preventDefault();
                                setSelectedPersona(persona);
                            }}
                        >
                            <SearchIcon className="size-4" />
                        </button>
                        {periodo && (
                            <>
                                <a
                                    href={`/admin/rh/periodos-laborales/${periodo.id}/contrato-pdf?tipo=planta`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    onClick={(e) => e.stopPropagation()}
                                    className="btn btn-ghost btn-xs"
                                    title="Contrato Planta"
                                >
                                    <FileTextIcon className="size-4" />
                                </a>
                                <a
                                    href={`/admin/rh/periodos-laborales/${periodo.id}/contrato-pdf?tipo=obra`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    onClick={(e) => e.stopPropagation()}
                                    className="btn btn-ghost btn-xs"
                                    title="Contrato Obra"
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
                            </>
                        )}
                    </div>
                );
            },
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Personas" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={personas}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar personas..."
                    createHref="/admin/rh/personas/create"
                    createLabel="Nueva Persona"
                    emptyMessage="No hay personas registradas"
                    getRowHref={(persona) => `/admin/rh/personas/${persona.id}`}
                    sortBy={filters.sort_by}
                    sortDir={filters.sort_dir}
                >
                    <select
                        className="select select-bordered select-sm"
                        value={filters.estado ?? ''}
                        onChange={(e) => handleEstadoChange(e.target.value)}
                    >
                        <option value="">Todos</option>
                        <option value="activo">Activos</option>
                        <option value="inactivo">Inactivos</option>
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
                        {persona.foto?.path && (
                            <div className="flex justify-center">
                                <img
                                    src={`/storage/${persona.foto.path}`}
                                    alt={`${persona.nombre} ${persona.apellido}`}
                                    className="size-24 rounded-full object-cover"
                                />
                            </div>
                        )}

                        <div>
                            <div className="mb-2 flex items-center justify-between">
                                <h4 className="text-sm font-semibold uppercase tracking-wide">Datos Personales</h4>
                                <Link href={`/admin/rh/personas/${persona.id}/edit`} className="btn btn-ghost btn-xs" title="Editar">
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

                        {extras && (
                            <div>
                                <h4 className="mb-2 text-sm font-semibold uppercase tracking-wide">Datos Adicionales</h4>
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

                        <div>
                            <h4 className="mb-2 text-sm font-semibold uppercase tracking-wide">Periodos Laborales</h4>
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
                                                        <Badge variant={p.estado === 'activo' ? 'success' : 'ghost'}>
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

                        {documentos.length > 0 && (
                            <div>
                                <div className="mb-2 flex items-center justify-between">
                                    <h4 className="text-sm font-semibold uppercase tracking-wide">Documentos</h4>
                                    <Link href={`/admin/rh/personas/${persona.id}`} className="btn btn-ghost btn-xs" title="Gestionar documentos">
                                        <PencilIcon className="size-3.5" />
                                    </Link>
                                </div>
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
                            </div>
                        )}
                    </div>
                )}

                <DialogFooter>
                    <DialogClose>Cerrar</DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
