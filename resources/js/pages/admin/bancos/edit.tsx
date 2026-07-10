import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';
import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Banco } from '@/types/models';

type Props = {
    banco: Banco;
};

export default function BancosEdit({ banco }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Bancos', href: '/admin/bancos' },
        { title: banco.nombre, href: `/admin/bancos/${banco.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        nombre: banco.nombre,
        digitos_cuenta: banco.digitos_cuenta ? String(banco.digitos_cuenta) : '',
        es_pagador: banco.es_pagador,
        activo: banco.activo,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/bancos/${banco.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${banco.nombre}`} />

            <div className="p-6">
                <div className="w-3/4 max-w-xl">
                    <div className="mb-6 flex items-center justify-between">
                        <h1 className="text-2xl font-semibold">Editar Banco</h1>
                        <DeleteDialog
                            title="Eliminar banco"
                            description={`¿Eliminar el banco "${banco.nombre}"?`}
                            deleteUrl={`/admin/bancos/${banco.id}`}
                        />
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Nombre" htmlFor="nombre" error={errors.nombre} required>
                            <Input id="nombre" value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} />
                        </FormField>

                        <label className="label cursor-pointer gap-2 w-fit">
                            <input type="checkbox" className="checkbox" checked={data.es_pagador} onChange={(e) => setData('es_pagador', e.target.checked)} />
                            <span className="label-text">Es el banco pagador de la empresa</span>
                        </label>
                        <p className="text-xs text-base-content/60">
                            Solo un banco puede ser pagador; al marcar este se desmarca el anterior. El pagador captura número de cuenta; los demás, CLABE.
                        </p>

                        {data.es_pagador && (
                            <FormField label="Dígitos del número de cuenta" htmlFor="digitos_cuenta" error={errors.digitos_cuenta} required>
                                <Input
                                    id="digitos_cuenta"
                                    type="number"
                                    min="1"
                                    max="30"
                                    value={data.digitos_cuenta}
                                    onChange={(e) => setData('digitos_cuenta', e.target.value)}
                                    placeholder="Ej: 10"
                                />
                            </FormField>
                        )}

                        <label className="label cursor-pointer gap-2 w-fit">
                            <input type="checkbox" className="checkbox" checked={data.activo} onChange={(e) => setData('activo', e.target.checked)} />
                            <span className="label-text">Activo</span>
                        </label>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/bancos">Cancelar</Link>
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
