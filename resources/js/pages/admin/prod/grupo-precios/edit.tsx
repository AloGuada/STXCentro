import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { SubprocesosPorProceso, type SubprocesoForm } from '@/components/prod/subprocesos-por-proceso';
import { TarifasPorProceso } from '@/components/prod/tarifas-por-proceso';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SearchSelect } from '@/components/ui/search-select';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Obra, ProdGrupoPrecio, ProdProceso, ProdTipoPago } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import { type FormEvent, useMemo } from 'react';

type Props = {
    grupoPrecio: ProdGrupoPrecio;
    obras: Obra[];
    /** Procesos que paga la obra: una tarifa por cada uno. */
    procesos: ProdProceso[];
    /** Kilo o subproceso: el grupo elige una y son excluyentes. */
    tiposPago: { value: ProdTipoPago; label: string }[];
    /** Falso si el grupo ya tiene produccion liquidada. */
    puedeCambiarModalidad: boolean;
};

export default function GrupoPreciosEdit({
    grupoPrecio,
    obras,
    procesos,
    tiposPago,
    puedeCambiarModalidad,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/destajos' },
        { title: 'Grupo Precios', href: '/admin/prod/grupo-precios' },
        { title: 'Obra', href: `/admin/prod/grupo-precios/obra/${grupoPrecio.obra_id}` },
        { title: grupoPrecio.descripcion, href: `/admin/prod/grupo-precios/${grupoPrecio.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        obra_id: String(grupoPrecio.obra_id),
        descripcion: grupoPrecio.descripcion,
        tipo_pago: grupoPrecio.tipo_pago,
        precios: Object.fromEntries(
            (grupoPrecio.precios ?? []).map((p) => [p.proceso_id, String(p.precio_kilo)]),
        ) as Record<number, string>,
        subprocesos: (grupoPrecio.subprocesos ?? [])
            .slice()
            .sort((a, b) => a.orden - b.orden)
            .map((s): SubprocesoForm => ({
                id: s.id,
                proceso_id: String(s.proceso_id),
                nombre: s.nombre,
                precio: String(s.precio),
                activo: s.activo,
            })),
    });

    const obraOptions = useMemo(
        () => obras.map((obra) => ({ value: String(obra.id), label: `${obra.no} - ${obra.descripcion}` })),
        [obras],
    );

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/prod/grupo-precios/${grupoPrecio.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${grupoPrecio.descripcion}`} />

            <div className="p-6">
                <div className="w-full max-w-2xl">
                    <h1 className="mb-6 text-2xl font-semibold">Editar grupo de precios</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Obra" htmlFor="obra_id" error={errors.obra_id} required>
                            <SearchSelect
                                options={obraOptions}
                                value={data.obra_id}
                                onValueChange={(value) => setData('obra_id', value)}
                                placeholder="Buscar obra..."
                            />
                        </FormField>

                        <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                error={!!errors.descripcion}
                            />
                        </FormField>

                        <FormField label="Forma de pago" htmlFor="tipo_pago" error={errors.tipo_pago} required>
                            <Select
                                id="tipo_pago"
                                value={data.tipo_pago}
                                onValueChange={(valor) => setData('tipo_pago', valor as ProdTipoPago)}
                                error={!!errors.tipo_pago}
                                disabled={!puedeCambiarModalidad}
                            >
                                {tiposPago.map((tipo) => (
                                    <SelectItem key={tipo.value} value={tipo.value}>
                                        {tipo.label}
                                    </SelectItem>
                                ))}
                            </Select>
                            {!puedeCambiarModalidad && (
                                <p className="text-base-content/60 mt-1 text-sm">
                                    Este grupo ya tiene producción liquidada. Para cambiar su forma de pago, crea un
                                    grupo nuevo y reasigna las marcas.
                                </p>
                            )}
                        </FormField>

                        {data.tipo_pago === 'subproceso' ? (
                            <SubprocesosPorProceso
                                procesos={procesos}
                                valores={data.subprocesos}
                                onChange={(valores) => setData('subprocesos', valores)}
                                errors={errors as Record<string, string>}
                            />
                        ) : (
                            <TarifasPorProceso
                                procesos={procesos}
                                valores={data.precios}
                                onChange={(procesoId, valor) =>
                                    setData('precios', { ...data.precios, [procesoId]: valor })
                                }
                                errors={errors as Record<string, string>}
                            />
                        )}

                        <div className="flex items-center justify-between">
                            <DeleteDialog
                                title="Eliminar grupo de precios"
                                description={`¿Eliminar el grupo "${grupoPrecio.descripcion}"? Esta acción no se puede deshacer.`}
                                deleteUrl={`/admin/prod/grupo-precios/${grupoPrecio.id}`}
                            />
                            <div className="flex gap-2">
                                <Button variant="outline" asChild>
                                    <Link href={`/admin/prod/grupo-precios/obra/${grupoPrecio.obra_id}`}>Cancelar</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Loader2Icon className="size-4 animate-spin" />}
                                    Guardar
                                </Button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
