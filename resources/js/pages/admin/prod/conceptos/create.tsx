import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Obra } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = {
    obra: Obra;
};

export default function ConceptosCreate({ obra }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/cortes' },
        { title: 'Conceptos', href: '/admin/prod/conceptos' },
        { title: `${obra.no} - ${obra.descripcion}`, href: `/admin/prod/conceptos/obra/${obra.id}` },
        { title: 'Nuevo Concepto', href: `/admin/prod/conceptos/create?obra_id=${obra.id}` },
    ];

    const { data, setData, post, processing, errors } = useForm({
        obra_id: String(obra.id),
        marca: '',
        descripcion: '',
        peso_unitario: '',
        version: '1',
        activo: true,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/prod/conceptos');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Concepto" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo Concepto</h1>
                    <p className="mb-4 text-sm text-gray-500">Obra: {obra.no} - {obra.descripcion}</p>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Marca" htmlFor="marca" error={errors.marca} required>
                            <Input
                                id="marca"
                                value={data.marca}
                                onChange={(e) => setData('marca', e.target.value)}
                                placeholder="Identificador del concepto"
                            />
                        </FormField>

                        <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                placeholder="Descripcion del concepto"
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
                                    placeholder="0.000"
                                />
                            </FormField>

                            <FormField label="Version" htmlFor="version" error={errors.version}>
                                <Input
                                    id="version"
                                    type="number"
                                    min="1"
                                    value={data.version}
                                    onChange={(e) => setData('version', e.target.value)}
                                    placeholder="1"
                                />
                            </FormField>
                        </div>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href={`/admin/prod/conceptos/obra/${obra.id}`}>Cancelar</Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Guardar
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
