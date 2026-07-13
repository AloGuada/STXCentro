import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import { type ChangeEvent, type FormEvent } from 'react';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { COB_ANTICIPO_ESTADO_LABELS, type CobAnticipo, type Obra } from '@/types/models';

type Props = {
    obra: Obra;
    anticipo: CobAnticipo;
};

export default function AnticipoEdit({ obra, anticipo }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cobranza', href: '/admin/cob/obras' },
        { title: `Obra ${obra.no}`, href: `/admin/cob/obras/${obra.id}` },
        { title: 'Editar Anticipo', href: '#' },
    ];

    const { data, setData, post, processing, errors } = useForm({
        _method: 'put' as const,
        folio: anticipo.folio ?? '',
        fecha_emision: anticipo.fecha_emision ?? '',
        monto: String(anticipo.monto),
        moneda: anticipo.moneda,
        estado: anticipo.estado,
        fecha_pagado: anticipo.fecha_pagado ?? '',
        comentarios: anticipo.comentarios ?? '',
        comprobante: null as File | null,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(`/admin/cob/obras/${obra.id}/anticipos/${anticipo.id}`, {
            forceFormData: true,
        });
    };

    const handleFileChange = (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] || null;
        setData('comprobante', file);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Editar Anticipo" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Editar Anticipo - Obra {obra.no}</h1>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
                            <FormField label="Folio" htmlFor="folio" error={errors.folio}>
                                <Input value={data.folio} onChange={(e) => setData('folio', e.target.value)} />
                            </FormField>

                            <FormField label="Fecha Emision" htmlFor="fecha_emision" error={errors.fecha_emision}>
                                <Input type="date" value={data.fecha_emision} onChange={(e) => setData('fecha_emision', e.target.value)} />
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

                            <FormField label="Estado" htmlFor="estado" error={errors.estado}>
                                <Select value={data.estado} onValueChange={(v) => setData('estado', v)}>
                                    {Object.entries(COB_ANTICIPO_ESTADO_LABELS).map(([k, v]) => (
                                        <SelectItem key={k} value={k}>{v}</SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField label="Fecha de Pago" htmlFor="fecha_pagado" error={errors.fecha_pagado}>
                                <Input type="date" value={data.fecha_pagado} onChange={(e) => setData('fecha_pagado', e.target.value)} />
                            </FormField>

                            <FormField label="Comprobante de Pago" htmlFor="comprobante" error={errors.comprobante}>
                                <Input
                                    id="comprobante"
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    onChange={handleFileChange}
                                    className="file:btn file:btn-sm file:btn-ghost"
                                />
                                {anticipo.comprobante && !data.comprobante && (
                                    <p className="mt-1 text-xs opacity-70">Archivo actual: {anticipo.comprobante.split('/').pop()}</p>
                                )}
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
