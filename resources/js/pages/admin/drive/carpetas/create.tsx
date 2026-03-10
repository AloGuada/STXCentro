import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Drive', href: '/admin/drive' },
    { title: 'Carpetas', href: '/admin/drive/carpetas' },
    { title: 'Crear', href: '/admin/drive/carpetas/create' },
];

export default function DriveCarpetaCreate() {
    const { data, setData, post, processing, errors } = useForm({
        nombre: '',
        descripcion: '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post('/admin/drive/carpetas');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Drive - Crear Carpeta" />

            <div className="p-6 max-w-2xl">
                <h1 className="text-2xl font-semibold mb-6">Crear Carpeta</h1>

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
                            Crear
                        </Button>
                        <Link href="/admin/drive/carpetas" className="btn btn-ghost">Cancelar</Link>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
