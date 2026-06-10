import { FormField } from '@/components/form';
import { Button, ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CotizCategoriaTarjeta, CotizFactor, CotizInsumo } from '@/types/models';
import { Head, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cotización', href: '/admin/cotiz/factores' },
    { title: 'Factores', href: '/admin/cotiz/factores' },
    { title: 'Editar', href: '/admin/cotiz/factores' },
];

type Props = {
    factor: CotizFactor;
    insumos: CotizInsumo[];
    categoriasTarjeta: CotizCategoriaTarjeta[];
};

export default function FactoresEdit({ factor, insumos, categoriasTarjeta }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        codigo: factor.codigo,
        nombre: factor.nombre,
        insumo_id: String(factor.insumo_id),
        formula: factor.formula ?? '',
        descripcion: factor.descripcion ?? '',
        categoria_tarjeta_id: factor.categoria_tarjeta_id ? String(factor.categoria_tarjeta_id) : '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/cotiz/factores/${factor.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar — ${factor.codigo}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Editar factor</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Código" htmlFor="codigo" error={errors.codigo} required>
                                <Input id="codigo" value={data.codigo} onChange={(e) => setData('codigo', e.target.value)} />
                            </FormField>
                            <FormField label="Nombre" htmlFor="nombre" error={errors.nombre} required>
                                <Input id="nombre" value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} />
                            </FormField>
                        </div>

                        <FormField label="Insumo" htmlFor="insumo_id" error={errors.insumo_id} required>
                            <Select id="insumo_id" value={data.insumo_id} onValueChange={(value) => setData('insumo_id', value)}>
                                <option value="">Seleccionar insumo</option>
                                {insumos.map((i) => (
                                    <option key={i.id} value={i.id}>{i.descripcion}</option>
                                ))}
                            </Select>
                        </FormField>

                        <FormField label="Fórmula" htmlFor="formula" error={errors.formula}>
                            <Input id="formula" value={data.formula} onChange={(e) => setData('formula', e.target.value)} />
                        </FormField>

                        <FormField label="Descripción" htmlFor="descripcion" error={errors.descripcion}>
                            <Input id="descripcion" value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} />
                        </FormField>

                        <FormField label="Categoría de tarjeta" htmlFor="categoria_tarjeta_id" error={errors.categoria_tarjeta_id}>
                            <Select id="categoria_tarjeta_id" value={data.categoria_tarjeta_id} onValueChange={(value) => setData('categoria_tarjeta_id', value)}>
                                <option value="">(sin clasificar)</option>
                                {categoriasTarjeta.map((c) => (
                                    <option key={c.id} value={c.id}>{c.descripcion}</option>
                                ))}
                            </Select>
                        </FormField>

                        <div className="flex items-center gap-2 pt-2">
                            <Button type="submit" variant="primary" disabled={processing}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Guardar cambios
                            </Button>
                            <ButtonLink variant="ghost" href="/admin/cotiz/factores">
                                Cancelar
                            </ButtonLink>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
