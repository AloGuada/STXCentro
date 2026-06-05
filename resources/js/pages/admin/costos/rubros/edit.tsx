import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosRubro, CostosTipoRubro, Departamento } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = {
    rubro: CostosRubro;
    tipoRubros: CostosTipoRubro[];
    departamentos: Departamento[];
};

export default function RubrosEdit({ rubro, tipoRubros, departamentos }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/rubros' },
        { title: 'Centros de Costos', href: '/admin/costos/rubros' },
        { title: rubro.codigo, href: `/admin/costos/rubros/${rubro.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        codigo: rubro.codigo,
        descripcion: rubro.descripcion,
        tipo_rubro_id: String(rubro.tipo_rubro_id),
        departamento_id: rubro.departamento_id ? String(rubro.departamento_id) : '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/costos/rubros/${rubro.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${rubro.codigo}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <div className="mb-6 flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold">Editar Centro de Costos</h1>
                            <span className={`badge ${rubro.ambito === 'planta' ? 'badge-info' : 'badge-ghost'}`}>
                                {rubro.ambito === 'planta' ? 'Planta' : 'Obras'}
                            </span>
                        </div>
                        <DeleteDialog
                            title="Eliminar centro de costos"
                            description={`¿Estás seguro de eliminar el centro de costos "${rubro.codigo}"? Esta acción no se puede deshacer.`}
                            deleteUrl={`/admin/costos/rubros/${rubro.id}`}
                        />
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Código" htmlFor="codigo" error={errors.codigo} required>
                                <Input id="codigo" value={data.codigo} onChange={(e) => setData('codigo', e.target.value)} />
                            </FormField>
                            <FormField label="Tipo de Centro de Costos" htmlFor="tipo_rubro_id" error={errors.tipo_rubro_id} required>
                                <Select id="tipo_rubro_id" value={data.tipo_rubro_id} onValueChange={(value) => setData('tipo_rubro_id', value)}>
                                    <option value="">Seleccionar tipo</option>
                                    {tipoRubros.map((tr) => (
                                        <option key={tr.id} value={tr.id}>{tr.descripcion}</option>
                                    ))}
                                </Select>
                            </FormField>
                        </div>

                        <FormField label="Descripción" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input id="descripcion" value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} />
                        </FormField>

                        <FormField label="Departamento" htmlFor="departamento_id" error={errors.departamento_id}>
                            <Select id="departamento_id" value={data.departamento_id} onValueChange={(value) => setData('departamento_id', value)}>
                                <option value="">Sin departamento</option>
                                {departamentos.map((d) => (
                                    <option key={d.id} value={d.id}>{d.descripcion}</option>
                                ))}
                            </Select>
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/costos/rubros">Cancelar</Link>
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
