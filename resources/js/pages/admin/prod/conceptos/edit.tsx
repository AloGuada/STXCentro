import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Concepto, Obra } from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = {
    concepto: Concepto & { obra: Obra };
};

export default function ConceptosEdit({ concepto }: Props) {
    const obra = concepto.obra;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/cortes' },
        { title: 'Conceptos', href: '/admin/prod/conceptos' },
        { title: `${obra.no} - ${obra.descripcion}`, href: `/admin/prod/conceptos/obra/${obra.id}` },
        { title: concepto.marca, href: `/admin/prod/conceptos/${concepto.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        obra_id: String(concepto.obra_id),
        marca: concepto.marca,
        descripcion: concepto.descripcion,
        peso_unitario: String(concepto.peso_unitario),
        version: String(concepto.version),
        activo: concepto.activo,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/prod/conceptos/${concepto.id}`);
    };

    const handleDelete = () => {
        if (confirm('Estas seguro de eliminar este concepto?')) {
            router.delete(`/admin/prod/conceptos/${concepto.id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${concepto.marca}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Editar Concepto</h1>
                    <p className="mb-4 text-sm text-gray-500">Obra: {obra.no} - {obra.descripcion}</p>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Marca" htmlFor="marca" error={errors.marca} required>
                            <Input
                                id="marca"
                                value={data.marca}
                                onChange={(e) => setData('marca', e.target.value)}
                            />
                        </FormField>

                        <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                            />
                        </FormField>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Peso Unitario (kg)" htmlFor="peso_unitario" error={errors.peso_unitario} required>
                                <Input
                                    id="peso_unitario"
                                    type="number"
                                    step="0.001"
                                    min="0"
                                    value={data.peso_unitario}
                                    onChange={(e) => setData('peso_unitario', e.target.value)}
                                />
                            </FormField>

                            <FormField label="Version" htmlFor="version" error={errors.version}>
                                <Input
                                    id="version"
                                    type="number"
                                    min="1"
                                    value={data.version}
                                    onChange={(e) => setData('version', e.target.value)}
                                />
                            </FormField>
                        </div>

                        <FormField label="Estado" htmlFor="activo">
                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={data.activo}
                                    onChange={(e) => setData('activo', e.target.checked)}
                                    className="rounded border-gray-300"
                                />
                                <span className="text-sm">Activo</span>
                            </label>
                        </FormField>

                        <div className="flex justify-between">
                            <Button type="button" variant="destructive" onClick={handleDelete}>
                                Eliminar
                            </Button>
                            <div className="flex gap-2">
                                <Button variant="outline" asChild>
                                    <Link href={`/admin/prod/conceptos/obra/${obra.id}`}>Cancelar</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Loader2Icon className="size-4 animate-spin" />}
                                    Guardar
                                </Button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
