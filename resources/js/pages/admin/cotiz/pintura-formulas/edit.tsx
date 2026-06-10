import { FormField } from '@/components/form';
import { Button, ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CotizPinturaFormula } from '@/types/models';
import { Head, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cotización', href: '/admin/cotiz/pintura-formulas' },
    { title: 'Fórmulas de pintura', href: '/admin/cotiz/pintura-formulas' },
    { title: 'Editar', href: '/admin/cotiz/pintura-formulas' },
];

type Props = {
    pinturaFormula: CotizPinturaFormula;
};

export default function PinturaFormulasEdit({ pinturaFormula }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        clave: pinturaFormula.clave,
        nombre: pinturaFormula.nombre,
        formula: pinturaFormula.formula,
        orden: String(pinturaFormula.orden),
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/cotiz/pintura-formulas/${pinturaFormula.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar — ${pinturaFormula.clave}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Editar fórmula de pintura</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Clave" htmlFor="clave" error={errors.clave} required>
                                <Input id="clave" value={data.clave} onChange={(e) => setData('clave', e.target.value)} />
                            </FormField>
                            <FormField label="Nombre" htmlFor="nombre" error={errors.nombre} required>
                                <Input id="nombre" value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} />
                            </FormField>
                        </div>

                        <FormField label="Fórmula" htmlFor="formula" error={errors.formula} required>
                            <Input id="formula" value={data.formula} onChange={(e) => setData('formula', e.target.value)} />
                        </FormField>

                        <FormField label="Orden" htmlFor="orden" error={errors.orden}>
                            <Input id="orden" type="number" min="0" value={data.orden} onChange={(e) => setData('orden', e.target.value)} />
                        </FormField>

                        <div className="flex items-center gap-2 pt-2">
                            <Button type="submit" variant="primary" disabled={processing}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Guardar cambios
                            </Button>
                            <ButtonLink variant="ghost" href="/admin/cotiz/pintura-formulas">
                                Cancelar
                            </ButtonLink>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
