import { Badge } from '@/components/ui/badge';
import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { SearchSelect } from '@/components/ui/search-select';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Media, RhOnboarding, RhOnboardingTarea, RhPeriodoLaboral } from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { BadgeCheckIcon, CheckIcon, ClipboardListIcon, CreditCardIcon, FileIcon, FileTextIcon, Loader2Icon, PencilIcon, PlusIcon, TrashIcon, UploadIcon, UserXIcon } from 'lucide-react';
import { useRef, useState, type FormEvent } from 'react';

type Props = {
    periodo: RhPeriodoLaboral & {
        onboarding?: RhOnboarding & {
            tareas?: (RhOnboardingTarea & {
                responsable?: RhPeriodoLaboral & { persona?: { nombre: string; apellido: string } };
                media?: Media | null;
            })[];
        };
    };
    personas: { id: number; nombre: string; apellido: string }[];
    puestos: { id: number; nombre: string }[];
    requisiciones: { id: number; folio: string; puesto_id: number }[];
    periodosActivos: { id: number; nombre: string }[];
};

export default function PeriodoLaboralEdit({ periodo, personas, puestos, requisiciones, periodosActivos }: Props) {
    const personaNombre = periodo.persona ? `${periodo.persona.nombre} ${periodo.persona.apellido}` : 'Periodo';
    const [activeTab, setActiveTab] = useState<'datos' | 'onboarding'>('datos');
    const [showBajaModal, setShowBajaModal] = useState(false);
    const [motivoBaja, setMotivoBaja] = useState('');
    const [bajaProcessing, setBajaProcessing] = useState(false);

    const handleBaja = () => {
        if (!motivoBaja.trim()) return;
        setBajaProcessing(true);
        router.post(`/admin/rh/periodos-laborales/${periodo.id}/terminar`, {
            motivo_baja: motivoBaja.trim(),
        }, {
            preserveScroll: true,
            onFinish: () => {
                setBajaProcessing(false);
                setShowBajaModal(false);
                setMotivoBaja('');
            },
        });
    };

    const handleDescargarContrato = (tipo: 'planta' | 'obra') => {
        const faltantes: string[] = [];
        const persona = periodo.persona;
        const extras = persona?.datos_extra;

        if (!persona?.nombre) faltantes.push('Nombre de la persona');
        if (!persona?.apellido) faltantes.push('Apellido de la persona');
        if (!periodo.fecha_inicio) faltantes.push('Fecha de inicio');
        if (!periodo.puesto) faltantes.push('Puesto');
        if (!periodo.salario_diario) faltantes.push('Salario Diario');
        if (!periodo.sueldo_mensual) faltantes.push('Sueldo Mensual');
        if (!extras?.curp) faltantes.push('CURP');
        if (!extras?.domicilio) faltantes.push('Domicilio');
        if (!extras?.cp) faltantes.push('Código Postal');
        if (!extras?.imss) faltantes.push('No. IMSS');
        if (!extras?.rfc) faltantes.push('RFC');
        if (!extras?.numero_ine) faltantes.push('Número de INE');
        if (!extras?.cuenta_banco) faltantes.push('Cuenta de banco');
        if (!extras?.estado_civil) faltantes.push('Estado civil');
        if (!persona?.fecha_nacimiento) faltantes.push('Fecha de nacimiento');

        if (faltantes.length > 0) {
            alert('Faltan los siguientes datos para generar el contrato:\n\n- ' + faltantes.join('\n- '));
            return;
        }

        window.open(`/admin/rh/periodos-laborales/${periodo.id}/contrato-pdf?tipo=${tipo}`, '_blank');
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'RH', href: '/admin/rh/skills' },
        { title: 'Periodos Laborales', href: '/admin/rh/periodos-laborales' },
        { title: personaNombre, href: `/admin/rh/periodos-laborales/${periodo.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        persona_id: String(periodo.persona_id),
        puesto_id: String(periodo.puesto_id),
        requisicion_id: periodo.requisicion_id ? String(periodo.requisicion_id) : '',
        fecha_inicio: periodo.fecha_inicio ?? '',
        fecha_fin: periodo.fecha_fin ?? '',
        salario_diario: periodo.salario_diario ? String(periodo.salario_diario) : '',
        sueldo_mensual: periodo.sueldo_mensual ? String(periodo.sueldo_mensual) : '',
        sueldo_real: periodo.sueldo_real ? String(periodo.sueldo_real) : '',
        periodicidad_pago: periodo.periodicidad_pago ?? '',
        tipo_salario: periodo.tipo_salario ?? '',
        tipo_contrato: periodo.tipo_contrato ?? '',
        numero_empleado: periodo.numero_empleado ?? '',
        tipo_empleado: periodo.tipo_empleado ?? '',
        estado: periodo.estado,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/rh/periodos-laborales/${periodo.id}`);
    };

    const onboarding = periodo.onboarding;
    const tareas = onboarding?.tareas ?? [];
    const totalTareas = tareas.length;
    const completadas = tareas.filter((t) => t.completada).length;
    const progreso = totalTareas > 0 ? Math.round((completadas / totalTareas) * 100) : 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar Periodo - ${personaNombre}`} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Editar Periodo Laboral</h1>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" type="button" onClick={() => handleDescargarContrato('planta')}>
                            <FileTextIcon className="size-4" />
                            Contrato Planta
                        </Button>
                        <Button variant="outline" type="button" onClick={() => handleDescargarContrato('obra')}>
                            <FileTextIcon className="size-4" />
                            Contrato Obra
                        </Button>
                        <Button
                            variant="outline"
                            type="button"
                            onClick={() => window.open(`/admin/rh/periodos-laborales/${periodo.id}/gafete-pdf`, '_blank')}
                        >
                            <BadgeCheckIcon className="size-4" />
                            Gafete
                        </Button>
                        <Button
                            variant="outline"
                            type="button"
                            onClick={() => window.open(`/admin/rh/periodos-laborales/${periodo.id}/tarjeta-pdf`, '_blank')}
                        >
                            <CreditCardIcon className="size-4" />
                            Tarjeta
                        </Button>
                        {periodo.estado === 'activo' && (
                            <Button variant="destructive" type="button" onClick={() => setShowBajaModal(true)}>
                                <UserXIcon className="size-4" />
                                Dar de Baja
                            </Button>
                        )}
                        <DeleteDialog
                            title="Eliminar periodo laboral"
                            description={`¿Estas seguro de eliminar este periodo laboral de "${personaNombre}"? Esta accion no se puede deshacer.`}
                            deleteUrl={`/admin/rh/periodos-laborales/${periodo.id}`}
                        />
                    </div>
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
                        className={`tab ${activeTab === 'onboarding' ? 'tab-active' : ''}`}
                        onClick={() => setActiveTab('onboarding')}
                    >
                        <ClipboardListIcon className="mr-1 size-4" />
                        Onboarding {onboarding ? `(${progreso}%)` : ''}
                    </button>
                </div>

                {/* Tab: Datos */}
                {activeTab === 'datos' && (
                    <div className="w-3/4">
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <FormField label="Persona" htmlFor="persona_id" error={errors.persona_id} required>
                                <SearchSelect
                                    options={personas.map((p) => ({ value: String(p.id), label: `${p.nombre} ${p.apellido}` }))}
                                    value={data.persona_id}
                                    onValueChange={(v) => setData('persona_id', v)}
                                    placeholder="Buscar persona..."
                                />
                            </FormField>

                            <FormField label="Puesto" htmlFor="puesto_id" error={errors.puesto_id} required>
                                <SearchSelect
                                    options={puestos.map((p) => ({ value: String(p.id), label: p.nombre }))}
                                    value={data.puesto_id}
                                    onValueChange={(v) => setData('puesto_id', v)}
                                    placeholder="Buscar puesto..."
                                />
                            </FormField>

                            <FormField label="Requisición" htmlFor="requisicion_id" error={errors.requisicion_id}>
                                <SearchSelect
                                    options={requisiciones.map((r) => ({ value: String(r.id), label: r.folio }))}
                                    value={data.requisicion_id}
                                    onValueChange={(v) => setData('requisicion_id', v)}
                                    placeholder="Buscar requisición..."
                                />
                            </FormField>

                            <div className="grid grid-cols-2 gap-4">
                                <FormField label="Fecha de Inicio" htmlFor="fecha_inicio" error={errors.fecha_inicio} required>
                                    <Input id="fecha_inicio" type="date" value={data.fecha_inicio} onChange={(e) => setData('fecha_inicio', e.target.value)} />
                                </FormField>

                                <FormField label="Fecha de Fin" htmlFor="fecha_fin" error={errors.fecha_fin}>
                                    <Input id="fecha_fin" type="date" value={data.fecha_fin} onChange={(e) => setData('fecha_fin', e.target.value)} />
                                </FormField>
                            </div>

                            <div className="grid grid-cols-3 gap-4">
                                <FormField label="Salario Diario" htmlFor="salario_diario" error={errors.salario_diario} required>
                                    <Input id="salario_diario" type="number" step="0.01" value={data.salario_diario} onChange={(e) => setData('salario_diario', e.target.value)} placeholder="0.00" />
                                </FormField>

                                <FormField label="Sueldo Mensual" htmlFor="sueldo_mensual" error={errors.sueldo_mensual} required>
                                    <Input id="sueldo_mensual" type="number" step="0.01" value={data.sueldo_mensual} onChange={(e) => setData('sueldo_mensual', e.target.value)} placeholder="0.00" />
                                </FormField>

                                <FormField label="Sueldo Real" htmlFor="sueldo_real" error={errors.sueldo_real}>
                                    <Input id="sueldo_real" type="number" step="0.01" value={data.sueldo_real} onChange={(e) => setData('sueldo_real', e.target.value)} placeholder="0.00" />
                                </FormField>
                            </div>

                            <div className="grid grid-cols-3 gap-4">
                                <FormField label="Periodicidad de Pago" htmlFor="periodicidad_pago" error={errors.periodicidad_pago}>
                                    <Select value={data.periodicidad_pago} onValueChange={(v) => setData('periodicidad_pago', v)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Seleccionar" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="semanal">Semanal</SelectItem>
                                            <SelectItem value="catorcenal">Catorcenal</SelectItem>
                                            <SelectItem value="quincenal">Quincenal</SelectItem>
                                            <SelectItem value="mensual">Mensual</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </FormField>

                                <FormField label="Tipo de Salario" htmlFor="tipo_salario" error={errors.tipo_salario}>
                                    <Select value={data.tipo_salario} onValueChange={(v) => setData('tipo_salario', v)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Seleccionar" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="fijo">Fijo</SelectItem>
                                            <SelectItem value="destajo">Destajo</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </FormField>

                                <FormField label="Tipo de Contrato" htmlFor="tipo_contrato" error={errors.tipo_contrato}>
                                    <Select value={data.tipo_contrato} onValueChange={(v) => setData('tipo_contrato', v)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Seleccionar" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="indefinido">Indefinido</SelectItem>
                                            <SelectItem value="temporal">Temporal</SelectItem>
                                            <SelectItem value="prueba">Prueba</SelectItem>
                                            <SelectItem value="obra_determinada">Obra determinada</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </FormField>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <FormField label="No. Empleado" htmlFor="numero_empleado" error={errors.numero_empleado}>
                                    <Input id="numero_empleado" value={data.numero_empleado} onChange={(e) => setData('numero_empleado', e.target.value)} placeholder="Ej: P-001" />
                                </FormField>

                                <FormField label="Tipo de Empleado" htmlFor="tipo_empleado" error={errors.tipo_empleado}>
                                    <Select value={data.tipo_empleado} onValueChange={(v) => setData('tipo_empleado', v)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Seleccionar" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="planta">Planta</SelectItem>
                                            <SelectItem value="contratista">Contratista</SelectItem>
                                            <SelectItem value="becario">Becario</SelectItem>
                                            <SelectItem value="foraneo">Foraneo</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </FormField>
                            </div>

                            <FormField label="Estado" htmlFor="estado" error={errors.estado} required>
                                <Select value={data.estado} onValueChange={(v) => setData('estado', v as 'activo' | 'baja')}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Seleccionar estado" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="activo">Activo</SelectItem>
                                        <SelectItem value="baja">Baja</SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <div className="flex justify-end gap-2">
                                <Button variant="outline" asChild>
                                    <Link href="/admin/rh/periodos-laborales">Cancelar</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Loader2Icon className="size-4 animate-spin" />}
                                    Guardar
                                </Button>
                            </div>
                        </form>
                    </div>
                )}

                {/* Tab: Onboarding */}
                {activeTab === 'onboarding' && (
                    <div className="w-3/4">
                        {!onboarding ? (
                            <div className="rounded border p-8 text-center">
                                <ClipboardListIcon className="text-muted-foreground mx-auto mb-3 size-12" />
                                <p className="text-muted-foreground mb-4">Este periodo laboral no tiene un proceso de onboarding.</p>
                                <Button
                                    onClick={() => router.post(`/admin/rh/periodos-laborales/${periodo.id}/onboarding`, {}, { preserveScroll: true })}
                                >
                                    <PlusIcon className="mr-1 size-4" />
                                    Iniciar Onboarding
                                </Button>
                            </div>
                        ) : (
                            <OnboardingPanel
                                onboarding={onboarding}
                                tareas={tareas}
                                progreso={progreso}
                                completadas={completadas}
                                totalTareas={totalTareas}
                                periodosActivos={periodosActivos}
                            />
                        )}
                    </div>
                )}
            </div>

            {/* Modal Dar de Baja */}
            <Dialog open={showBajaModal} onOpenChange={setShowBajaModal}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Dar de Baja - {personaNombre}</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4 py-2">
                        <p className="text-muted-foreground text-sm">
                            Se cambiara el estado a <strong>Baja</strong> y se registrara la fecha de hoy como fecha de fin.
                        </p>
                        {periodo.motivo_baja && (
                            <div className="rounded border bg-muted/50 p-3 text-sm">
                                <span className="font-medium">Motivo anterior:</span> {periodo.motivo_baja}
                            </div>
                        )}
                        <FormField label="Motivo de baja" htmlFor="motivo_baja" required>
                            <textarea
                                id="motivo_baja"
                                className="textarea textarea-bordered w-full"
                                value={motivoBaja}
                                onChange={(e) => setMotivoBaja(e.target.value)}
                                placeholder="Describa el motivo de la baja..."
                                rows={4}
                            />
                        </FormField>
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Cancelar</Button>
                        </DialogClose>
                        <Button variant="destructive" onClick={handleBaja} disabled={!motivoBaja.trim() || bajaProcessing}>
                            {bajaProcessing && <Loader2Icon className="size-4 animate-spin" />}
                            Confirmar Baja
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

function OnboardingPanel({
    onboarding,
    tareas,
    progreso,
    completadas,
    totalTareas,
    periodosActivos,
}: {
    onboarding: RhOnboarding;
    tareas: (RhOnboardingTarea & { responsable?: RhPeriodoLaboral & { persona?: { nombre: string; apellido: string } }; media?: Media | null })[];
    progreso: number;
    completadas: number;
    totalTareas: number;
    periodosActivos: { id: number; nombre: string }[];
}) {
    const [newTareaTitle, setNewTareaTitle] = useState('');
    const [newTareaDesc, setNewTareaDesc] = useState('');
    const [newTareaFecha, setNewTareaFecha] = useState('');
    const [newTareaResponsable, setNewTareaResponsable] = useState('');

    const addTarea = () => {
        if (!newTareaTitle.trim()) return;
        router.post(`/admin/rh/onboarding/${onboarding.id}/tareas`, {
            titulo: newTareaTitle.trim(),
            descripcion: newTareaDesc.trim() || undefined,
            fecha_vencimiento: newTareaFecha || undefined,
            responsable_periodo_id: newTareaResponsable || undefined,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setNewTareaTitle('');
                setNewTareaDesc('');
                setNewTareaFecha('');
                setNewTareaResponsable('');
            },
        });
    };

    const toggleTarea = (tareaId: number) => {
        router.post(`/admin/rh/onboarding/${onboarding.id}/tareas/${tareaId}/toggle`, {}, { preserveScroll: true });
    };

    const deleteTarea = (tareaId: number) => {
        router.delete(`/admin/rh/onboarding/${onboarding.id}/tareas/${tareaId}`, { preserveScroll: true });
    };

    return (
        <div className="space-y-6">
            {/* Progreso */}
            <div className="rounded border p-4">
                <div className="mb-2 flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Progreso</h2>
                    <span className="text-muted-foreground text-sm">{completadas}/{totalTareas} tareas completadas</span>
                </div>
                <div className="h-3 w-full overflow-hidden rounded-full bg-gray-200">
                    <div
                        className="h-full rounded-full bg-green-500 transition-all duration-300"
                        style={{ width: `${progreso}%` }}
                    />
                </div>
                <p className="text-muted-foreground mt-1 text-sm">{progreso}% completado</p>
            </div>

            {/* Agregar tarea */}
            <div className="rounded border p-4">
                <h2 className="mb-4 text-lg font-semibold">Tareas</h2>

                <div className="mb-4 space-y-2">
                    <div className="flex gap-2">
                        <Input
                            value={newTareaTitle}
                            onChange={(e) => setNewTareaTitle(e.target.value)}
                            placeholder="Titulo de la tarea"
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    addTarea();
                                }
                            }}
                        />
                        <Input
                            value={newTareaDesc}
                            onChange={(e) => setNewTareaDesc(e.target.value)}
                            placeholder="Descripcion (opcional)"
                        />
                    </div>
                    <div className="flex gap-2">
                        <Input
                            type="date"
                            value={newTareaFecha}
                            onChange={(e) => setNewTareaFecha(e.target.value)}
                            className="w-44 shrink-0"
                        />
                        <Select value={newTareaResponsable} onValueChange={setNewTareaResponsable}>
                            <SelectTrigger className="flex-1">
                                <SelectValue placeholder="Responsable (opcional)" />
                            </SelectTrigger>
                            <SelectContent>
                                {periodosActivos.map((p) => (
                                    <SelectItem key={p.id} value={String(p.id)}>
                                        {p.nombre}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button type="button" variant="outline" onClick={addTarea} disabled={!newTareaTitle.trim()} className="shrink-0">
                            <PlusIcon className="size-4" />
                            Agregar
                        </Button>
                    </div>
                </div>

                {tareas.length > 0 ? (
                    <ul className="space-y-2">
                        {tareas.map((tarea) => (
                            <TareaItem
                                key={tarea.id}
                                tarea={tarea}
                                onboardingId={onboarding.id}
                                onToggle={() => toggleTarea(tarea.id)}
                                onDelete={() => deleteTarea(tarea.id)}
                            />
                        ))}
                    </ul>
                ) : (
                    <p className="text-muted-foreground text-sm">No hay tareas de onboarding registradas.</p>
                )}
            </div>
        </div>
    );
}

function TareaItem({ tarea, onboardingId, onToggle, onDelete }: {
    tarea: RhOnboardingTarea & { responsable?: RhPeriodoLaboral & { persona?: { nombre: string; apellido: string } }; media?: Media | null };
    onboardingId: number;
    onToggle: () => void;
    onDelete: () => void;
}) {
    const fileInputRef = useRef<HTMLInputElement>(null);

    const subirEvidencia = (file: File) => {
        const formData = new FormData();
        formData.append('evidencia', file);
        router.post(`/admin/rh/onboarding/${onboardingId}/tareas/${tarea.id}/evidencia`, formData, {
            preserveScroll: true,
        });
    };

    return (
        <li className="rounded border px-3 py-2">
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <button
                        type="button"
                        onClick={onToggle}
                        className={`flex size-5 shrink-0 items-center justify-center rounded border ${
                            tarea.completada ? 'border-green-500 bg-green-500 text-white' : 'border-gray-300'
                        }`}
                    >
                        {tarea.completada && <CheckIcon className="size-3" />}
                    </button>
                    <div>
                        <span className={tarea.completada ? 'text-muted-foreground line-through' : ''}>
                            {tarea.titulo}
                        </span>
                        {tarea.descripcion && (
                            <p className="text-muted-foreground text-sm">{tarea.descripcion}</p>
                        )}
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    {tarea.responsable?.persona && (
                        <Badge variant="outline">
                            {tarea.responsable.persona.nombre} {tarea.responsable.persona.apellido}
                        </Badge>
                    )}
                    {tarea.fecha_vencimiento && (
                        <span className="text-muted-foreground text-xs">Vence: {tarea.fecha_vencimiento}</span>
                    )}
                    <Badge variant={tarea.completada ? 'default' : 'secondary'}>
                        {tarea.completada ? 'Realizado' : 'Pendiente'}
                    </Badge>
                    {tarea.media?.path ? (
                        <Button variant="outline" size="sm" asChild>
                            <a href={`/storage/${tarea.media.path}`} target="_blank" rel="noopener noreferrer">
                                <FileIcon className="size-4" />
                                Evidencia
                            </a>
                        </Button>
                    ) : (
                        <>
                            <input
                                ref={fileInputRef}
                                type="file"
                                className="hidden"
                                onChange={(e) => {
                                    const file = e.target.files?.[0];
                                    if (file) subirEvidencia(file);
                                }}
                            />
                            <Button variant="ghost" size="sm" onClick={() => fileInputRef.current?.click()}>
                                <UploadIcon className="size-4" />
                                Evidencia
                            </Button>
                        </>
                    )}
                    <Button type="button" variant="ghost" size="icon" onClick={onDelete}>
                        <TrashIcon className="size-4" />
                    </Button>
                </div>
            </div>
        </li>
    );
}
