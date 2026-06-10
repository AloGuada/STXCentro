import { FormField } from '@/components/form';
import { Button, ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CotizKilosRealesCategoria } from '@/types/models';
import { Head, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cotización', href: '/admin/cotiz/kilos-reales-categorias' },
    { title: 'Categorías de kilos reales', href: '/admin/cotiz/kilos-reales-categorias' },
    { title: 'Editar', href: '/admin/cotiz/kilos-reales-categorias' },
];

type Props = {
    kilosRealesCategoria: CotizKilosRealesCategoria;
    tiposCorte: Record<string, string>;
};

export default function KilosRealesCategoriasEdit({ kilosRealesCategoria, tiposCorte }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        descripcion: kilosRealesCategoria.descripcion,
        tipo_corte: kilosRealesCategoria.tipo_corte as string,
        orden: String(kilosRealesCategoria.orden),
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/cotiz/kilos-reales-categorias/${kilosRealesCategoria.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar — ${kilosRealesCategoria.descripcion}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Editar categoría de kilos reales</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Descripción" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input id="descripcion" value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} />
                        </FormField>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Tipo de corte" htmlFor="tipo_corte" error={errors.tipo_corte} required>
                                <Select id="tipo_corte" value={data.tipo_corte} onValueChange={(value) => setData('tipo_corte', value)}>
                                    <option value="">Seleccionar tipo</option>
                                    {Object.entries(tiposCorte).map(([value, label]) => (
                                        <option key={value} value={value}>{label}</option>
                                    ))}
                                </Select>
                            </FormField>
                            <FormField label="Orden" htmlFor="orden" error={errors.orden}>
                                <Input id="orden" type="number" min="0" value={data.orden} onChange={(e) => setData('orden', e.target.value)} />
                            </FormField>
                        </div>

                        <div className="flex items-center gap-2 pt-2">
                            <Button type="submit" variant="primary" disabled={processing}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Guardar cambios
                            </Button>
                            <ButtonLink variant="ghost" href="/admin/cotiz/kilos-reales-categorias">
                                Cancelar
                            </ButtonLink>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
