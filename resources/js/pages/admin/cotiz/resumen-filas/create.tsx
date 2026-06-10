import { FormField } from '@/components/form';
import { Button, ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cotización', href: '/admin/cotiz/resumen-filas' },
    { title: 'Filas de resumen', href: '/admin/cotiz/resumen-filas' },
    { title: 'Nueva', href: '/admin/cotiz/resumen-filas/create' },
];

type Props = {
    bloques: Record<string, string>;
    tiposFormula: Record<string, string>;
};

export default function ResumenFilasCreate({ bloques, tiposFormula }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        descripcion: '',
        bloque: '',
        tipo_formula: '',
        coef_default: '',
        referencia_extra: '',
        orden: '0',
        bloqueada: false,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/cotiz/resumen-filas');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva fila de resumen" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nueva fila de resumen</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Descripción" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input id="descripcion" value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} />
                        </FormField>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Bloque" htmlFor="bloque" error={errors.bloque} required>
                                <Select id="bloque" value={data.bloque} onValueChange={(value) => setData('bloque', value)}>
                                    <option value="">Seleccionar bloque</option>
                                    {Object.entries(bloques).map(([value, label]) => (
                                        <option key={value} value={value}>{label}</option>
                                    ))}
                                </Select>
                            </FormField>
                            <FormField label="Tipo de fórmula" htmlFor="tipo_formula" error={errors.tipo_formula} required>
                                <Select id="tipo_formula" value={data.tipo_formula} onValueChange={(value) => setData('tipo_formula', value)}>
                                    <option value="">Seleccionar tipo</option>
                                    {Object.entries(tiposFormula).map(([value, label]) => (
                                        <option key={value} value={value}>{label}</option>
                                    ))}
                                </Select>
                            </FormField>
                        </div>

                        <div className="grid grid-cols-3 gap-4">
                            <FormField label="Coef. default" htmlFor="coef_default" error={errors.coef_default}>
                                <Input id="coef_default" type="number" step="0.000001" value={data.coef_default} onChange={(e) => setData('coef_default', e.target.value)} />
                            </FormField>
                            <FormField label="Referencia extra" htmlFor="referencia_extra" error={errors.referencia_extra}>
                                <Input id="referencia_extra" value={data.referencia_extra} onChange={(e) => setData('referencia_extra', e.target.value)} />
                            </FormField>
                            <FormField label="Orden" htmlFor="orden" error={errors.orden}>
                                <Input id="orden" type="number" min="0" value={data.orden} onChange={(e) => setData('orden', e.target.value)} />
                            </FormField>
                        </div>

                        <FormField label="Bloqueada" htmlFor="bloqueada" error={errors.bloqueada}>
                            <label className="flex items-center gap-2">
                                <input
                                    id="bloqueada"
                                    type="checkbox"
                                    className="checkbox"
                                    checked={data.bloqueada}
                                    onChange={(e) => setData('bloqueada', e.target.checked)}
                                />
                                <span className="text-sm">Fila bloqueada (no editable en cotización)</span>
                            </label>
                        </FormField>

                        <div className="flex items-center gap-2 pt-2">
                            <Button type="submit" variant="primary" disabled={processing}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Guardar
                            </Button>
                            <ButtonLink variant="ghost" href="/admin/cotiz/resumen-filas">
                                Cancelar
                            </ButtonLink>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
