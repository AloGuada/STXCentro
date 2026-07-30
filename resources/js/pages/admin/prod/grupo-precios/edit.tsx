import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SearchSelect } from '@/components/ui/search-select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Obra, ProdGrupoPrecio } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import { type FormEvent, useMemo } from 'react';

type Props = {
    grupoPrecio: ProdGrupoPrecio;
    obras: Obra[];
};

export default function GrupoPreciosEdit({ grupoPrecio, obras }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/destajos' },
        { title: 'Grupo Precios', href: '/admin/prod/grupo-precios' },
        { title: 'Obra', href: `/admin/prod/grupo-precios/obra/${grupoPrecio.obra_id}` },
        { title: grupoPrecio.descripcion, href: `/admin/prod/grupo-precios/${grupoPrecio.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        obra_id: String(grupoPrecio.obra_id),
        descripcion: grupoPrecio.descripcion,
        precio_kilo: String(grupoPrecio.precio_kilo),
    });

    const obraOptions = useMemo(
        () => obras.map((obra) => ({ value: String(obra.id), label: `${obra.no} - ${obra.descripcion}` })),
        [obras],
    );

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/prod/grupo-precios/${grupoPrecio.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${grupoPrecio.descripcion}`} />

            <div className="p-6">
                <div className="w-full max-w-2xl">
                    <h1 className="mb-6 text-2xl font-semibold">Editar grupo de precios</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Obra" htmlFor="obra_id" error={errors.obra_id} required>
                            <SearchSelect
                                options={obraOptions}
                                value={data.obra_id}
                                onValueChange={(value) => setData('obra_id', value)}
                                placeholder="Buscar obra..."
                            />
                        </FormField>

                        <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                error={!!errors.descripcion}
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
                                error={!!errors.precio_kilo}
                            />
                        </FormField>

                        <div className="flex items-center justify-between">
                            <DeleteDialog
                                title="Eliminar grupo de precios"
                                description={`¿Eliminar el grupo "${grupoPrecio.descripcion}"? Esta acción no se puede deshacer.`}
                                deleteUrl={`/admin/prod/grupo-precios/${grupoPrecio.id}`}
                            />
                            <div className="flex gap-2">
                                <Button variant="outline" asChild>
                                    <Link href={`/admin/prod/grupo-precios/obra/${grupoPrecio.obra_id}`}>Cancelar</Link>
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
