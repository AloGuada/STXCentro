import { FormField } from '@/components/form';
import { TarifasPorProceso } from '@/components/prod/tarifas-por-proceso';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SearchSelect } from '@/components/ui/search-select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Obra, ProdProceso } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import { type FormEvent, useMemo } from 'react';

type Props = {
    obras: Obra[];
    /** Cuando se llega desde una obra, se da por dada y no se vuelve a elegir. */
    obra?: Obra | null;
    /** Procesos que paga la obra: una tarifa por cada uno. */
    procesos: ProdProceso[];
};

export default function GrupoPreciosCreate({ obras, obra, procesos }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/destajos' },
        { title: 'Grupo Precios', href: '/admin/prod/grupo-precios' },
        ...(obra
            ? [{ title: `${obra.no} - ${obra.descripcion}`, href: `/admin/prod/grupo-precios/obra/${obra.id}` }]
            : []),
        { title: 'Nuevo', href: '/admin/prod/grupo-precios/create' },
    ];

    const { data, setData, post, processing, errors } = useForm<{
        obra_id: string;
        descripcion: string;
        precios: Record<number, string>;
    }>({
        obra_id: obra ? String(obra.id) : '',
        descripcion: '',
        precios: {},
    });

    const obraOptions = useMemo(
        () => obras.map((obra) => ({ value: String(obra.id), label: `${obra.no} - ${obra.descripcion}` })),
        [obras],
    );

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/prod/grupo-precios');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Grupo de Precios" />

            <div className="p-6">
                <div className="w-full max-w-2xl">
                    <h1 className="text-2xl font-semibold">Nuevo grupo de precios</h1>
                    {obra ? (
                        <p className="text-base-content/60 mb-6 mt-1 text-sm">
                            Obra {obra.no} — {obra.descripcion}
                        </p>
                    ) : (
                        <div className="mb-6" />
                    )}

                    <form onSubmit={handleSubmit} className="space-y-4">
                        {!obra && (
                            <FormField label="Obra" htmlFor="obra_id" error={errors.obra_id} required>
                                <SearchSelect
                                    options={obraOptions}
                                    value={data.obra_id}
                                    onValueChange={(value) => setData('obra_id', value)}
                                    placeholder="Buscar obra..."
                                />
                            </FormField>
                        )}

                        {obra && errors.obra_id && (
                            <div className="alert alert-error text-sm">{errors.obra_id}</div>
                        )}

                        <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                error={!!errors.descripcion}
                                placeholder="Nombre del grupo de precios"
                            />
                        </FormField>

                        <TarifasPorProceso
                            procesos={procesos}
                            valores={data.precios}
                            onChange={(procesoId, valor) =>
                                setData('precios', { ...data.precios, [procesoId]: valor })
                            }
                            errors={errors as Record<string, string>}
                        />

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href={data.obra_id ? `/admin/prod/grupo-precios/obra/${data.obra_id}` : '/admin/prod/grupo-precios'}>Cancelar</Link>
                            </Button>
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
