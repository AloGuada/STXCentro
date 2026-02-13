import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { ITEM_ESTADO_LABELS, type StiItemEstado, type StiItemTipo } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STI', href: '/admin/sti/equipos' },
    { title: 'Inventario', href: '/admin/sti/items' },
    { title: 'Nuevo Item', href: '/admin/sti/items/create' },
];

type Props = {
    tipos: StiItemTipo[];
};

export default function ItemsCreate({ tipos }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        descripcion: '',
        tipo_id: '',
        costo: 0,
        no_serie: '',
        estado: 'disponible' as StiItemEstado,
        principal: false,
        accesorio: false,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/sti/items');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Item" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo Item</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                placeholder="Descripcion del item"
                            />
                        </FormField>

                        <FormField label="Tipo" htmlFor="tipo_id" error={errors.tipo_id} required>
                            <Select
                                id="tipo_id"
                                value={data.tipo_id}
                                onValueChange={(value) => setData('tipo_id', value)}
                                placeholder="Seleccionar tipo"
                            >
                                {tipos.map((tipo) => (
                                    <option key={tipo.id} value={tipo.id}>
                                        {tipo.descripcion}
                                    </option>
                                ))}
                            </Select>
                        </FormField>

                        <FormField label="Costo" htmlFor="costo" error={errors.costo} required>
                            <Input
                                id="costo"
                                type="number"
                                min={0}
                                step="0.01"
                                value={data.costo}
                                onChange={(e) => setData('costo', parseFloat(e.target.value) || 0)}
                                placeholder="0.00"
                            />
                        </FormField>

                        <FormField label="No. Serie" htmlFor="no_serie" error={errors.no_serie}>
                            <Input
                                id="no_serie"
                                value={data.no_serie}
                                onChange={(e) => setData('no_serie', e.target.value)}
                                placeholder="Numero de serie"
                            />
                        </FormField>

                        <FormField label="Estado" htmlFor="estado" error={errors.estado} required>
                            <Select
                                id="estado"
                                value={data.estado}
                                onValueChange={(value) => setData('estado', value as StiItemEstado)}
                            >
                                {(Object.entries(ITEM_ESTADO_LABELS) as [StiItemEstado, string][]).map(([value, label]) => (
                                    <option key={value} value={value}>
                                        {label}
                                    </option>
                                ))}
                            </Select>
                        </FormField>

                        <div className="flex gap-6">
                            <label className="flex cursor-pointer items-center gap-2">
                                <input
                                    type="checkbox"
                                    className="checkbox"
                                    checked={data.principal}
                                    onChange={(e) => setData('principal', e.target.checked)}
                                />
                                <span className="label-text">Principal</span>
                            </label>
                            <label className="flex cursor-pointer items-center gap-2">
                                <input
                                    type="checkbox"
                                    className="checkbox"
                                    checked={data.accesorio}
                                    onChange={(e) => setData('accesorio', e.target.checked)}
                                />
                                <span className="label-text">Accesorio</span>
                            </label>
                        </div>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/sti/items">Cancelar</Link>
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
