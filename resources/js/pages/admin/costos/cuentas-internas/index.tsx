import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Usuario } from '@/types/models';
import { Head, router, useForm } from '@inertiajs/react';
import { Loader2Icon, Trash2Icon } from 'lucide-react';
import { type FormEvent, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/ordenes-compra' },
    { title: 'Cuentas Internas', href: '/admin/costos/cuentas-internas' },
];

type UsuarioCuenta = {
    id: string;
    name: string;
    email: string;
    roles: string[];
    created_at: string;
};

type Props = {
    usuarios: UsuarioCuenta[];
    todosUsuarios: Pick<Usuario, 'id' | 'name' | 'email'>[];
    costosRoles: string[];
};

const ROLE_LABELS: Record<string, string> = {
    compras: 'Compras',
    almacen: 'Almacén',
    contabilidad: 'Contabilidad',
};

const ROLE_COLORS: Record<string, string> = {
    compras: 'badge-primary',
    almacen: 'badge-accent',
    contabilidad: 'badge-info',
};

export default function CuentasInternasIndex({ usuarios, todosUsuarios, costosRoles }: Props) {
    const [showModal, setShowModal] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        usuario_id: '',
        rol: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/costos/cuentas-internas', {
            preserveScroll: true,
            onSuccess: () => {
                setShowModal(false);
                reset();
            },
        });
    };

    const handleRemoveRole = (usuarioId: string, rol: string) => {
        if (confirm(`Quitar rol ${ROLE_LABELS[rol] ?? rol} a este usuario?`)) {
            router.delete(`/admin/costos/cuentas-internas/${usuarioId}/${rol}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Cuentas Internas" />

            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Cuentas Internas - Costos</h1>
                    <Button onClick={() => setShowModal(true)}>Asignar Rol</Button>
                </div>

                {usuarios.length === 0 ? (
                    <p className="text-base-content/60">No hay usuarios con roles de costos asignados.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="table table-sm">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Roles</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                {usuarios.map((u) => (
                                    <tr key={u.id}>
                                        <td className="font-medium">{u.name}</td>
                                        <td>{u.email}</td>
                                        <td>
                                            <div className="flex gap-1">
                                                {u.roles.map((r) => (
                                                    <span key={r} className={`badge ${ROLE_COLORS[r] ?? 'badge-ghost'}`}>
                                                        {ROLE_LABELS[r] ?? r}
                                                    </span>
                                                ))}
                                            </div>
                                        </td>
                                        <td>
                                            <div className="flex gap-1">
                                                {u.roles.map((r) => (
                                                    <button
                                                        key={r}
                                                        className="btn btn-ghost btn-xs text-error"
                                                        onClick={() => handleRemoveRole(u.id, r)}
                                                        title={`Quitar ${ROLE_LABELS[r] ?? r}`}
                                                    >
                                                        <Trash2Icon className="size-3" /> {ROLE_LABELS[r] ?? r}
                                                    </button>
                                                ))}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* Modal Asignar Rol */}
                {showModal && (
                    <dialog className="modal modal-open">
                        <div className="modal-box">
                            <h3 className="font-bold text-lg mb-4">Asignar Rol de Costos</h3>
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div className="form-control">
                                    <label className="label" htmlFor="usuario_id">
                                        <span className="label-text">Usuario</span>
                                    </label>
                                    <select
                                        id="usuario_id"
                                        className="select select-bordered w-full"
                                        value={data.usuario_id}
                                        onChange={(e) => setData('usuario_id', e.target.value)}
                                    >
                                        <option value="">Seleccionar usuario...</option>
                                        {todosUsuarios.map((u) => (
                                            <option key={u.id} value={u.id}>
                                                {u.name} ({u.email})
                                            </option>
                                        ))}
                                    </select>
                                    {errors.usuario_id && <span className="text-error text-sm mt-1">{errors.usuario_id}</span>}
                                </div>

                                <div className="form-control">
                                    <label className="label" htmlFor="rol">
                                        <span className="label-text">Rol</span>
                                    </label>
                                    <select
                                        id="rol"
                                        className="select select-bordered w-full"
                                        value={data.rol}
                                        onChange={(e) => setData('rol', e.target.value)}
                                    >
                                        <option value="">Seleccionar rol...</option>
                                        {costosRoles.map((r) => (
                                            <option key={r} value={r}>
                                                {ROLE_LABELS[r] ?? r}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.rol && <span className="text-error text-sm mt-1">{errors.rol}</span>}
                                </div>

                                <div className="modal-action">
                                    <Button type="button" variant="outline" onClick={() => { setShowModal(false); reset(); }}>
                                        Cancelar
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Loader2Icon className="size-4 animate-spin" />}
                                        Asignar
                                    </Button>
                                </div>
                            </form>
                        </div>
                        <div className="modal-backdrop" onClick={() => { setShowModal(false); reset(); }}></div>
                    </dialog>
                )}
            </div>
        </AppLayout>
    );
}
