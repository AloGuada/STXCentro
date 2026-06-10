import { FormField } from '@/components/form';
import { Button, ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CotizCentroCosto, CotizFaseMontaje } from '@/types/models';
import { Head, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cotización', href: '/admin/cotiz/fases-montaje' },
    { title: 'Fases de montaje', href: '/admin/cotiz/fases-montaje' },
    { title: 'Editar', href: '/admin/cotiz/fases-montaje' },
];

type Props = {
    faseMontaje: CotizFaseMontaje;
    centrosCosto: CotizCentroCosto[];
};

export default function FasesMontajeEdit({ faseMontaje, centrosCosto }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        codigo: faseMontaje.codigo,
        nombre: faseMontaje.nombre,
        unidad: faseMontaje.unidad,
        centro_costo_id: faseMontaje.centro_costo_id ? String(faseMontaje.centro_costo_id) : '',
        orden: String(faseMontaje.orden),
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/cotiz/fases-montaje/${faseMontaje.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar — ${faseMontaje.codigo}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Editar fase de montaje</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Código" htmlFor="codigo" error={errors.codigo} required>
                                <Input id="codigo" value={data.codigo} onChange={(e) => setData('codigo', e.target.value)} />
                            </FormField>
                            <FormField label="Nombre" htmlFor="nombre" error={errors.nombre} required>
                                <Input id="nombre" value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} />
                            </FormField>
                        </div>

                        <div className="grid grid-cols-3 gap-4">
                            <FormField label="Unidad" htmlFor="unidad" error={errors.unidad} required>
                                <Input id="unidad" value={data.unidad} onChange={(e) => setData('unidad', e.target.value)} placeholder="pza / m2 / ml" />
                            </FormField>
                            <FormField label="Centro de costo" htmlFor="centro_costo_id" error={errors.centro_costo_id}>
                                <Select id="centro_costo_id" value={data.centro_costo_id} onValueChange={(value) => setData('centro_costo_id', value)}>
                                    <option value="">(sin centro)</option>
                                    {centrosCosto.map((c) => (
                                        <option key={c.id} value={c.id}>{c.cod_coste} — {c.concepto}</option>
                                    ))}
                                </Select>
                            </FormField>
                            <FormField label="Orden" htmlFor="orden" error={errors.orden}>
                                <Input id="orden" type="number" min="0" value={data.orden} onChange={(e) => setData('orden', e.target.value)} />
                            </FormField>
                        </div>

                        <div className="flex items-center gap-2 pt-2">
                            <Button type="submit" variant="primary" disabled={processing}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Guardar cambios
                            </Button>
                            <ButtonLink variant="ghost" href="/admin/cotiz/fases-montaje">
                                Cancelar
                            </ButtonLink>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
