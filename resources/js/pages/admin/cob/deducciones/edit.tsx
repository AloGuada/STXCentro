import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import { type FormEvent } from 'react';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { fechaParaInput } from '@/lib/fechas';
import type { BreadcrumbItem } from '@/types';
import { type CobDeduccion, type Obra } from '@/types/models';

type Props = {
    obra: Obra;
    deduccion: CobDeduccion;
};

export default function DeduccionEdit({ obra, deduccion }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cobranza', href: '/admin/cob/obras' },
        { title: `Obra ${obra.no}`, href: `/admin/cob/obras/${obra.id}` },
        { title: 'Editar Deduccion', href: '#' },
    ];

    const { data, setData, post, processing, errors } = useForm({
        _method: 'put' as const,
        descripcion: deduccion.descripcion,
        monto: String(deduccion.monto),
        moneda: deduccion.moneda,
        fecha: fechaParaInput(deduccion.fecha),
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(`/admin/cob/obras/${obra.id}/deducciones/${deduccion.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Editar Deduccion" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Editar Deduccion - Obra {obra.no}</h1>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
                            <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                                <Input value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} />
                            </FormField>

                            <FormField label="Monto" htmlFor="monto" error={errors.monto} required>
                                <Input type="number" step="0.01" value={data.monto} onChange={(e) => setData('monto', e.target.value)} />
                            </FormField>

                            <FormField label="Moneda" htmlFor="moneda" error={errors.moneda}>
                                <Select value={data.moneda} onValueChange={(v) => setData('moneda', v)}>
                                    <SelectItem value="MXN">MXN</SelectItem>
                                    <SelectItem value="USD">USD</SelectItem>
                                </Select>
                            </FormField>

                            <FormField label="Fecha" htmlFor="fecha" error={errors.fecha}>
                                <Input type="date" value={data.fecha} onChange={(e) => setData('fecha', e.target.value)} />
                            </FormField>
                        </div>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href={`/admin/cob/obras/${obra.id}`}>Cancelar</Link>
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
