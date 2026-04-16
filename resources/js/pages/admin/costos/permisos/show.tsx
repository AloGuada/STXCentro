import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosPermiso, Usuario } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronDownIcon, Loader2Icon, PencilIcon, XIcon } from 'lucide-react';
import { useRef, useState } from 'react';

type Departamento = { id: number; descripcion: string };

type Props = {
    permiso: CostosPermiso;
    departamentos: Departamento[];
    usuarios: Usuario[];
    asignaciones: Record<number, string[]>;
};

function MultiUserSelect({
    usuarios,
    selected,
    onChange,
}: {
    usuarios: Usuario[];
    selected: string[];
    onChange: (ids: string[]) => void;
}) {
    const [open, setOpen] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);

    const toggle = (id: string) => {
        onChange(selected.includes(id) ? selected.filter((s) => s !== id) : [...selected, id]);
    };

    const remove = (id: string) => {
        onChange(selected.filter((s) => s !== id));
    };

    const selectedUsers = usuarios.filter((u) => selected.includes(u.id));

    return (
        <div ref={containerRef} className="relative w-full max-w-md">
            <div
                className="flex min-h-9 cursor-pointer flex-wrap items-center gap-1 rounded-md border border-base-300 bg-base-100 px-2 py-1"
                onClick={() => setOpen(!open)}
            >
                {selectedUsers.length === 0 && <span className="text-sm text-base-content/50">Sin asignar</span>}
                {selectedUsers.map((u) => (
                    <Badge key={u.id} variant="primary" className="gap-1 text-xs">
                        {u.name}
                        <button
                            type="button"
                            className="ml-0.5 hover:opacity-70"
                            onClick={(e) => {
                                e.stopPropagation();
                                remove(u.id);
                            }}
                        >
                            <XIcon className="size-3" />
                        </button>
                    </Badge>
                ))}
                <ChevronDownIcon className="ml-auto size-4 shrink-0 text-base-content/50" />
            </div>

            {open && (
                <div className="absolute z-50 mt-1 max-h-48 w-full overflow-y-auto rounded-md border border-base-300 bg-base-100 shadow-lg">
                    {usuarios.map((u) => (
                        <label
                            key={u.id}
                            className="flex cursor-pointer items-center gap-2 px-3 py-1.5 hover:bg-base-200"
                        >
                            <Checkbox checked={selected.includes(u.id)} onCheckedChange={() => toggle(u.id)} />
                            <span className="text-sm">{u.name}</span>
                        </label>
                    ))}
                    {usuarios.length === 0 && (
                        <div className="px-3 py-2 text-sm text-base-content/50">No hay usuarios con el rol requerido</div>
                    )}
                </div>
            )}
        </div>
    );
}

export default function PermisosShow({ permiso, departamentos, usuarios, asignaciones }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/permisos' },
        { title: 'Niveles Aprobacion', href: '/admin/costos/permisos' },
        { title: permiso.descripcion, href: `/admin/costos/permisos/${permiso.id}` },
    ];

    const [selections, setSelections] = useState<Record<number, string[]>>(() => {
        const initial: Record<number, string[]> = {};
        for (const dept of departamentos) {
            initial[dept.id] = asignaciones[dept.id] ?? [];
        }
        return initial;
    });

    const [processing, setProcessing] = useState(false);

    const handleChange = (departamentoId: number, ids: string[]) => {
        setSelections((prev) => ({ ...prev, [departamentoId]: ids }));
    };

    const handleSave = () => {
        const payload = departamentos.map((dept) => ({
            departamento_id: dept.id,
            aprobador_ids: selections[dept.id] ?? [],
        }));

        setProcessing(true);
        router.post(
            `/admin/costos/permisos/${permiso.id}/sync-departamentos`,
            {
                asignaciones: payload,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${permiso.descripcion} - Nivel ${permiso.nivel}`} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">{permiso.descripcion}</h1>
                        <p className="text-base-content/60">Nivel {permiso.nivel}</p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={`/admin/costos/permisos/${permiso.id}/edit`}>
                            <PencilIcon className="size-4" />
                            Editar
                        </Link>
                    </Button>
                </div>

                <div className="card bg-base-100 shadow">
                    <div className="card-body">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="card-title">Asignacion de Aprobadores por Departamento</h2>
                            <Button onClick={handleSave} disabled={processing}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Guardar Asignaciones
                            </Button>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>Departamento</th>
                                        <th>Aprobadores</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {departamentos.map((dept) => (
                                        <tr key={dept.id}>
                                            <td>{dept.descripcion}</td>
                                            <td>
                                                <MultiUserSelect
                                                    usuarios={usuarios}
                                                    selected={selections[dept.id] ?? []}
                                                    onChange={(ids) => handleChange(dept.id, ids)}
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
