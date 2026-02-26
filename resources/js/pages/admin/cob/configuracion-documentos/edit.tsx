import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { type CobConfiguracionDocumento, type Obra } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import { type FormEvent } from 'react';

type Props = {
    obra: Obra;
    configuracionDocumento: CobConfiguracionDocumento;
};

export default function ConfiguracionDocumentoEdit({ obra, configuracionDocumento }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cobranza', href: '/admin/cob/obras' },
        { title: `Obra ${obra.no}`, href: `/admin/cob/obras/${obra.id}` },
        { title: 'Editar Documento', href: '#' },
    ];

    const { data, setData, post, processing, errors } = useForm({
        _method: 'put' as const,
        nombre_documento: configuracionDocumento.nombre_documento,
        descripcion: configuracionDocumento.descripcion ?? '',
        obligatorio: configuracionDocumento.obligatorio,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(`/admin/cob/obras/${obra.id}/configuracion-documentos/${configuracionDocumento.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Editar Documento de Configuracion" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Editar Documento de Configuracion - Obra {obra.no}</h1>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
                            <FormField label="Nombre Documento" htmlFor="nombre_documento" error={errors.nombre_documento} required>
                                <Input value={data.nombre_documento} onChange={(e) => setData('nombre_documento', e.target.value)} />
                            </FormField>

                            <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion}>
                                <Input value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} />
                            </FormField>

                            <div className="flex items-end">
                                <label className="label cursor-pointer gap-2">
                                    <input
                                        type="checkbox"
                                        className="checkbox checkbox-sm"
                                        checked={data.obligatorio as boolean}
                                        onChange={(e) => setData('obligatorio', e.target.checked)}
                                    />
                                    <span className="label-text">Obligatorio</span>
                                </label>
                            </div>
                        </div>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href={`/admin/cob/obras/${obra.id}`}>Cancelar</Link>
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
