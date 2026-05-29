import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';
import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { RegimenFiscal } from '@/types/models';

type Props = {
    regimen: RegimenFiscal;
};

export default function RegimenesFiscalesEdit({ regimen }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Regímenes Fiscales', href: '/admin/regimenes-fiscales' },
        { title: regimen.clave, href: `/admin/regimenes-fiscales/${regimen.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        clave: regimen.clave,
        descripcion: regimen.descripcion,
        aplica_persona_fisica: regimen.aplica_persona_fisica,
        aplica_persona_moral: regimen.aplica_persona_moral,
        activo: regimen.activo,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/regimenes-fiscales/${regimen.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${regimen.clave}`} />

            <div className="p-6">
                <div className="w-3/4 max-w-xl">
                    <div className="mb-6 flex items-center justify-between">
                        <h1 className="text-2xl font-semibold">Editar Régimen Fiscal</h1>
                        <DeleteDialog
                            title="Eliminar régimen"
                            description={`¿Eliminar el régimen "${regimen.clave} - ${regimen.descripcion}"?`}
                            deleteUrl={`/admin/regimenes-fiscales/${regimen.id}`}
                        />
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Clave" htmlFor="clave" error={errors.clave} required>
                            <Input id="clave" value={data.clave} onChange={(e) => setData('clave', e.target.value)} />
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
