import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import { type FormEvent } from 'react';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { COB_DISPUTA_ESTADO_LABELS, type CobDisputa, type Obra } from '@/types/models';

type Props = {
    obra: Obra;
    disputa: CobDisputa;
};

export default function DisputaEdit({ obra, disputa }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cobranza', href: '/admin/cob/obras' },
        { title: `Obra ${obra.no}`, href: `/admin/cob/obras/${obra.id}` },
        { title: 'Editar Disputa', href: '#' },
    ];

    const { data, setData, post, processing, errors } = useForm({
        _method: 'put' as const,
        descripcion: disputa.descripcion,
        fecha_inicio: disputa.fecha_inicio ?? '',
        fecha_resolucion: disputa.fecha_resolucion ?? '',
        estado: disputa.estado,
        resultado: disputa.resultado ?? '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(`/admin/cob/obras/${obra.id}/disputas/${disputa.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Editar Disputa" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Editar Disputa - Obra {obra.no}</h1>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
                            <FormField label="Fecha Inicio" htmlFor="fecha_inicio" error={errors.fecha_inicio}>
                                <Input type="date" value={data.fecha_inicio} onChange={(e) => setData('fecha_inicio', e.target.value)} />
                            </FormField>

                            <FormField label="Fecha Resolucion" htmlFor="fecha_resolucion" error={errors.fecha_resolucion}>
                                <Input type="date" value={data.fecha_resolucion} onChange={(e) => setData('fecha_resolucion', e.target.value)} />
                            </FormField>

                            <FormField label="Estado" htmlFor="estado" error={errors.estado}>
                                <Select value={data.estado} onValueChange={(v) => setData('estado', v)}>
                                    {Object.entries(COB_DISPUTA_ESTADO_LABELS).map(([k, v]) => (
                                        <SelectItem key={k} value={k}>{v}</SelectItem>
                                    ))}
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

                        <FormField label="Resultado" htmlFor="resultado" error={errors.resultado}>
                            <textarea
                                className="textarea textarea-bordered w-full"
                                value={data.resultado}
                                onChange={(e) => setData('resultado', e.target.value)}
                                rows={2}
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
