import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';

type Carpeta = {
    id: number;
    nombre: string;
    descripcion: string | null;
};

type Props = {
    carpeta: Carpeta;
};

export default function DriveCarpetaEdit({ carpeta }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Drive', href: '/admin/drive' },
        { title: 'Carpetas', href: '/admin/drive/carpetas' },
        { title: carpeta.nombre, href: `/admin/drive/carpetas/${carpeta.id}` },
        { title: 'Editar', href: `/admin/drive/carpetas/${carpeta.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        nombre: carpeta.nombre,
        descripcion: carpeta.descripcion ?? '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/admin/drive/carpetas/${carpeta.id}`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Drive - Editar ${carpeta.nombre}`} />

            <div className="p-6 max-w-2xl">
                <h1 className="text-2xl font-semibold mb-6">Editar Carpeta</h1>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="nombre">Nombre *</Label>
                        <Input id="nombre" value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} required />
                        <InputError message={errors.nombre} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="descripcion">Descripción</Label>
                        <Input id="descripcion" value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} />
                        <InputError message={errors.descripcion} />
                    </div>

                    <div className="flex gap-3 pt-4">
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Guardar
                        </Button>
                        <Link href={`/admin/drive/carpetas/${carpeta.id}`} className="btn btn-ghost">Cancelar</Link>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
