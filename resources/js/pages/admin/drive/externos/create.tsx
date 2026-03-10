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
    { title: 'Externos', href: '/admin/drive/externos' },
    { title: 'Crear', href: '/admin/drive/externos/create' },
];

export default function DriveExternoCreate() {
    const { data, setData, post, processing, errors } = useForm({
        nombre: '',
        email: '',
        password: '',
        password_confirmation: '',
        telefono: '',
        empresa: '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post('/admin/drive/externos');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Drive - Crear Externo" />

            <div className="p-6 max-w-2xl">
                <h1 className="text-2xl font-semibold mb-6">Crear Usuario Externo</h1>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="nombre">Nombre *</Label>
                        <Input id="nombre" value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} required />
                        <InputError message={errors.nombre} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">Correo electrónico *</Label>
                        <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="password">Contraseña *</Label>
                            <Input id="password" type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} required />
                            <InputError message={errors.password} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">Confirmar Contraseña *</Label>
                            <Input id="password_confirmation" type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} required />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="telefono">Teléfono</Label>
                            <Input id="telefono" value={data.telefono} onChange={(e) => setData('telefono', e.target.value)} />
                            <InputError message={errors.telefono} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="empresa">Empresa</Label>
                            <Input id="empresa" value={data.empresa} onChange={(e) => setData('empresa', e.target.value)} />
                            <InputError message={errors.empresa} />
                        </div>
                    </div>

                    <div className="flex gap-3 pt-4">
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Crear
                        </Button>
                        <Link href="/admin/drive/externos" className="btn btn-ghost">Cancelar</Link>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
