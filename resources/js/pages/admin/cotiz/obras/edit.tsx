import { FormField } from '@/components/form';
import { Button, ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CotizObra } from '@/types/models';
import { Head, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cotización', href: '/admin/cotiz/obras' },
    { title: 'Obras', href: '/admin/cotiz/obras' },
    { title: 'Editar', href: '/admin/cotiz/obras' },
];

type Props = {
    obra: CotizObra;
};

export default function ObrasEdit({ obra }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        nombre: obra.nombre,
        op: obra.op ?? '',
        factor_contratista: obra.factor_contratista,
        num_grupos: String(obra.num_grupos),
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/cotiz/obras/${obra.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar — ${obra.nombre}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Editar obra</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField
                            label="Nombre"
                            htmlFor="nombre"
                            error={errors.nombre}
                            required
                        >
                            <Input
                                id="nombre"
                                value={data.nombre}
                                onChange={(e) =>
                                    setData('nombre', e.target.value)
                                }
                            />
                        </FormField>

                        <div className="grid grid-cols-3 gap-4">
                            <FormField
                                label="OP"
                                htmlFor="op"
                                error={errors.op}
                            >
                                <Input
                                    id="op"
                                    value={data.op}
                                    onChange={(e) =>
                                        setData('op', e.target.value)
                                    }
                                />
                            </FormField>
                            <FormField
                                label="Factor contratista"
                                htmlFor="factor_contratista"
                                error={errors.factor_contratista}
                                required
                            >
                                <Input
                                    id="factor_contratista"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.factor_contratista}
                                    onChange={(e) =>
                                        setData(
                                            'factor_contratista',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                label="Número de grupos"
                                htmlFor="num_grupos"
                                error={errors.num_grupos}
                                required
                            >
                                <Input
                                    id="num_grupos"
                                    type="number"
                                    min="1"
                                    value={data.num_grupos}
                                    onChange={(e) =>
                                        setData('num_grupos', e.target.value)
                                    }
                                />
                            </FormField>
                        </div>

                        <div className="flex items-center gap-2 pt-2">
                            <Button
                                type="submit"
                                variant="primary"
                                disabled={processing}
                            >
                                {processing && (
                                    <Loader2Icon className="size-4 animate-spin" />
                                )}
                                Guardar cambios
                            </Button>
                            <ButtonLink
                                variant="ghost"
                                href="/admin/cotiz/obras"
                            >
                                Cancelar
                            </ButtonLink>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
