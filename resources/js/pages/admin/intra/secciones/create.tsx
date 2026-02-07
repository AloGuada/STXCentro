import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { ChangeEvent, FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Intranet', href: '/admin/intra/secciones' },
    { title: 'Secciones', href: '/admin/intra/secciones' },
    { title: 'Nueva Sección', href: '/admin/intra/secciones/create' },
];

export default function SeccionesCreate() {
    const { data, setData, post, processing, errors } = useForm<{
        titulo: string;
        descripcion: string;
        boton: string;
        activo: boolean;
        file: File | null;
    }>({
        titulo: '',
        descripcion: '',
        boton: '',
        activo: true,
        file: null,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/intra/secciones');
    };

    const handleFileChange = (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] || null;
        setData('file', file);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva Sección" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nueva Sección Estática</h1>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Título" htmlFor="titulo" error={errors.titulo} required>
                            <Input
                                id="titulo"
                                value={data.titulo}
                                onChange={(e) => setData('titulo', e.target.value)}
                                placeholder="Título de la sección"
                            />
                        </FormField>

                        <FormField label="Texto del Botón" htmlFor="boton" error={errors.boton} required>
                            <Input
                                id="boton"
                                value={data.boton}
                                onChange={(e) => setData('boton', e.target.value)}
                                placeholder="Texto que aparecerá en el botón"
                            />
                        </FormField>

                        <FormField label="Descripción" htmlFor="descripcion" error={errors.descripcion}>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                placeholder="Descripción breve (opcional)"
                            />
                        </FormField>

                        <FormField label="Archivo PDF" htmlFor="file" error={errors.file} required>
                            <Input
                                id="file"
                                type="file"
                                accept=".pdf"
                                onChange={handleFileChange}
                                className="file:btn file:btn-sm file:btn-ghost"
                            />
                        </FormField>

                        <FormField label="" htmlFor="activo" error={errors.activo}>
                            <label className="flex items-center gap-2 cursor-pointer">
                                <Checkbox
                                    id="activo"
                                    checked={data.activo}
                                    onCheckedChange={(checked) => setData('activo', !!checked)}
                                />
                                <span>Activo</span>
                            </label>
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/intra/secciones">Cancelar</Link>
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
