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
    { title: 'Produccion', href: '/admin/prod/destajos' },
    { title: 'Piezas', href: '/admin/prod/piezas' },
    { title: 'Nueva Pieza', href: '/admin/prod/piezas/create' },
];

type Props = {
    obras: Obra[];
};

export default function PiezasCreate({ obras }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        obra_id: '',
        marca: '',
        descripcion: '',
        longitud: '',
        peso: '',
        cantidad: '1',
        version: '1',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/prod/piezas');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva Pieza" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nueva Pieza</h1>

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

                        <FormField label="Marca" htmlFor="marca" error={errors.marca} required>
                            <Input
                                id="marca"
                                value={data.marca}
                                onChange={(e) => setData('marca', e.target.value)}
                                placeholder="Identificador de la pieza"
                            />
                        </FormField>

                        <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                placeholder="Descripcion de la pieza"
                            />
                        </FormField>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Longitud" htmlFor="longitud" error={errors.longitud}>
                                <Input
                                    id="longitud"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.longitud}
                                    onChange={(e) => setData('longitud', e.target.value)}
                                    placeholder="0.00"
                                />
                            </FormField>

                            <FormField label="Peso (kg)" htmlFor="peso" error={errors.peso} required>
                                <Input
                                    id="peso"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.peso}
                                    onChange={(e) => setData('peso', e.target.value)}
                                    placeholder="0.00"
                                />
                            </FormField>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Cantidad" htmlFor="cantidad" error={errors.cantidad} required>
                                <Input
                                    id="cantidad"
                                    type="number"
                                    min="1"
                                    value={data.cantidad}
                                    onChange={(e) => setData('cantidad', e.target.value)}
                                    placeholder="1"
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
                                <Link href="/admin/prod/piezas">Cancelar</Link>
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
