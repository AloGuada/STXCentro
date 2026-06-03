import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CobDocumentoSeccion } from '@/types/models';
import { Head, router, useForm } from '@inertiajs/react';
import { Loader2Icon, PencilIcon, PlusIcon, Trash2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cobranza', href: '/admin/cob/dashboard' },
    { title: 'Secciones de Documentación', href: '/admin/cob/documento-secciones' },
];

type Props = {
    secciones: CobDocumentoSeccion[];
};

export default function DocumentoSeccionesIndex({ secciones }: Props) {
    const form = useForm({ nombre: '', orden: String(secciones.length), activo: true });

    const handleCreate = (e: FormEvent) => {
        e.preventDefault();
        form.post('/admin/cob/documento-secciones', {
            preserveScroll: true,
            onSuccess: () => form.reset('nombre'),
        });
    };

    const renombrar = (seccion: CobDocumentoSeccion) => {
        const nombre = window.prompt('Nuevo nombre de la sección:', seccion.nombre);
        if (!nombre?.trim() || nombre.trim() === seccion.nombre) return;
        router.put(
            `/admin/cob/documento-secciones/${seccion.id}`,
            { nombre: nombre.trim(), orden: seccion.orden, activo: seccion.activo },
            { preserveScroll: true },
        );
    };

    const toggleActivo = (seccion: CobDocumentoSeccion) => {
        router.put(
            `/admin/cob/documento-secciones/${seccion.id}`,
            { nombre: seccion.nombre, orden: seccion.orden, activo: !seccion.activo },
            { preserveScroll: true },
        );
    };

    const eliminar = (seccion: CobDocumentoSeccion) => {
        if (!window.confirm(`¿Eliminar la sección "${seccion.nombre}"?`)) return;
        router.delete(`/admin/cob/documento-secciones/${seccion.id}`, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Secciones de Documentación" />

            <div className="space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Secciones de Documentación</h1>
                    <p className="text-sm text-base-content/60">
                        Catálogo de secciones disponibles en la documentación de cada obra. Las secciones inactivas no se muestran en las obras.
                    </p>
                </div>

                <form onSubmit={handleCreate} className="flex items-end gap-3">
                    <div className="flex-1">
                        <label className="mb-1 block text-sm font-medium">Nueva sección</label>
                        <Input
                            value={form.data.nombre}
                            onChange={(e) => form.setData('nombre', e.target.value)}
                            placeholder="Nombre de la sección"
                        />
                        {form.errors.nombre && <p className="mt-1 text-sm text-error">{form.errors.nombre}</p>}
                    </div>
                    <div className="w-24">
                        <label className="mb-1 block text-sm font-medium">Orden</label>
                        <Input type="number" min="0" value={form.data.orden} onChange={(e) => form.setData('orden', e.target.value)} />
                    </div>
                    <Button type="submit" disabled={form.processing || !form.data.nombre.trim()}>
                        {form.processing ? <Loader2Icon className="size-4 animate-spin" /> : <PlusIcon className="size-4" />}
                        Agregar
                    </Button>
                </form>

                <div className="overflow-x-auto rounded-box border border-base-300">
                    <table className="table">
                        <thead>
                            <tr>
                                <th className="w-16 text-right">Orden</th>
                                <th>Nombre</th>
                                <th className="text-right">Carpetas</th>
                                <th className="text-right">Archivos</th>
                                <th>Activo</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {secciones.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="py-8 text-center text-base-content/60">
                                        No hay secciones registradas
                                    </td>
                                </tr>
                            ) : (
                                secciones.map((seccion) => (
                                    <tr key={seccion.id} className="hover">
                                        <td className="text-right font-mono text-sm">{seccion.orden}</td>
                                        <td className="font-medium">{seccion.nombre}</td>
                                        <td className="text-right">{seccion.carpetas_count ?? 0}</td>
                                        <td className="text-right">{seccion.archivos_count ?? 0}</td>
                                        <td>
                                            <input
                                                type="checkbox"
                                                className="toggle toggle-sm toggle-success"
                                                checked={seccion.activo}
                                                onChange={() => toggleActivo(seccion)}
                                            />
                                        </td>
                                        <td>
                                            <div className="flex justify-end gap-1">
                                                <button type="button" className="btn btn-ghost btn-xs" title="Renombrar" onClick={() => renombrar(seccion)}>
                                                    <PencilIcon className="size-3.5" />
                                                </button>
                                                <button type="button" className="btn btn-ghost btn-xs text-error" title="Eliminar" onClick={() => eliminar(seccion)}>
                                                    <Trash2Icon className="size-3.5" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
