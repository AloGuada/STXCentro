import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Obra } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/cortes' },
    { title: 'Grupo Precios', href: '/admin/prod/grupo-precios' },
    { title: 'Nuevo', href: '/admin/prod/grupo-precios/create' },
];

type Props = {
    obras: Obra[];
    obraId?: string;
};

export default function GrupoPreciosCreate({ obras, obraId }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        obra_id: obraId ?? '',
        descripcion: '',
        precio_kilo: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/prod/grupo-precios');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Grupo de Precios" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo Grupo de Precios</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Obra" htmlFor="obra_id" error={errors.obra_id} required>
                            <Select
                                id="obra_id"
                                value={data.obra_id}
                                onValueChange={(value) => setData('obra_id', value)}
                                placeholder="Seleccionar obra"
                            >
                                {obras.map((obra) => (
                                    <option key={obra.id} value={obra.id}>
                                        {obra.no} - {obra.descripcion}
                                    </option>
                                ))}
                            </Select>
                        </FormField>

                        <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                placeholder="Nombre del grupo de precios"
                            />
                        </FormField>

                        <FormField label="Precio por Kilo" htmlFor="precio_kilo" error={errors.precio_kilo} required>
                            <Input
                                id="precio_kilo"
                                type="number"
                                step="0.0001"
                                min="0"
                                value={data.precio_kilo}
                                onChange={(e) => setData('precio_kilo', e.target.value)}
                                placeholder="0.0000"
                            />
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href={data.obra_id ? `/admin/prod/grupo-precios/obra/${data.obra_id}` : '/admin/prod/grupo-precios'}>Cancelar</Link>
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
