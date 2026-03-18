import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { RhPersona } from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { CameraIcon, FileIcon, FolderOpenIcon, Loader2Icon, PencilIcon, TrashIcon, UploadIcon, UserIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { useRef, useState } from 'react';

type Props = {
    persona: RhPersona;
};

export default function PersonaEdit({ persona }: Props) {
    const [activeTab, setActiveTab] = useState<'datos' | 'extras' | 'documentos'>('datos');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'RH', href: '/admin/rh/skills' },
        { title: 'Personas', href: '/admin/rh/personas' },
        { title: `${persona.nombre} ${persona.apellido}`, href: `/admin/rh/personas/${persona.id}` },
        { title: 'Editar', href: `/admin/rh/personas/${persona.id}/edit` },
    ];

    const extras = persona.datos_extra;

    const { data, setData, post, processing, errors } = useForm({
        _method: 'put' as const,
        nombre: persona.nombre,
        apellido: persona.apellido,
        email: persona.email ?? '',
        telefono: persona.telefono ?? '',
        fecha_nacimiento: persona.fecha_nacimiento ?? '',
        cv: null as File | null,
        foto: null as File | null,
        datos_extra: {
            imss: extras?.imss ?? '',
            curp: extras?.curp ?? '',
            rfc: extras?.rfc ?? '',
            estado_civil: extras?.estado_civil ?? '',
            hijos: extras?.hijos != null ? String(extras.hijos) : '',
            domicilio: extras?.domicilio ?? '',
            cp: extras?.cp ?? '',
            localidad: extras?.localidad ?? '',
            nombre_padre: extras?.nombre_padre ?? '',
            nombre_madre: extras?.nombre_madre ?? '',
            cuenta_banco: extras?.cuenta_banco ?? '',
            banco_op: extras?.banco_op ?? '',
            c_infonavit: extras?.c_infonavit ?? '',
            c_fonacot: extras?.c_fonacot ?? '',
        },
    });

    const [docTipo, setDocTipo] = useState('');
    const [docFile, setDocFile] = useState<File | null>(null);
    const [docUploading, setDocUploading] = useState(false);
    const docInputRef = useRef<HTMLInputElement>(null);

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(`/admin/rh/personas/${persona.id}`, { forceFormData: true });
    };

    const setExtra = (field: keyof typeof data.datos_extra, value: string) => {
        setData('datos_extra', { ...data.datos_extra, [field]: value });
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

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Editar Persona</h1>
                    <DeleteDialog
                        title="Eliminar persona"
                        description={`¿Estas seguro de eliminar a "${persona.nombre} ${persona.apellido}"? Esta accion no se puede deshacer.`}
                        deleteUrl={`/admin/rh/personas/${persona.id}`}
                    />
                </div>

                {/* Tabs */}
                <div className="tabs tabs-boxed">
                    <button
                        type="button"
                        className={`tab ${activeTab === 'datos' ? 'tab-active' : ''}`}
                        onClick={() => setActiveTab('datos')}
                    >
                        <PencilIcon className="mr-1 size-4" />
                        Datos
                    </button>
                    <button
                        type="button"
                        className={`tab ${activeTab === 'extras' ? 'tab-active' : ''}`}
                        onClick={() => setActiveTab('extras')}
                    >
                        <UserIcon className="mr-1 size-4" />
                        Datos Extra
                    </button>
                    <button
                        type="button"
                        className={`tab ${activeTab === 'documentos' ? 'tab-active' : ''}`}
                        onClick={() => setActiveTab('documentos')}
                    >
                        <FolderOpenIcon className="mr-1 size-4" />
                        Documentos
                    </button>
                </div>

                {/* Tab: Datos */}
                {activeTab === 'datos' && (
                    <div className="w-3/4">
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

                            <FormField label="Foto" htmlFor="foto" error={errors.foto}>
                                <div className="flex items-center gap-4">
                                    {persona.foto?.path ? (
                                        <img
                                            src={`/storage/${persona.foto.path}`}
                                            alt="Foto"
                                            className="size-20 rounded-md border object-cover"
                                        />
                                    ) : (
                                        <div className="bg-muted flex size-20 items-center justify-center rounded-md border">
                                            <CameraIcon className="text-muted-foreground size-8" />
                                        </div>
                                    )}
                                    <Input
                                        id="foto"
                                        type="file"
                                        accept="image/*"
                                        onChange={(e) => setData('foto', e.target.files?.[0] ?? null)}
                                    />
                                </div>
                            </FormField>

                            <FormField label="CV" htmlFor="cv" error={errors.cv}>
                                <div className="flex items-center gap-2">
                                    <Input
                                        id="cv"
                                        type="file"
                                        accept=".pdf,.doc,.docx"
                                        onChange={(e) => setData('cv', e.target.files?.[0] ?? null)}
                                    />
                                    {persona.media?.path && (
                                        <Button variant="outline" size="sm" asChild className="shrink-0">
                                            <a href={`/storage/${persona.media.path}`} target="_blank" rel="noopener noreferrer">
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
                    </div>
                )}

                {/* Tab: Datos Extra */}
                {activeTab === 'extras' && (
                    <div className="w-3/4">
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid grid-cols-3 gap-4">
                                <FormField label="IMSS" htmlFor="imss">
                                    <Input id="imss" value={data.datos_extra.imss} onChange={(e) => setExtra('imss', e.target.value)} placeholder="No. IMSS" />
                                </FormField>
                                <FormField label="CURP" htmlFor="curp">
                                    <Input id="curp" value={data.datos_extra.curp} onChange={(e) => setExtra('curp', e.target.value)} placeholder="CURP" maxLength={18} />
                                </FormField>
                                <FormField label="RFC" htmlFor="rfc">
                                    <Input id="rfc" value={data.datos_extra.rfc} onChange={(e) => setExtra('rfc', e.target.value)} placeholder="RFC con homoclave" maxLength={13} />
                                </FormField>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <FormField label="Estado Civil" htmlFor="estado_civil">
                                    <Select value={data.datos_extra.estado_civil} onValueChange={(v) => setExtra('estado_civil', v)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Seleccionar" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="soltero">Soltero(a)</SelectItem>
                                            <SelectItem value="casado">Casado(a)</SelectItem>
                                            <SelectItem value="union libre">Union Libre</SelectItem>
                                            <SelectItem value="divorciado">Divorciado(a)</SelectItem>
                                            <SelectItem value="viudo">Viudo(a)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </FormField>
                                <FormField label="Hijos" htmlFor="hijos">
                                    <Input id="hijos" type="number" min="0" value={data.datos_extra.hijos} onChange={(e) => setExtra('hijos', e.target.value)} placeholder="0" />
                                </FormField>
                            </div>

                            <div className="grid grid-cols-3 gap-4">
                                <FormField label="Domicilio" htmlFor="domicilio" className="col-span-2">
                                    <Input id="domicilio" value={data.datos_extra.domicilio} onChange={(e) => setExtra('domicilio', e.target.value)} placeholder="Calle, numero, colonia" />
                                </FormField>
                                <FormField label="Codigo Postal" htmlFor="cp">
                                    <Input id="cp" value={data.datos_extra.cp} onChange={(e) => setExtra('cp', e.target.value)} placeholder="C.P." maxLength={10} />
                                </FormField>
                            </div>

                            <FormField label="Localidad" htmlFor="localidad">
                                <Input id="localidad" value={data.datos_extra.localidad} onChange={(e) => setExtra('localidad', e.target.value)} placeholder="Ciudad / Localidad" />
                            </FormField>

                            <div className="grid grid-cols-2 gap-4">
                                <FormField label="Nombre del Padre" htmlFor="nombre_padre">
                                    <Input id="nombre_padre" value={data.datos_extra.nombre_padre} onChange={(e) => setExtra('nombre_padre', e.target.value)} placeholder="Nombre completo" />
                                </FormField>
                                <FormField label="Nombre de la Madre" htmlFor="nombre_madre">
                                    <Input id="nombre_madre" value={data.datos_extra.nombre_madre} onChange={(e) => setExtra('nombre_madre', e.target.value)} placeholder="Nombre completo" />
                                </FormField>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <FormField label="Cuenta Banco" htmlFor="cuenta_banco">
                                    <Input id="cuenta_banco" value={data.datos_extra.cuenta_banco} onChange={(e) => setExtra('cuenta_banco', e.target.value)} placeholder="No. de cuenta" />
                                </FormField>
                                <FormField label="Banco Operador" htmlFor="banco_op">
                                    <Input id="banco_op" value={data.datos_extra.banco_op} onChange={(e) => setExtra('banco_op', e.target.value)} placeholder="Nombre del banco" />
                                </FormField>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <FormField label="Credito Infonavit" htmlFor="c_infonavit">
                                    <Select value={data.datos_extra.c_infonavit} onValueChange={(v) => setExtra('c_infonavit', v)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Seleccionar" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="si">SI</SelectItem>
                                            <SelectItem value="no">NO</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </FormField>
                                <FormField label="Credito Fonacot" htmlFor="c_fonacot">
                                    <Select value={data.datos_extra.c_fonacot} onValueChange={(v) => setExtra('c_fonacot', v)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Seleccionar" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="si">SI</SelectItem>
                                            <SelectItem value="no">NO</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </FormField>
                            </div>

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
                )}

                {/* Tab: Documentos */}
                {activeTab === 'documentos' && (
                    <div className="w-3/4">
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
                                                <a href={`/storage/${doc.media?.path}`} target="_blank" rel="noopener noreferrer" className="text-primary font-medium underline">
                                                    {doc.media?.nombre_original ?? 'Documento'}
                                                </a>
                                                <div className="flex items-center gap-2 text-sm">
                                                    <Badge variant="outline">{doc.tipo_documento}</Badge>
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
                )}
            </div>
        </AppLayout>
    );
}
