import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Regímenes Fiscales', href: '/admin/regimenes-fiscales' },
    { title: 'Nuevo', href: '/admin/regimenes-fiscales/create' },
];

export default function RegimenesFiscalesCreate() {
    const { data, setData, post, processing, errors } = useForm({
        clave: '',
        descripcion: '',
        aplica_persona_fisica: true,
        aplica_persona_moral: true,
        activo: true,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/regimenes-fiscales');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Régimen Fiscal" />

            <div className="p-6">
                <div className="w-3/4 max-w-xl">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo Régimen Fiscal</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Clave" htmlFor="clave" error={errors.clave} required>
                            <Input id="clave" value={data.clave} onChange={(e) => setData('clave', e.target.value)} placeholder="Ej: 626" />
                        </FormField>

                        <FormField label="Descripción" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input id="descripcion" value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} />
                        </FormField>

                        <label className="label cursor-pointer gap-2 w-fit">
                            <input type="checkbox" className="checkbox" checked={data.aplica_persona_fisica} onChange={(e) => setData('aplica_persona_fisica', e.target.checked)} />
                            <span className="label-text">Aplica a Persona Física</span>
                        </label>
                        <label className="label cursor-pointer gap-2 w-fit">
                            <input type="checkbox" className="checkbox" checked={data.aplica_persona_moral} onChange={(e) => setData('aplica_persona_moral', e.target.checked)} />
                            <span className="label-text">Aplica a Persona Moral</span>
                        </label>
                        <label className="label cursor-pointer gap-2 w-fit">
                            <input type="checkbox" className="checkbox" checked={data.activo} onChange={(e) => setData('activo', e.target.checked)} />
                            <span className="label-text">Activo</span>
                        </label>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/regimenes-fiscales">Cancelar</Link>
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
