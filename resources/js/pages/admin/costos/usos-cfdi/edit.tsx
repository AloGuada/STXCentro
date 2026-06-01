import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';
import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosUsoCfdi } from '@/types/models';

type Props = {
    usoCfdi: CostosUsoCfdi;
};

export default function UsosCfdiEdit({ usoCfdi }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/usos-cfdi' },
        { title: 'Usos CFDI', href: '/admin/costos/usos-cfdi' },
        { title: usoCfdi.clave, href: `/admin/costos/usos-cfdi/${usoCfdi.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        clave: usoCfdi.clave,
        descripcion: usoCfdi.descripcion,
        activo: usoCfdi.activo,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/costos/usos-cfdi/${usoCfdi.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${usoCfdi.clave}`} />

            <div className="p-6">
                <div className="w-3/4 max-w-xl">
                    <div className="mb-6 flex items-center justify-between">
                        <h1 className="text-2xl font-semibold">Editar Uso CFDI</h1>
                        <DeleteDialog
                            title="Eliminar uso CFDI"
                            description={`¿Eliminar el uso "${usoCfdi.clave} - ${usoCfdi.descripcion}"?`}
                            deleteUrl={`/admin/costos/usos-cfdi/${usoCfdi.id}`}
                        />
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Clave SAT" htmlFor="clave" error={errors.clave} required>
                            <Input id="clave" value={data.clave} onChange={(e) => setData('clave', e.target.value)} />
                        </FormField>

                        <FormField label="Descripción" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input id="descripcion" value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} />
                        </FormField>

                        <label className="label cursor-pointer gap-2 w-fit">
                            <input type="checkbox" className="checkbox" checked={data.activo} onChange={(e) => setData('activo', e.target.checked)} />
                            <span className="label-text">Activo</span>
                        </label>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/costos/usos-cfdi">Cancelar</Link>
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
