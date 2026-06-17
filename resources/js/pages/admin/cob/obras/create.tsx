import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import { type FormEvent } from 'react';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Proyecto } from '@/types/models';

type Props = {
    proyecto: Pick<Proyecto, 'id' | 'no' | 'descripcion'>;
};

export default function ObraCreate({ proyecto }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cobranza', href: '/admin/cob/proyectos' },
        { title: proyecto.descripcion, href: `/admin/cob/proyectos/${proyecto.id}` },
        { title: 'Nueva obra', href: '#' },
    ];

    const { data, setData, post, processing, errors } = useForm({
        tipo: 'base',
        no: '',
        descripcion: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(`/admin/cob/proyectos/${proyecto.id}/obras`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva obra" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nueva obra — Proyecto {proyecto.no}</h1>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
                            <FormField label="Tipo" htmlFor="tipo" error={errors.tipo}>
                                <Select value={data.tipo} onValueChange={(v) => setData('tipo', v)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="base">Obra</SelectItem>
                                        <SelectItem value="adicional">Adicional</SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField label="No" htmlFor="no" error={errors.no} required>
                                <Input value={data.no} onChange={(e) => setData('no', e.target.value)} />
                            </FormField>

                            <FormField label="Descripción" htmlFor="descripcion" error={errors.descripcion} required>
                                <Input value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} />
                            </FormField>
                        </div>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href={`/admin/cob/proyectos/${proyecto.id}`}>Cancelar</Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Crear obra
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
