import { FormField } from '@/components/form';
import { Button, ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CotizFleteViaticoCatalogo } from '@/types/models';
import { Head, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cotización', href: '/admin/cotiz/fletes-viaticos' },
    { title: 'Fletes y viáticos', href: '/admin/cotiz/fletes-viaticos' },
    { title: 'Editar', href: '/admin/cotiz/fletes-viaticos' },
];

type Props = {
    fleteViatico: CotizFleteViaticoCatalogo;
    grupos: Record<string, string>;
};

export default function FletesViaticosEdit({ fleteViatico, grupos }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        grupo: fleteViatico.grupo as string,
        orden: String(fleteViatico.orden),
        concepto: fleteViatico.concepto,
        unidad: fleteViatico.unidad ?? '',
        p_unit_default: fleteViatico.p_unit_default,
        notas: fleteViatico.notas ?? '',
        clave: fleteViatico.clave ?? '',
        formula_cantidad: fleteViatico.formula_cantidad ?? '',
        formula_p_unit: fleteViatico.formula_p_unit ?? '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/cotiz/fletes-viaticos/${fleteViatico.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar — ${fleteViatico.concepto}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Editar flete o viático</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Grupo" htmlFor="grupo" error={errors.grupo} required>
                                <Select id="grupo" value={data.grupo} onValueChange={(value) => setData('grupo', value)}>
                                    <option value="">Seleccionar grupo</option>
                                    {Object.entries(grupos).map(([value, label]) => (
                                        <option key={value} value={value}>{label}</option>
                                    ))}
                                </Select>
                            </FormField>
                            <FormField label="Orden" htmlFor="orden" error={errors.orden}>
                                <Input id="orden" type="number" min="0" value={data.orden} onChange={(e) => setData('orden', e.target.value)} />
                            </FormField>
                        </div>

                        <FormField label="Concepto" htmlFor="concepto" error={errors.concepto} required>
                            <Input id="concepto" value={data.concepto} onChange={(e) => setData('concepto', e.target.value)} />
                        </FormField>

                        <div className="grid grid-cols-3 gap-4">
                            <FormField label="Clave" htmlFor="clave" error={errors.clave}>
                                <Input id="clave" value={data.clave} onChange={(e) => setData('clave', e.target.value)} />
                            </FormField>
                            <FormField label="Unidad" htmlFor="unidad" error={errors.unidad}>
                                <Input id="unidad" value={data.unidad} onChange={(e) => setData('unidad', e.target.value)} />
                            </FormField>
                            <FormField label="P. Unit. default" htmlFor="p_unit_default" error={errors.p_unit_default} required>
                                <Input id="p_unit_default" type="number" step="0.0001" min="0" value={data.p_unit_default} onChange={(e) => setData('p_unit_default', e.target.value)} />
                            </FormField>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Fórmula cantidad" htmlFor="formula_cantidad" error={errors.formula_cantidad}>
                                <Input id="formula_cantidad" value={data.formula_cantidad} onChange={(e) => setData('formula_cantidad', e.target.value)} />
                            </FormField>
                            <FormField label="Fórmula P. Unit." htmlFor="formula_p_unit" error={errors.formula_p_unit}>
                                <Input id="formula_p_unit" value={data.formula_p_unit} onChange={(e) => setData('formula_p_unit', e.target.value)} />
                            </FormField>
                        </div>

                        <FormField label="Notas" htmlFor="notas" error={errors.notas}>
                            <Input id="notas" value={data.notas} onChange={(e) => setData('notas', e.target.value)} />
                        </FormField>

                        <div className="flex items-center gap-2 pt-2">
                            <Button type="submit" variant="primary" disabled={processing}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Guardar cambios
                            </Button>
                            <ButtonLink variant="ghost" href="/admin/cotiz/fletes-viaticos">
                                Cancelar
                            </ButtonLink>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
