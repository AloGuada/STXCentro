import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { RhPersona } from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FileIcon, Loader2Icon, TrashIcon, UploadIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { useRef, useState } from 'react';

type Props = {
    persona: RhPersona;
};

export default function PersonaEdit({ persona }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'RH', href: '/admin/rh/skills' },
        { title: 'Personas', href: '/admin/rh/personas' },
        { title: `${persona.nombre} ${persona.apellido}`, href: `/admin/rh/personas/${persona.id}` },
        { title: 'Editar', href: `/admin/rh/personas/${persona.id}/edit` },
    ];

    const { data, setData, post, processing, errors } = useForm({
        _method: 'put' as const,
        nombre: persona.nombre,
        apellido: persona.apellido,
        email: persona.email ?? '',
        telefono: persona.telefono ?? '',
        fecha_nacimiento: persona.fecha_nacimiento ?? '',
        cv: null as File | null,
    });

    const [docTipo, setDocTipo] = useState('');
    const [docFile, setDocFile] = useState<File | null>(null);
    const [docUploading, setDocUploading] = useState(false);
    const docInputRef = useRef<HTMLInputElement>(null);

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(`/admin/rh/personas/${persona.id}`, { forceFormData: true });
    };

    const handleDocUpload = () => {
        if (!docFile || !docTipo.trim()) return;
        const formData = new FormData();
        formData.append('archivo', docFile);
        formData.append('tipo_documento', docTipo.trim());

        router.post(`/admin/rh/personas/${persona.id}/documentos`, formData, {
            preserveScroll: true,
            onStart: () => setDocUploading(true),
            onFinish: () => {
                setDocUploading(false);
                setDocTipo('');
                setDocFile(null);
                if (docInputRef.current) docInputRef.current.value = '';
            },
        });
    };

    const removeDocumento = (docId: number) => {
        router.delete(`/admin/rh/personas/${persona.id}/documentos/${docId}`, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${persona.nombre} ${persona.apellido}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <div className="mb-6 flex items-center justify-between">
                        <h1 className="text-2xl font-semibold">Editar Persona</h1>
                        <DeleteDialog
                            title="Eliminar persona"
                            description={`¿Estas seguro de eliminar a "${persona.nombre} ${persona.apellido}"? Esta accion no se puede deshacer.`}
                            deleteUrl={`/admin/rh/personas/${persona.id}`}
                        />
                    </div>

                    {/* Datos personales */}
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

                        <FormField label="CV" htmlFor="cv" error={errors.cv}>
                            <div className="flex items-center gap-2">
                                <Input
                                    id="cv"
                                    type="file"
                                    accept=".pdf,.doc,.docx"
                                    onChange={(e) => setData('cv', e.target.files?.[0] ?? null)}
                                />
                                {persona.cv_ruta && (
                                    <Button variant="outline" size="sm" asChild className="shrink-0">
                                        <a href={`/storage/${persona.cv_ruta}`} target="_blank" rel="noopener noreferrer">
                                            <FileIcon className="size-4" />
                                            Ver CV
                                        </a>
                                    </Button>
                                )}
                            </div>
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

                    {/* Documentos */}
                    <div className="mt-8 border-t pt-6">
                        <h2 className="mb-4 text-lg font-semibold">Documentos</h2>

                        <div className="mb-4 space-y-3">
                            <div className="grid grid-cols-2 gap-2">
                                <Input
                                    value={docTipo}
                                    onChange={(e) => setDocTipo(e.target.value)}
                                    placeholder="Tipo de documento (ej: INE, CURP, Comprobante)"
                                />
                                <input
                                    ref={docInputRef}
                                    type="file"
                                    className="file-input file-input-bordered w-full"
                                    onChange={(e) => setDocFile(e.target.files?.[0] ?? null)}
                                />
                            </div>
                            <Button type="button" onClick={handleDocUpload} disabled={!docFile || !docTipo.trim() || docUploading}>
                                {docUploading ? <Loader2Icon className="size-4 animate-spin" /> : <UploadIcon className="size-4" />}
                                Subir Documento
                            </Button>
                        </div>

                        {(persona.documentos ?? []).length > 0 ? (
                            <ul className="space-y-2">
                                {(persona.documentos ?? []).map((doc) => (
                                    <li key={doc.id} className="flex items-center justify-between rounded border px-3 py-2">
                                        <div className="flex items-center gap-3">
                                            <FileIcon className="text-muted-foreground size-5" />
                                            <div>
                                                <a href={`/storage/${doc.ruta_archivo}`} target="_blank" rel="noopener noreferrer" className="text-primary font-medium underline">
                                                    {doc.nombre_archivo}
                                                </a>
                                                <div className="flex items-center gap-2 text-sm">
                                                    <Badge variant="outline">{doc.tipo_documento}</Badge>
                                                    {doc.extension && <Badge variant="secondary">{doc.extension}</Badge>}
                                                </div>
                                            </div>
                                        </div>
                                        <Button type="button" variant="ghost" size="icon" onClick={() => removeDocumento(doc.id)}>
                                            <TrashIcon className="size-4" />
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-muted-foreground text-sm">No hay documentos adjuntos.</p>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
