import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosAprobacionDepartamento, CostosPermiso, Departamento, Usuario } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { Loader2Icon, PencilIcon } from 'lucide-react';
import { useState } from 'react';

type Props = {
    permiso: CostosPermiso;
    departamentos: Departamento[];
    usuarios: Usuario[];
    asignaciones: Record<number, CostosAprobacionDepartamento>;
};

export default function PermisosShow({ permiso, departamentos, usuarios, asignaciones }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/permisos' },
        { title: 'Niveles Aprobacion', href: '/admin/costos/permisos' },
        { title: permiso.descripcion, href: `/admin/costos/permisos/${permiso.id}` },
    ];

    const [selections, setSelections] = useState<Record<number, string>>(() => {
        const initial: Record<number, string> = {};
        for (const dept of departamentos) {
            const asignacion = asignaciones[dept.id];
            initial[dept.id] = asignacion?.aprobador_id ?? '';
        }
        return initial;
    });

    const [processing, setProcessing] = useState(false);

    const handleChange = (departamentoId: number, aprobadorId: string) => {
        setSelections((prev) => ({ ...prev, [departamentoId]: aprobadorId }));
    };

    const handleSave = () => {
        const payload = departamentos.map((dept) => ({
            departamento_id: dept.id,
            aprobador_id: selections[dept.id] || null,
        }));

        setProcessing(true);
        router.post(`/admin/costos/permisos/${permiso.id}/sync-departamentos`, {
            asignaciones: payload,
        }, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
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
                                        <th>Aprobador</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {departamentos.map((dept) => (
                                        <tr key={dept.id}>
                                            <td>{dept.descripcion}</td>
                                            <td>
                                                <select
                                                    className="select select-bordered select-sm w-full max-w-xs"
                                                    value={selections[dept.id] ?? ''}
                                                    onChange={(e) => handleChange(dept.id, e.target.value)}
                                                >
                                                    <option value="">Sin asignar</option>
                                                    {usuarios.map((u) => (
                                                        <option key={u.id} value={u.id}>
                                                            {u.name}
                                                        </option>
                                                    ))}
                                                </select>
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
