import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cobranza', href: '/admin/cob/dashboard' },
    { title: 'Clientes', href: '/admin/cob/clientes' },
    { title: 'Nuevo', href: '/admin/cob/clientes/create' },
];

export default function ClientesCreate() {
    const { data, setData, post, processing, errors } = useForm({
        nombre: '',
        rfc: '',
        direccion: '',
        telefono: '',
        email: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/cob/clientes');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Cliente" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo Cliente</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Nombre" htmlFor="nombre" error={errors.nombre} required>
                            <Input
                                id="nombre"
                                value={data.nombre}
                                onChange={(e) => setData('nombre', e.target.value)}
                                placeholder="Nombre del cliente"
                            />
                        </FormField>

                        <FormField label="RFC" htmlFor="rfc" error={errors.rfc}>
                            <Input
                                id="rfc"
                                value={data.rfc}
                                onChange={(e) => setData('rfc', e.target.value)}
                                placeholder="RFC del cliente"
                            />
                        </FormField>

                        <FormField label="Direccion" htmlFor="direccion" error={errors.direccion}>
                            <Input
                                id="direccion"
                                value={data.direccion}
                                onChange={(e) => setData('direccion', e.target.value)}
                                placeholder="Direccion del cliente"
                            />
                        </FormField>

                        <FormField label="Telefono" htmlFor="telefono" error={errors.telefono}>
                            <Input
                                id="telefono"
                                value={data.telefono}
                                onChange={(e) => setData('telefono', e.target.value)}
                                placeholder="Telefono de contacto"
                            />
                        </FormField>

                        <FormField label="Email" htmlFor="email" error={errors.email}>
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder="Correo electronico"
                            />
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/cob/clientes">Cancelar</Link>
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
