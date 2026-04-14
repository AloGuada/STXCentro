import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, Trash2, UserPlus } from 'lucide-react';

type Usuario = { id: string; name: string; email: string };
type Asignado = Usuario & { puede_escribir: boolean };

type Props = {
    carpeta: { id: number; nombre: string; descripcion: string | null };
    asignados: Asignado[];
    disponibles: Usuario[];
};

export default function CarpetaAccesos({ carpeta, asignados, disponibles }: Props) {
    const [usuarioId, setUsuarioId] = useState('');
    const [puedeEscribir, setPuedeEscribir] = useState(true);
    const [procesando, setProcesando] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Dirección General', href: '/admin/dg' },
        { title: carpeta.nombre, href: `/admin/dg/carpetas/${carpeta.id}` },
        { title: 'Accesos', href: `/admin/dg/carpetas/${carpeta.id}/accesos` },
    ];

    const agregar = (e: React.FormEvent) => {
        e.preventDefault();
        if (!usuarioId) return;
        setProcesando(true);
        router.post(
            `/admin/dg/carpetas/${carpeta.id}/accesos`,
            { usuario_id: usuarioId, puede_escribir: puedeEscribir },
            {
                preserveScroll: true,
                onSuccess: () => setUsuarioId(''),
                onFinish: () => setProcesando(false),
            },
        );
    };

    const togglePermiso = (usuario: Asignado) => {
        router.patch(
            `/admin/dg/carpetas/${carpeta.id}/accesos/${usuario.id}`,
            {},
            { preserveScroll: true },
        );
    };

    const quitar = (usuario: Asignado) => {
        if (!confirm(`¿Quitar acceso a ${usuario.name}?`)) return;
        router.delete(`/admin/dg/carpetas/${carpeta.id}/accesos/${usuario.id}`, {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Accesos · ${carpeta.nombre}`} />

            <div className="p-6 space-y-6 max-w-4xl">
                <div className="flex items-start gap-3">
                    <Link href={`/admin/dg/carpetas/${carpeta.id}`} className="btn btn-ghost btn-sm">
                        <ChevronLeft className="size-4" /> Volver
                    </Link>
                    <div>
                        <h1 className="text-2xl font-semibold">Accesos · {carpeta.nombre}</h1>
                        <p className="text-base-content/60 mt-1">Gestiona qué usuarios ven y escriben en esta carpeta.</p>
                    </div>
                </div>

                <section className="bg-base-100 border border-base-300 rounded-lg p-4">
                    <h2 className="font-semibold mb-3">Agregar usuario</h2>
                    <form onSubmit={agregar} className="flex flex-wrap items-end gap-3">
                        <label className="form-control flex-1 min-w-[240px]">
                            <span className="label-text text-xs mb-1">Usuario</span>
                            <select
                                value={usuarioId}
                                onChange={(e) => setUsuarioId(e.target.value)}
                                className="select select-bordered"
                                required
                            >
                                <option value="">Selecciona un usuario…</option>
                                {disponibles.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.name} — {u.email}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="label cursor-pointer gap-2">
                            <input
                                type="checkbox"
                                checked={puedeEscribir}
                                onChange={(e) => setPuedeEscribir(e.target.checked)}
                                className="checkbox checkbox-sm"
                            />
                            <span className="label-text">Puede escribir</span>
                        </label>
                        <button type="submit" disabled={procesando || !usuarioId} className="btn btn-primary">
                            <UserPlus className="size-4" /> Agregar
                        </button>
                    </form>
                </section>

                <section className="bg-base-100 border border-base-300 rounded-lg overflow-hidden">
                    <h2 className="font-semibold px-4 py-3 border-b border-base-300">
                        Usuarios con acceso ({asignados.length})
                    </h2>
                    {asignados.length === 0 ? (
                        <div className="p-8 text-center text-base-content/60">
                            Aún no hay usuarios asignados a esta carpeta.
                        </div>
                    ) : (
                        <table className="table">
                            <thead className="bg-base-200/50">
                                <tr>
                                    <th>Usuario</th>
                                    <th>Email</th>
                                    <th className="w-[18%]">Puede escribir</th>
                                    <th className="w-[10%]">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                {asignados.map((u) => (
                                    <tr key={u.id}>
                                        <td className="font-medium">{u.name}</td>
                                        <td className="text-sm text-base-content/70">{u.email}</td>
                                        <td>
                                            <input
                                                type="checkbox"
                                                checked={u.puede_escribir}
                                                onChange={() => togglePermiso(u)}
                                                className="toggle toggle-sm toggle-primary"
                                            />
                                        </td>
                                        <td>
                                            <button
                                                type="button"
                                                onClick={() => quitar(u)}
                                                className="btn btn-xs btn-ghost text-error"
                                                title="Quitar acceso"
                                            >
                                                <Trash2 className="size-3.5" />
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
