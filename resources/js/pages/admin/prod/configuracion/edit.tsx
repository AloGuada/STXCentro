import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/destajos' },
    { title: 'Configuracion', href: '/admin/prod/configuracion' },
];

type Props = { configuracion: { salario_minimo_diario: number | string } };

export default function ConfiguracionEdit({ configuracion }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        salario_minimo_diario: String(configuracion.salario_minimo_diario ?? 0),
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put('/admin/prod/configuracion');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Configuracion de Produccion" />

            <div className="p-6">
                <div className="w-full max-w-2xl">
                    <h1 className="text-2xl font-semibold">Configuración de Producción</h1>
                    <p className="text-base-content/60 mb-6 mt-1 text-sm">
                        Valores que rigen el cálculo del destajo.
                    </p>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField
                            label="Salario mínimo diario"
                            htmlFor="salario_minimo_diario"
                            error={errors.salario_minimo_diario}
                            description="Cada día asistido se le paga al trabajador aunque el destajo del grupo no alcance. Las semanas ya cerradas conservan el valor que regía al cerrarlas."
                            required
                        >
                            <Input
                                id="salario_minimo_diario"
                                type="number"
                                step="0.01"
                                min="0"
                                value={data.salario_minimo_diario}
                                onChange={(e) => setData('salario_minimo_diario', e.target.value)}
                                error={!!errors.salario_minimo_diario}
                            />
                        </FormField>

                        <div className="flex justify-end">
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
