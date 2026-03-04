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
    { title: 'RH', href: '/admin/rh/skills' },
    { title: 'Personas', href: '/admin/rh/personas' },
    { title: 'Nueva Persona', href: '/admin/rh/personas/create' },
];

export default function PersonaCreate() {
    const { data, setData, post, processing, errors } = useForm({
        nombre: '',
        apellido: '',
        email: '',
        telefono: '',
        fecha_nacimiento: '',
        cv: null as File | null,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/rh/personas', { forceFormData: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva Persona" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nueva Persona</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Nombre" htmlFor="nombre" error={errors.nombre} required>
                                <Input id="nombre" value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} placeholder="Nombre" />
                            </FormField>

                            <FormField label="Apellido" htmlFor="apellido" error={errors.apellido} required>
                                <Input id="apellido" value={data.apellido} onChange={(e) => setData('apellido', e.target.value)} placeholder="Apellido" />
                            </FormField>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Email" htmlFor="email" error={errors.email}>
                                <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} placeholder="correo@ejemplo.com" />
                            </FormField>

                            <FormField label="Telefono" htmlFor="telefono" error={errors.telefono}>
                                <Input id="telefono" value={data.telefono} onChange={(e) => setData('telefono', e.target.value)} placeholder="Telefono" />
                            </FormField>
                        </div>

                        <FormField label="Fecha de Nacimiento" htmlFor="fecha_nacimiento" error={errors.fecha_nacimiento}>
                            <Input id="fecha_nacimiento" type="date" value={data.fecha_nacimiento} onChange={(e) => setData('fecha_nacimiento', e.target.value)} />
                        </FormField>

                        <FormField label="CV (PDF, DOC, DOCX)" htmlFor="cv" error={errors.cv}>
                            <Input
                                id="cv"
                                type="file"
                                accept=".pdf,.doc,.docx"
                                onChange={(e) => setData('cv', e.target.files?.[0] ?? null)}
                            />
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/rh/personas">Cancelar</Link>
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
