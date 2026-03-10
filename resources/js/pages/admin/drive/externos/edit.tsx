import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';

type Externo = {
    id: number;
    nombre: string;
    email: string;
    telefono: string | null;
    empresa: string | null;
    activo: boolean;
    carpetas?: { id: number; nombre: string }[];
};

type Props = {
    externo: Externo;
};

export default function DriveExternoEdit({ externo }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Drive', href: '/admin/drive' },
        { title: 'Externos', href: '/admin/drive/externos' },
        { title: externo.nombre, href: `/admin/drive/externos/${externo.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        nombre: externo.nombre,
        email: externo.email,
        password: '',
        password_confirmation: '',
        telefono: externo.telefono ?? '',
        empresa: externo.empresa ?? '',
        activo: externo.activo,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/admin/drive/externos/${externo.id}`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Drive - Editar ${externo.nombre}`} />

            <div className="p-6 max-w-2xl">
                <h1 className="text-2xl font-semibold mb-6">Editar Usuario Externo</h1>

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
                            <Label htmlFor="password">Nueva Contraseña</Label>
                            <Input id="password" type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} placeholder="Dejar vacío para no cambiar" />
                            <InputError message={errors.password} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">Confirmar</Label>
                            <Input id="password_confirmation" type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="telefono">Teléfono</Label>
                            <Input id="telefono" value={data.telefono} onChange={(e) => setData('telefono', e.target.value)} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="empresa">Empresa</Label>
                            <Input id="empresa" value={data.empresa} onChange={(e) => setData('empresa', e.target.value)} />
                        </div>
                    </div>

                    <div className="form-control">
                        <label className="label cursor-pointer justify-start gap-3">
                            <input
                                type="checkbox"
                                className="toggle toggle-success"
                                checked={data.activo}
                                onChange={(e) => setData('activo', e.target.checked)}
                            />
                            <span className="label-text">Cuenta activa</span>
                        </label>
                    </div>

                    {externo.carpetas && externo.carpetas.length > 0 && (
                        <div className="mt-4">
                            <h3 className="font-medium mb-2">Carpetas asignadas</h3>
                            <div className="flex flex-wrap gap-2">
                                {externo.carpetas.map((c) => (
                                    <Link key={c.id} href={`/admin/drive/carpetas/${c.id}`} className="badge badge-outline badge-lg gap-1 hover:badge-primary">
                                        {c.nombre}
                                    </Link>
                                ))}
                            </div>
                        </div>
                    )}

                    <div className="flex gap-3 pt-4">
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Guardar
                        </Button>
                        <Link href="/admin/drive/externos" className="btn btn-ghost">Cancelar</Link>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
