import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { type Obra } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import { type FormEvent } from 'react';

type Props = {
    obra: Obra;
};

export default function PartidaCreate({ obra }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cobranza', href: '/admin/cob/obras' },
        { title: `Obra ${obra.no}`, href: `/admin/cob/obras/${obra.id}` },
        { title: 'Nueva Partida', href: '#' },
    ];

    const { data, setData, post, processing, errors } = useForm({
        tipo: 'suministro',
        descripcion: '',
        monto: '',
        moneda: 'MXN',
        es_adicional: false,
        es_subobra: false,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(`/admin/cob/obras/${obra.id}/partidas`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva Partida" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nueva Partida - Obra {obra.no}</h1>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
                            <FormField label="Tipo" htmlFor="tipo" error={errors.tipo}>
                                <Select value={data.tipo} onValueChange={(v) => setData('tipo', v)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="suministro">Suministro</SelectItem>
                                        <SelectItem value="montaje">Montaje</SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                                <Input value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} />
                            </FormField>

                            <FormField label="Monto" htmlFor="monto" error={errors.monto} required>
                                <Input type="number" step="0.01" value={data.monto} onChange={(e) => setData('monto', e.target.value)} />
                            </FormField>

                            <FormField label="Moneda" htmlFor="moneda" error={errors.moneda}>
                                <Select value={data.moneda} onValueChange={(v) => setData('moneda', v)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="MXN">MXN</SelectItem>
                                        <SelectItem value="USD">USD</SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <div className="flex items-end gap-6">
                                <label className="label cursor-pointer gap-2">
                                    <input
                                        type="checkbox"
                                        className="checkbox checkbox-sm"
                                        checked={data.es_adicional as boolean}
                                        onChange={(e) => setData('es_adicional', e.target.checked)}
                                    />
                                    <span className="label-text">Adicional</span>
                                </label>
                                <label className="label cursor-pointer gap-2">
                                    <input
                                        type="checkbox"
                                        className="checkbox checkbox-sm"
                                        checked={data.es_subobra as boolean}
                                        onChange={(e) => setData('es_subobra', e.target.checked)}
                                    />
                                    <span className="label-text">Subobra</span>
                                </label>
                            </div>
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
