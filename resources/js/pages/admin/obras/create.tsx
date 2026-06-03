import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { OBRA_ESTATUS_LABELS, type ObraEstatus } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Obras', href: '/admin/obras' },
    { title: 'Nueva Obra', href: '/admin/obras/create' },
];

export default function ObrasCreate() {
    const { data, setData, post, processing, errors } = useForm({
        no: '',
        descripcion: '',
        fecha_inicio: '',
        fecha_fin: '',
        presupuesto_total: '0',
        ingreso_real: '',
        estatus: 'abierta' as ObraEstatus,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/obras');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva Obra" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nueva Obra</h1>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Numero" htmlFor="no" error={errors.no} required>
                                <Input
                                    id="no"
                                    value={data.no}
                                    onChange={(e) => setData('no', e.target.value)}
                                    placeholder="Ej: OBR-001"
                                />
                            </FormField>

                            <FormField label="Estatus" htmlFor="estatus" error={errors.estatus}>
                                <Select id="estatus" value={data.estatus} onValueChange={(value) => setData('estatus', value as ObraEstatus)}>
                                    {(Object.keys(OBRA_ESTATUS_LABELS) as ObraEstatus[]).map((key) => (
                                        <option key={key} value={key}>{OBRA_ESTATUS_LABELS[key]}</option>
                                    ))}
                                </Select>
                            </FormField>
                        </div>

                        <FormField
                            label="Descripcion"
                            htmlFor="descripcion"
                            error={errors.descripcion}
                            required
                        >
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                placeholder="Descripcion de la obra"
                            />
                        </FormField>

                        <div className="grid grid-cols-3 gap-4">
                            <FormField label="Fecha Inicio" htmlFor="fecha_inicio" error={errors.fecha_inicio}>
                                <Input
                                    id="fecha_inicio"
                                    type="date"
                                    value={data.fecha_inicio}
                                    onChange={(e) => setData('fecha_inicio', e.target.value)}
                                />
                            </FormField>

                            <FormField label="Fecha Fin" htmlFor="fecha_fin" error={errors.fecha_fin}>
                                <Input
                                    id="fecha_fin"
                                    type="date"
                                    value={data.fecha_fin}
                                    onChange={(e) => setData('fecha_fin', e.target.value)}
                                />
                            </FormField>

                            <FormField label="Presupuesto Total" htmlFor="presupuesto_total" error={errors.presupuesto_total}>
                                <Input
                                    id="presupuesto_total"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.presupuesto_total}
                                    onChange={(e) => setData('presupuesto_total', e.target.value)}
                                />
                            </FormField>
                        </div>

                        <FormField label="Ingreso Real" htmlFor="ingreso_real" error={errors.ingreso_real}>
                            <Input
                                id="ingreso_real"
                                type="number"
                                step="0.0001"
                                min="0"
                                value={data.ingreso_real}
                                onChange={(e) => setData('ingreso_real', e.target.value)}
                                placeholder="0.00"
                            />
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/obras">Cancelar</Link>
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
