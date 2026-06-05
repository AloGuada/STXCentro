import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon, PlusIcon, Trash2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/tipo-solicitudes' },
    { title: 'Tipo Solicitudes', href: '/admin/costos/tipo-solicitudes' },
    { title: 'Nuevo', href: '/admin/costos/tipo-solicitudes/create' },
];

type DocForm = {
    titulo: string;
    multiple: boolean;
    texto: string;
    texto_adicional: boolean;
};

export default function TipoSolicitudesCreate() {
    const { data, setData, post, processing, errors } = useForm<{
        titulo: string;
        descripcion: string;
        rubros: boolean;
        documentos: DocForm[];
    }>({
        titulo: '',
        descripcion: '',
        rubros: false,
        documentos: [],
    });

    const addDocumento = () => {
        setData('documentos', [...data.documentos, { titulo: '', multiple: false, texto: '', texto_adicional: false }]);
    };

    const removeDocumento = (index: number) => {
        setData('documentos', data.documentos.filter((_, i) => i !== index));
    };

    const updateDocumento = (index: number, field: keyof DocForm, value: string | boolean) => {
        const updated = [...data.documentos];
        updated[index] = { ...updated[index], [field]: value };
        setData('documentos', updated);
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/costos/tipo-solicitudes');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Tipo Solicitud" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo Tipo Solicitud</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Título" htmlFor="titulo" error={errors.titulo} required>
                            <Input id="titulo" value={data.titulo} onChange={(e) => setData('titulo', e.target.value)} />
                        </FormField>

                        <FormField label="Descripción" htmlFor="descripcion" error={errors.descripcion}>
                            <textarea
                                id="descripcion"
                                className="textarea textarea-bordered w-full"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                            />
                        </FormField>

                        <label className="label cursor-pointer justify-start gap-2">
                            <input type="checkbox" className="checkbox" checked={data.rubros} onChange={(e) => setData('rubros', e.target.checked)} />
                            <span className="label-text">Requiere centros de costos</span>
                        </label>

                        <div className="divider" />

                        <div className="flex items-center justify-between">
                            <h2 className="text-lg font-medium">Documentos Requeridos</h2>
                            <Button type="button" variant="outline" onClick={addDocumento}>
                                <PlusIcon className="size-4" />
                                Agregar Documento
                            </Button>
                        </div>

                        {data.documentos.length === 0 && (
                            <p className="text-sm text-base-content/60">No hay documentos requeridos.</p>
                        )}

                        {data.documentos.map((doc, index) => (
                            <div key={index} className="rounded-lg border border-base-300 p-4 space-y-3">
                                <div className="flex items-start justify-between">
                                    <h3 className="font-medium">Documento {index + 1}</h3>
                                    <button type="button" className="btn btn-ghost btn-sm text-error" onClick={() => removeDocumento(index)}>
                                        <Trash2Icon className="size-4" />
                                    </button>
                                </div>
                                <FormField label="Título" htmlFor={`doc_titulo_${index}`} error={errors[`documentos.${index}.titulo` as keyof typeof errors]} required>
                                    <Input id={`doc_titulo_${index}`} value={doc.titulo} onChange={(e) => updateDocumento(index, 'titulo', e.target.value)} />
                                </FormField>
                                <label className="label cursor-pointer justify-start gap-2">
                                    <input type="checkbox" className="checkbox checkbox-sm" checked={doc.multiple} onChange={(e) => updateDocumento(index, 'multiple', e.target.checked)} />
                                    <span className="label-text">Permite múltiples archivos</span>
                                </label>
                                <FormField label="Texto" htmlFor={`doc_texto_${index}`}>
                                    <Input id={`doc_texto_${index}`} value={doc.texto} onChange={(e) => updateDocumento(index, 'texto', e.target.value)} />
                                </FormField>
                                <label className="label cursor-pointer justify-start gap-2">
                                    <input type="checkbox" className="checkbox checkbox-sm" checked={doc.texto_adicional} onChange={(e) => updateDocumento(index, 'texto_adicional', e.target.checked)} />
                                    <span className="label-text">Requiere información adicional por archivo</span>
                                </label>
                            </div>
                        ))}

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/costos/tipo-solicitudes">Cancelar</Link>
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
