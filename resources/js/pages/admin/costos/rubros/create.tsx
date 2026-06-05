import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosTipoRubro, Departamento } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/rubros' },
    { title: 'Centros de Costos', href: '/admin/costos/rubros' },
    { title: 'Nuevo', href: '/admin/costos/rubros/create' },
];

type Props = {
    tipoRubros: CostosTipoRubro[];
    departamentos: Departamento[];
};

export default function RubrosCreate({ tipoRubros, departamentos }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        codigo: '',
        descripcion: '',
        ambito: 'obra',
        tipo_rubro_id: '',
        departamento_id: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/costos/rubros');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Centro de Costos" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo Centro de Costos</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Código" htmlFor="codigo" error={errors.codigo} required>
                                <Input id="codigo" value={data.codigo} onChange={(e) => setData('codigo', e.target.value)} placeholder="Ej: RB001" />
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

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Ámbito" htmlFor="ambito" error={errors.ambito} required>
                                <Select id="ambito" value={data.ambito} onValueChange={(value) => setData('ambito', value)}>
                                    <option value="obra">Obras</option>
                                    <option value="planta">Planta</option>
                                </Select>
                                <span className="mt-1 text-xs text-base-content/60">
                                    Define si presupuesta en las obras o en el proyecto de planta. No se puede cambiar después.
                                </span>
                            </FormField>
                            <FormField label="Departamento" htmlFor="departamento_id" error={errors.departamento_id}>
                                <Select id="departamento_id" value={data.departamento_id} onValueChange={(value) => setData('departamento_id', value)}>
                                    <option value="">Sin departamento</option>
                                    {departamentos.map((d) => (
                                        <option key={d.id} value={d.id}>{d.descripcion}</option>
                                    ))}
                                </Select>
                            </FormField>
                        </div>

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
