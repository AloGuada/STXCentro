import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Concepto, Obra, ProdGrupoTrabajo } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/cortes' },
    { title: 'Registros', href: '/admin/prod/registros' },
    { title: 'Nuevo Registro', href: '/admin/prod/registros/create' },
];

type Props = {
    conceptos: (Concepto & { obra: Obra })[];
    gruposTrabajo: ProdGrupoTrabajo[];
};

export default function RegistrosCreate({ conceptos, gruposTrabajo }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        fecha: new Date().toISOString().split('T')[0],
        concepto_id: '',
        grupo_trabajo_id: '',
        cantidad: '1',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/prod/registros');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Registro" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo Registro de Produccion</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Fecha" htmlFor="fecha" error={errors.fecha} required>
                            <Input
                                id="fecha"
                                type="date"
                                value={data.fecha}
                                onChange={(e) => setData('fecha', e.target.value)}
                            />
                        </FormField>

                        <FormField label="Concepto" htmlFor="concepto_id" error={errors.concepto_id} required>
                            <Select
                                id="concepto_id"
                                value={data.concepto_id}
                                onValueChange={(value) => setData('concepto_id', value)}
                                placeholder="Seleccionar concepto"
                            >
                                {conceptos.map((c) => {
                                    const producidas = c.registros_sum_cantidad ?? 0;
                                    const faltantes = c.cantidad - producidas;
                                    return (
                                        <option key={c.id} value={c.id}>
                                            [{c.obra?.no} - {c.obra?.descripcion}] {c.marca} - {c.descripcion} (Faltan: {faltantes} de {c.cantidad})
                                        </option>
                                    );
                                })}
                            </Select>
                        </FormField>

                        <FormField label="Grupo de Trabajo" htmlFor="grupo_trabajo_id" error={errors.grupo_trabajo_id} required>
                            <Select
                                id="grupo_trabajo_id"
                                value={data.grupo_trabajo_id}
                                onValueChange={(value) => setData('grupo_trabajo_id', value)}
                                placeholder="Seleccionar grupo"
                            >
                                {gruposTrabajo.map((g) => (
                                    <option key={g.id} value={g.id}>{g.descripcion}</option>
                                ))}
                            </Select>
                        </FormField>

                        <FormField label="Cantidad" htmlFor="cantidad" error={errors.cantidad} required>
                            <Input
                                id="cantidad"
                                type="number"
                                min="1"
                                value={data.cantidad}
                                onChange={(e) => setData('cantidad', e.target.value)}
                                placeholder="1"
                            />
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/prod/registros">Cancelar</Link>
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
