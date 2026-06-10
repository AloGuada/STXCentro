import { FormField } from '@/components/form';
import { Button, ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CotizCentroCosto, CotizInsumo } from '@/types/models';
import { Head, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cotización', href: '/admin/cotiz/cuadrillas' },
    { title: 'Cuadrillas', href: '/admin/cotiz/cuadrillas' },
    { title: 'Nueva', href: '/admin/cotiz/cuadrillas/create' },
];

type Props = {
    centrosCosto: CotizCentroCosto[];
    insumos: CotizInsumo[];
};

export default function CuadrillasCreate({ centrosCosto, insumos }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        codigo: '',
        nombre: '',
        centro_costo_id: '',
        insumo_id: '',
        rendimiento: '',
        formula_costo: '',
        descripcion: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/cotiz/cuadrillas');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva cuadrilla" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nueva cuadrilla</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Código" htmlFor="codigo" error={errors.codigo} required>
                                <Input id="codigo" value={data.codigo} onChange={(e) => setData('codigo', e.target.value)} />
                            </FormField>
                            <FormField label="Nombre" htmlFor="nombre" error={errors.nombre} required>
                                <Input id="nombre" value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} />
                            </FormField>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Centro de costo" htmlFor="centro_costo_id" error={errors.centro_costo_id} required>
                                <Select id="centro_costo_id" value={data.centro_costo_id} onValueChange={(value) => setData('centro_costo_id', value)}>
                                    <option value="">Seleccionar centro de costo</option>
                                    {centrosCosto.map((c) => (
                                        <option key={c.id} value={c.id}>{c.cod_coste} — {c.concepto}</option>
                                    ))}
                                </Select>
                            </FormField>
                            <FormField label="Insumo" htmlFor="insumo_id" error={errors.insumo_id}>
                                <Select id="insumo_id" value={data.insumo_id} onValueChange={(value) => setData('insumo_id', value)}>
                                    <option value="">(sin insumo)</option>
                                    {insumos.map((i) => (
                                        <option key={i.id} value={i.id}>{i.descripcion}</option>
                                    ))}
                                </Select>
                            </FormField>
                        </div>

                        <FormField label="Rendimiento" htmlFor="rendimiento" error={errors.rendimiento}>
                            <Input id="rendimiento" type="number" step="0.000001" min="0" value={data.rendimiento} onChange={(e) => setData('rendimiento', e.target.value)} />
                        </FormField>

                        <FormField label="Fórmula de costo" htmlFor="formula_costo" error={errors.formula_costo}>
                            <Input id="formula_costo" value={data.formula_costo} onChange={(e) => setData('formula_costo', e.target.value)} />
                        </FormField>

                        <FormField label="Descripción" htmlFor="descripcion" error={errors.descripcion}>
                            <Input id="descripcion" value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} />
                        </FormField>

                        <div className="flex items-center gap-2 pt-2">
                            <Button type="submit" variant="primary" disabled={processing}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Guardar
                            </Button>
                            <ButtonLink variant="ghost" href="/admin/cotiz/cuadrillas">
                                Cancelar
                            </ButtonLink>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
