import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Proyecto } from '@/types/models';

type Props = {
    proyecto: Proyecto;
    nextNumber: number;
};

export default function EstimacionCreate({ proyecto, nextNumber }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cobranza', href: '/admin/cob/proyectos' },
        { title: `Proyecto ${proyecto.no}`, href: `/admin/cob/proyectos/${proyecto.id}` },
        { title: 'Nueva Estimacion', href: '#' },
    ];

    const { data, setData, post, processing, errors } = useForm({
        numero_estimacion: nextNumber,
        folio: '',
        tipo: '',
        fecha_emision: '',
        inicio: '',
        fin: '',
        monto_estimado: '',
        monto_total: '',
        moneda: 'MXN',
        comentarios: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(`/admin/cob/proyectos/${proyecto.id}/estimaciones`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva Estimacion" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nueva Estimacion - Proyecto {proyecto.no}</h1>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
                            <FormField label="Numero Estimacion" htmlFor="numero_estimacion" error={errors.numero_estimacion} required>
                                <Input type="number" min="1" value={data.numero_estimacion} onChange={(e) => setData('numero_estimacion', Number(e.target.value))} />
                            </FormField>

                            <FormField label="Folio" htmlFor="folio" error={errors.folio}>
                                <Input value={data.folio} onChange={(e) => setData('folio', e.target.value)} />
                            </FormField>

                            <FormField label="Tipo" htmlFor="tipo" error={errors.tipo}>
                                <Input value={data.tipo} onChange={(e) => setData('tipo', e.target.value)} placeholder="ej. normal, extraordinaria" />
                            </FormField>

                            <FormField label="Fecha Emision" htmlFor="fecha_emision" error={errors.fecha_emision}>
                                <Input type="date" value={data.fecha_emision} onChange={(e) => setData('fecha_emision', e.target.value)} />
                            </FormField>

                            <FormField label="Inicio Periodo" htmlFor="inicio" error={errors.inicio}>
                                <Input type="date" value={data.inicio} onChange={(e) => setData('inicio', e.target.value)} />
                            </FormField>

                            <FormField label="Fin Periodo" htmlFor="fin" error={errors.fin}>
                                <Input type="date" value={data.fin} onChange={(e) => setData('fin', e.target.value)} />
                            </FormField>

                            <FormField label="Monto Estimado" htmlFor="monto_estimado" error={errors.monto_estimado} required>
                                <Input type="number" step="0.01" value={data.monto_estimado} onChange={(e) => setData('monto_estimado', e.target.value)} />
                            </FormField>

                            <FormField label="Monto Total" htmlFor="monto_total" error={errors.monto_total}>
                                <Input type="number" step="0.01" value={data.monto_total} onChange={(e) => setData('monto_total', e.target.value)} />
                            </FormField>
                        </div>

                        <FormField label="Comentarios" htmlFor="comentarios" error={errors.comentarios}>
                            <textarea
                                className="textarea textarea-bordered w-full"
                                value={data.comentarios}
                                onChange={(e) => setData('comentarios', e.target.value)}
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
