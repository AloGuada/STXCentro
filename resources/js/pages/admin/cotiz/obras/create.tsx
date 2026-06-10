import { FormField } from '@/components/form';
import { Button, ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cotización', href: '/admin/cotiz/obras' },
    { title: 'Obras', href: '/admin/cotiz/obras' },
    { title: 'Nueva', href: '/admin/cotiz/obras/create' },
];

export default function ObrasCreate() {
    const { data, setData, post, processing, errors } = useForm({
        nombre: '',
        op: '',
        factor_contratista: '1.15',
        num_grupos: '1',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/cotiz/obras');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva obra" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nueva obra</h1>

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
                                placeholder="Ej: Nave industrial Querétaro"
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
                                Guardar
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
