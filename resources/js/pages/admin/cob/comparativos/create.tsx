import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { COB_COMPARATIVO_ESTADO_LABELS, type Proyecto } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import { type FormEvent } from 'react';

type ObraOpcion = { id: number; no: string; tipo: string };

type Props = {
    proyecto: Pick<Proyecto, 'id' | 'no' | 'descripcion'>;
    obras: ObraOpcion[];
};

export default function ComparativoCreate({ proyecto, obras }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cobranza', href: '/admin/cob/proyectos' },
        { title: `Proyecto ${proyecto.no}`, href: `/admin/cob/proyectos/${proyecto.id}` },
        { title: 'Nuevo Comparativo', href: '#' },
    ];

    const { data, setData, post, processing, errors } = useForm({
        obra_id: '',
        descripcion: '',
        monto_impacto: '',
        fecha_identificacion: '',
        estado: 'analisis',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(`/admin/cob/proyectos/${proyecto.id}/comparativos`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Comparativo" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo Comparativo - Proyecto {proyecto.no}</h1>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
                            <FormField label="Obra" htmlFor="obra_id" error={errors.obra_id} required>
                                <Select value={data.obra_id} onValueChange={(v) => setData('obra_id', v)}>
                                    <SelectTrigger><SelectValue placeholder="Seleccionar obra" /></SelectTrigger>
                                    <SelectContent>
                                        {obras.map((o) => (
                                            <SelectItem key={o.id} value={String(o.id)}>
                                                {o.no} {o.tipo === 'base' ? '(base)' : '(adicional)'}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField label="Monto Impacto" htmlFor="monto_impacto" error={errors.monto_impacto} required>
                                <Input type="number" step="0.01" value={data.monto_impacto} onChange={(e) => setData('monto_impacto', e.target.value)} />
                            </FormField>

                            <FormField label="Fecha Identificacion" htmlFor="fecha_identificacion" error={errors.fecha_identificacion}>
                                <Input type="date" value={data.fecha_identificacion} onChange={(e) => setData('fecha_identificacion', e.target.value)} />
                            </FormField>

                            <FormField label="Estado" htmlFor="estado" error={errors.estado}>
                                <Select value={data.estado} onValueChange={(v) => setData('estado', v)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(COB_COMPARATIVO_ESTADO_LABELS).map(([k, v]) => (
                                            <SelectItem key={k} value={k}>{v}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                        </div>

                        <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                            <textarea
                                className="textarea textarea-bordered w-full"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                rows={3}
                            />
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href={`/admin/cob/proyectos/${proyecto.id}`}>Cancelar</Link>
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
