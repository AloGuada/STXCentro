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

    const datosForm = useForm({
        _method: 'put' as const,
        nombre: persona.nombre,
        apellido: persona.apellido,
        email: persona.email ?? '',
        telefono: persona.telefono ?? '',
        fecha_nacimiento: persona.fecha_nacimiento ? String(persona.fecha_nacimiento).slice(0, 10) : '',
        cv: null as File | null,
        foto: null as File | null,
    });

    const extrasForm = useForm({
        _method: 'put' as const,
        imss: extras?.imss ?? '',
        curp: extras?.curp ?? '',
        rfc: extras?.rfc ?? '',
        numero_ine: extras?.numero_ine ?? '',
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
    });

    const contactoForm = useForm({
        nombre: '',
        telefono: '',
    });

    const [docTipo, setDocTipo] = useState('');
    const [docFile, setDocFile] = useState<File | null>(null);
    const [docUploading, setDocUploading] = useState(false);
    const docInputRef = useRef<HTMLInputElement>(null);

    const handleDatosSubmit = (e: FormEvent) => {
        e.preventDefault();
        datosForm.post(`/admin/rh/personas/${persona.id}`, { forceFormData: true, preserveScroll: true });
    };

    const handleExtrasSubmit = (e: FormEvent) => {
        e.preventDefault();
        extrasForm.put(`/admin/rh/personas/${persona.id}/datos-extra`, { preserveScroll: true });
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
                        <form onSubmit={handleDatosSubmit} className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <FormField label="Nombre" htmlFor="nombre" error={datosForm.errors.nombre} required>
                                    <Input id="nombre" value={datosForm.data.nombre} onChange={(e) => datosForm.setData('nombre', e.target.value)} placeholder="Nombre" />
                                </FormField>

                                <FormField label="Apellido" htmlFor="apellido" error={datosForm.errors.apellido} required>
                                    <Input id="apellido" value={datosForm.data.apellido} onChange={(e) => datosForm.setData('apellido', e.target.value)} placeholder="Apellido" />
                                </FormField>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <FormField label="Email" htmlFor="email" error={datosForm.errors.email}>
                                    <Input id="email" type="email" value={datosForm.data.email} onChange={(e) => datosForm.setData('email', e.target.value)} placeholder="correo@ejemplo.com" />
                                </FormField>

                                <FormField label="Telefono" htmlFor="telefono" error={datosForm.errors.telefono}>
                                    <Input id="telefono" value={datosForm.data.telefono} onChange={(e) => datosForm.setData('telefono', e.target.value)} placeholder="Telefono" />
                                </FormField>
                            </div>

                            <FormField label="Fecha de Nacimiento" htmlFor="fecha_nacimiento" error={datosForm.errors.fecha_nacimiento}>
                                <Input id="fecha_nacimiento" type="date" value={datosForm.data.fecha_nacimiento} onChange={(e) => datosForm.setData('fecha_nacimiento', e.target.value)} />
                            </FormField>

                            <FormField label="Foto" htmlFor="foto" error={datosForm.errors.foto}>
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
                                        onChange={(e) => datosForm.setData('foto', e.target.files?.[0] ?? null)}
                                    />
                                </div>
                            </FormField>

                            <FormField label="CV" htmlFor="cv" error={datosForm.errors.cv}>
                                <div className="flex items-center gap-2">
                                    <Input
                                        id="cv"
                                        type="file"
                                        accept=".pdf,.doc,.docx"
                                        onChange={(e) => datosForm.setData('cv', e.target.files?.[0] ?? null)}
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
                                <Button type="submit" disabled={datosForm.processing}>
                                    {datosForm.processing && <Loader2Icon className="size-4 animate-spin" />}
                                    Guardar
                                </Button>
                            </div>
                        </form>
                    </div>
                )}

                {/* Tab: Datos Extra */}
                {activeTab === 'extras' && (
                    <div className="w-3/4">
                        <form onSubmit={handleExtrasSubmit} className="space-y-4">
                            <div className="grid grid-cols-3 gap-4">
                                <FormField label="IMSS" htmlFor="imss" error={extrasForm.errors.imss}>
                                    <Input id="imss" value={extrasForm.data.imss} onChange={(e) => extrasForm.setData('imss', e.target.value)} placeholder="No. IMSS" />
                                </FormField>
                                <FormField label="CURP" htmlFor="curp" error={extrasForm.errors.curp}>
                                    <Input id="curp" value={extrasForm.data.curp} onChange={(e) => extrasForm.setData('curp', e.target.value)} placeholder="CURP" />
                                </FormField>
                                <FormField label="RFC" htmlFor="rfc" error={extrasForm.errors.rfc}>
                                    <Input id="rfc" value={extrasForm.data.rfc} onChange={(e) => extrasForm.setData('rfc', e.target.value)} placeholder="RFC con homoclave" />
                                </FormField>
                            </div>

                            <div className="grid grid-cols-3 gap-4">
                                <FormField label="No. INE" htmlFor="numero_ine" error={extrasForm.errors.numero_ine}>
                                    <Input id="numero_ine" value={extrasForm.data.numero_ine} onChange={(e) => extrasForm.setData('numero_ine', e.target.value)} placeholder="Número de INE" />
                                </FormField>
                                <FormField label="Estado Civil" htmlFor="estado_civil" error={extrasForm.errors.estado_civil}>
                                    <Select value={extrasForm.data.estado_civil} onValueChange={(v) => extrasForm.setData('estado_civil', v)}>
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
                                <FormField label="Hijos" htmlFor="hijos" error={extrasForm.errors.hijos}>
                                    <Input id="hijos" type="number" min="0" value={extrasForm.data.hijos} onChange={(e) => extrasForm.setData('hijos', e.target.value)} placeholder="0" />
                                </FormField>
                            </div>

                            <div className="grid grid-cols-3 gap-4">
                                <FormField label="Domicilio" htmlFor="domicilio" className="col-span-2" error={extrasForm.errors.domicilio}>
                                    <Input id="domicilio" value={extrasForm.data.domicilio} onChange={(e) => extrasForm.setData('domicilio', e.target.value)} placeholder="Calle, numero, colonia" />
                                </FormField>
                                <FormField label="Codigo Postal" htmlFor="cp" error={extrasForm.errors.cp}>
                                    <Input id="cp" value={extrasForm.data.cp} onChange={(e) => extrasForm.setData('cp', e.target.value)} placeholder="C.P." />
                                </FormField>
                            </div>

                            <FormField label="Localidad" htmlFor="localidad" error={extrasForm.errors.localidad}>
                                <Input id="localidad" value={extrasForm.data.localidad} onChange={(e) => extrasForm.setData('localidad', e.target.value)} placeholder="Ciudad / Localidad" />
                            </FormField>

                            <div className="grid grid-cols-2 gap-4">
                                <FormField label="Nombre del Padre" htmlFor="nombre_padre" error={extrasForm.errors.nombre_padre}>
                                    <Input id="nombre_padre" value={extrasForm.data.nombre_padre} onChange={(e) => extrasForm.setData('nombre_padre', e.target.value)} placeholder="Nombre completo" />
                                </FormField>
                                <FormField label="Nombre de la Madre" htmlFor="nombre_madre" error={extrasForm.errors.nombre_madre}>
                                    <Input id="nombre_madre" value={extrasForm.data.nombre_madre} onChange={(e) => extrasForm.setData('nombre_madre', e.target.value)} placeholder="Nombre completo" />
                                </FormField>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <FormField label="Cuenta Banco" htmlFor="cuenta_banco" error={extrasForm.errors.cuenta_banco}>
                                    <Input id="cuenta_banco" value={extrasForm.data.cuenta_banco} onChange={(e) => extrasForm.setData('cuenta_banco', e.target.value)} placeholder="No. de cuenta" />
                                </FormField>
                                <FormField label="Banco Operador" htmlFor="banco_op" error={extrasForm.errors.banco_op}>
                                    <Input id="banco_op" value={extrasForm.data.banco_op} onChange={(e) => extrasForm.setData('banco_op', e.target.value)} placeholder="Nombre del banco" />
                                </FormField>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <FormField label="Credito Infonavit" htmlFor="c_infonavit" error={extrasForm.errors.c_infonavit}>
                                    <Select value={extrasForm.data.c_infonavit} onValueChange={(v) => extrasForm.setData('c_infonavit', v)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Seleccionar" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="si">SI</SelectItem>
                                            <SelectItem value="no">NO</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </FormField>
                                <FormField label="Credito Fonacot" htmlFor="c_fonacot" error={extrasForm.errors.c_fonacot}>
                                    <Select value={extrasForm.data.c_fonacot} onValueChange={(v) => extrasForm.setData('c_fonacot', v)}>
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
                                <Button type="submit" disabled={extrasForm.processing}>
                                    {extrasForm.processing && <Loader2Icon className="size-4 animate-spin" />}
                                    Guardar
                                </Button>
                            </div>
                        </form>

                        {/* Contactos de Emergencia */}
                        <div className="mt-8 border-t pt-6">
                            <h3 className="mb-4 text-lg font-semibold">Contactos de Emergencia</h3>

                            {(persona.contactos_emergencia ?? []).length > 0 && (
                                <div className="mb-4 space-y-2">
                                    {persona.contactos_emergencia!.map((contacto) => (
                                        <div key={contacto.id} className="flex items-center justify-between rounded border p-3">
                                            <div>
                                                <span className="font-medium">{contacto.nombre}</span>
                                                <span className="ml-2 text-muted-foreground">{contacto.telefono}</span>
                                            </div>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => {
                                                    if (confirm('¿Eliminar este contacto de emergencia?')) {
                                                        router.delete(`/admin/rh/personas/${persona.id}/contactos-emergencia/${contacto.id}`, { preserveScroll: true });
                                                    }
                                                }}
                                            >
                                                <TrashIcon className="size-4 text-destructive" />
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            )}

                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    contactoForm.post(`/admin/rh/personas/${persona.id}/contactos-emergencia`, {
                                        preserveScroll: true,
                                        onSuccess: () => contactoForm.reset(),
                                    });
                                }}
                                className="space-y-3"
                            >
                                <div className="grid grid-cols-2 gap-4">
                                    <FormField label="Nombre" htmlFor="contacto_nombre" error={contactoForm.errors.nombre}>
                                        <Input
                                            id="contacto_nombre"
                                            value={contactoForm.data.nombre}
                                            onChange={(e) => contactoForm.setData('nombre', e.target.value)}
                                            placeholder="Nombre del contacto"
                                        />
                                    </FormField>
                                    <FormField label="Teléfono" htmlFor="contacto_telefono" error={contactoForm.errors.telefono}>
                                        <Input
                                            id="contacto_telefono"
                                            value={contactoForm.data.telefono}
                                            onChange={(e) => contactoForm.setData('telefono', e.target.value)}
                                            placeholder="Número de teléfono"
                                        />
                                    </FormField>
                                </div>
                                <Button type="submit" disabled={contactoForm.processing || !contactoForm.data.nombre.trim() || !contactoForm.data.telefono.trim()}>
                                    {contactoForm.processing && <Loader2Icon className="size-4 animate-spin" />}
                                    Agregar contacto
                                </Button>
                            </form>
                        </div>
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
