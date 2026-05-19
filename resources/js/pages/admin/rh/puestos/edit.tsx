import { CreatableCombobox } from '@/components/ui/creatable-combobox';
import { DeleteDialog } from '@/components/delete-dialog';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { FormField } from '@/components/form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Departamento, RhActividad, RhDocumentoPuesto, RhPuesto, RhRequerimiento, RhSkill } from '@/types/models';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { BriefcaseIcon, ClipboardListIcon, FileTextIcon, ListChecksIcon, Loader2Icon, PencilIcon, PlusIcon, SparklesIcon, TrashIcon, XIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';

type Props = {
    puesto: RhPuesto;
    departamentos: Departamento[];
    puestosJefe: { id: number; nombre: string }[];
    allSkills: RhSkill[];
    allRequerimientos: RhRequerimiento[];
};

type TabKey = 'datos' | 'skills' | 'requerimientos' | 'actividades' | 'plantilla' | 'documentos';

export default function PuestoEdit({ puesto, departamentos, puestosJefe, allSkills, allRequerimientos }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'RH', href: '/admin/rh/puestos' },
        { title: 'Puestos', href: '/admin/rh/puestos' },
        { title: puesto.nombre, href: `/admin/rh/puestos/${puesto.id}/edit` },
    ];

    const [activeTab, setActiveTab] = useState<TabKey>('datos');

    const { data, setData, put, processing, errors } = useForm({
        nombre: puesto.nombre,
        departamento_id: String(puesto.departamento_id),
        descripcion: puesto.descripcion ?? '',
        codigo: puesto.codigo ?? '',
        ubicacion: puesto.ubicacion ?? '',
        hora_entrada: puesto.hora_entrada ?? '',
        hora_salida: puesto.hora_salida ?? '',
        puesto_jefe_id: puesto.puesto_jefe_id ? String(puesto.puesto_jefe_id) : '',
    });

    const [skillNivel, setSkillNivel] = useState('basico');
    const [skillTipo, setSkillTipo] = useState('hard');
    const [reqValor, setReqValor] = useState('');
    const [newActividad, setNewActividad] = useState('');
    const [newDocNombre, setNewDocNombre] = useState('');
    const [newDocFrecuencia, setNewDocFrecuencia] = useState('');
    const [newDocCargo, setNewDocCargo] = useState('');
    const [newTplTitulo, setNewTplTitulo] = useState('');
    const [newTplDescripcion, setNewTplDescripcion] = useState('');
    const [newTplDias, setNewTplDias] = useState('');

    // Estado para el Dialog "Crear nueva skill"
    const [skillDialogOpen, setSkillDialogOpen] = useState(false);
    const [newSkillNombre, setNewSkillNombre] = useState('');
    const [newSkillTipo, setNewSkillTipo] = useState('hard');
    const [newSkillNivel, setNewSkillNivel] = useState('basico');

    // Estado para el Dialog "Crear nuevo requerimiento"
    const [reqDialogOpen, setReqDialogOpen] = useState(false);
    const [newReqDescripcion, setNewReqDescripcion] = useState('');
    const [newReqValor, setNewReqValor] = useState('');

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/rh/puestos/${puesto.id}`);
    };

    const assignedSkillIds = (puesto.skills ?? []).map((s) => s.id);
    const availableSkills = allSkills.filter((s) => !assignedSkillIds.includes(s.id));
    const skillOptions = availableSkills.map((s) => ({ value: String(s.id), label: s.nombre }));

    const assignedRequerimientoIds = (puesto.requerimientos ?? []).map((r) => r.id);
    const availableRequerimientos = allRequerimientos.filter((r) => !assignedRequerimientoIds.includes(r.id));
    const requerimientoOptions = availableRequerimientos.map((r) => ({ value: String(r.id), label: r.descripcion }));

    const handleSkillSelect = (option: { value: string }) => {
        router.post(`/admin/rh/puestos/${puesto.id}/skills`, {
            skill_id: option.value,
            nivel_requerido: skillNivel,
        }, { preserveScroll: true });
    };

    const removeSkill = (skillId: number) => {
        router.delete(`/admin/rh/puestos/${puesto.id}/skills/${skillId}`, { preserveScroll: true });
    };

    const submitNewSkill = () => {
        if (!newSkillNombre.trim()) return;
        router.post(`/admin/rh/puestos/${puesto.id}/skills`, {
            nombre: newSkillNombre.trim(),
            tipo: newSkillTipo,
            nivel_requerido: newSkillNivel,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setNewSkillNombre('');
                setNewSkillTipo('hard');
                setNewSkillNivel('basico');
                setSkillDialogOpen(false);
            },
        });
    };

    const handleRequerimientoSelect = (option: { value: string }) => {
        router.post(`/admin/rh/puestos/${puesto.id}/requerimientos`, {
            requerimiento_id: option.value,
            valor: reqValor || undefined,
        }, { preserveScroll: true, onSuccess: () => setReqValor('') });
    };

    const removeRequerimiento = (requerimientoId: number) => {
        router.delete(`/admin/rh/puestos/${puesto.id}/requerimientos/${requerimientoId}`, { preserveScroll: true });
    };

    const submitNewRequerimiento = () => {
        if (!newReqDescripcion.trim()) return;
        router.post(`/admin/rh/puestos/${puesto.id}/requerimientos`, {
            descripcion: newReqDescripcion.trim(),
            valor: newReqValor.trim() || undefined,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setNewReqDescripcion('');
                setNewReqValor('');
                setReqDialogOpen(false);
            },
        });
    };

    const addActividad = () => {
        if (!newActividad.trim()) return;
        router.post(`/admin/rh/puestos/${puesto.id}/actividades`, {
            descripcion: newActividad.trim(),
        }, { preserveScroll: true });
        setNewActividad('');
    };

    const removeActividad = (actividadId: number) => {
        router.delete(`/admin/rh/puestos/${puesto.id}/actividades/${actividadId}`, { preserveScroll: true });
    };

    const addPlantillaOnboarding = () => {
        if (!newTplTitulo.trim()) return;
        router.post(`/admin/rh/puestos/${puesto.id}/plantilla-onboarding`, {
            titulo: newTplTitulo.trim(),
            descripcion: newTplDescripcion.trim() || null,
            dias_desde_inicio: newTplDias === '' ? null : Number(newTplDias),
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setNewTplTitulo('');
                setNewTplDescripcion('');
                setNewTplDias('');
            },
        });
    };

    const removePlantillaOnboarding = (plantillaId: number) => {
        router.delete(`/admin/rh/puestos/${puesto.id}/plantilla-onboarding/${plantillaId}`, { preserveScroll: true });
    };

    const addDocumentoPuesto = () => {
        if (!newDocNombre.trim()) return;
        router.post(`/admin/rh/puestos/${puesto.id}/documentos-puesto`, {
            nombre_reporte: newDocNombre.trim(),
            frecuencia_entrega: newDocFrecuencia || null,
            cargo_entrega: newDocCargo || null,
        }, { preserveScroll: true });
        setNewDocNombre('');
        setNewDocFrecuencia('');
        setNewDocCargo('');
    };

    const removeDocumentoPuesto = (docId: number) => {
        router.delete(`/admin/rh/puestos/${puesto.id}/documentos-puesto/${docId}`, { preserveScroll: true });
    };

    const tabs: { key: TabKey; label: string; icon: typeof PencilIcon; count?: number }[] = [
        { key: 'datos', label: 'Datos', icon: PencilIcon },
        { key: 'skills', label: 'Skills', icon: SparklesIcon, count: (puesto.skills ?? []).length },
        { key: 'requerimientos', label: 'Requerimientos', icon: ListChecksIcon, count: (puesto.requerimientos ?? []).length },
        { key: 'actividades', label: 'Actividades', icon: BriefcaseIcon, count: (puesto.actividades ?? []).length },
        { key: 'plantilla', label: 'Plantilla Onboarding', icon: ClipboardListIcon, count: (puesto.plantillas_onboarding ?? []).length },
        { key: 'documentos', label: 'Documentos', icon: FileTextIcon, count: (puesto.documentos_puesto ?? []).length },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${puesto.nombre}`} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Editar Puesto: {puesto.nombre}</h1>
                    <DeleteDialog
                        title="Eliminar puesto"
                        description={`¿Estas seguro de eliminar el puesto "${puesto.nombre}"? Esta accion no se puede deshacer.`}
                        deleteUrl={`/admin/rh/puestos/${puesto.id}`}
                    />
                </div>

                {/* Tabs */}
                <div className="tabs tabs-boxed">
                    {tabs.map((t) => {
                        const Icon = t.icon;
                        return (
                            <button
                                key={t.key}
                                type="button"
                                className={`tab ${activeTab === t.key ? 'tab-active' : ''}`}
                                onClick={() => setActiveTab(t.key)}
                            >
                                <Icon className="mr-1 size-4" />
                                {t.label}
                                {t.count !== undefined && t.count > 0 && (
                                    <Badge variant="secondary" className="ml-2">{t.count}</Badge>
                                )}
                            </button>
                        );
                    })}
                </div>

                {/* Tab: Datos */}
                {activeTab === 'datos' && (
                    <div className="w-3/4">
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <FormField label="Nombre" htmlFor="nombre" error={errors.nombre} required>
                                <Input id="nombre" value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} placeholder="Nombre del puesto" />
                            </FormField>

                            <FormField label="Departamento" htmlFor="departamento_id" error={errors.departamento_id} required>
                                <Select value={data.departamento_id} onValueChange={(v) => setData('departamento_id', v)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Seleccionar departamento" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {departamentos.map((dep) => (
                                            <SelectItem key={dep.id} value={String(dep.id)}>
                                                {dep.descripcion}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion}>
                                <textarea id="descripcion" className="textarea textarea-bordered w-full" value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} placeholder="Descripcion del puesto" />
                            </FormField>

                            <div className="grid grid-cols-2 gap-4">
                                <FormField label="Codigo" htmlFor="codigo" error={errors.codigo}>
                                    <Input id="codigo" value={data.codigo} onChange={(e) => setData('codigo', e.target.value)} placeholder="Codigo del puesto" />
                                </FormField>

                                <FormField label="Ubicacion" htmlFor="ubicacion" error={errors.ubicacion}>
                                    <Input id="ubicacion" value={data.ubicacion} onChange={(e) => setData('ubicacion', e.target.value)} placeholder="Ubicacion" />
                                </FormField>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <FormField label="Hora de Entrada" htmlFor="hora_entrada" error={errors.hora_entrada}>
                                    <Input id="hora_entrada" type="time" value={data.hora_entrada} onChange={(e) => setData('hora_entrada', e.target.value)} />
                                </FormField>

                                <FormField label="Hora de Salida" htmlFor="hora_salida" error={errors.hora_salida}>
                                    <Input id="hora_salida" type="time" value={data.hora_salida} onChange={(e) => setData('hora_salida', e.target.value)} />
                                </FormField>
                            </div>

                            <FormField label="Puesto Jefe" htmlFor="puesto_jefe_id" error={errors.puesto_jefe_id}>
                                <Select value={data.puesto_jefe_id} onValueChange={(v) => setData('puesto_jefe_id', v)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Seleccionar puesto jefe (opcional)" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {puestosJefe.map((p) => (
                                            <SelectItem key={p.id} value={String(p.id)}>
                                                {p.nombre}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <div className="flex justify-end gap-2">
                                <Button variant="outline" asChild>
                                    <Link href="/admin/rh/puestos">Cancelar</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Loader2Icon className="size-4 animate-spin" />}
                                    Guardar
                                </Button>
                            </div>
                        </form>
                    </div>
                )}

                {/* Tab: Skills */}
                {activeTab === 'skills' && (
                    <div className="w-3/4 space-y-4">
                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-sm">Busca y asigna skills al puesto, o crea una nueva.</p>
                            <Dialog open={skillDialogOpen} onOpenChange={setSkillDialogOpen}>
                                <DialogTrigger asChild>
                                    <Button type="button">
                                        <PlusIcon className="mr-1 size-4" />
                                        Crear nueva skill
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Crear nueva skill</DialogTitle>
                                    </DialogHeader>
                                    <div className="space-y-3">
                                        <FormField label="Nombre" htmlFor="new_skill_nombre" required>
                                            <Input
                                                id="new_skill_nombre"
                                                value={newSkillNombre}
                                                onChange={(e) => setNewSkillNombre(e.target.value)}
                                                placeholder="Ej. Manejo de SAP"
                                                autoFocus
                                            />
                                        </FormField>
                                        <FormField label="Tipo" htmlFor="new_skill_tipo" required>
                                            <Select value={newSkillTipo} onValueChange={setNewSkillTipo}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="hard">Hard skill (técnica)</SelectItem>
                                                    <SelectItem value="soft">Soft skill (interpersonal)</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </FormField>
                                        <FormField label="Nivel requerido" htmlFor="new_skill_nivel" required>
                                            <Select value={newSkillNivel} onValueChange={setNewSkillNivel}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="basico">Básico</SelectItem>
                                                    <SelectItem value="intermedio">Intermedio</SelectItem>
                                                    <SelectItem value="avanzado">Avanzado</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </FormField>
                                    </div>
                                    <DialogFooter>
                                        <DialogClose asChild>
                                            <Button variant="outline" type="button">Cancelar</Button>
                                        </DialogClose>
                                        <Button type="button" onClick={submitNewSkill} disabled={!newSkillNombre.trim()}>
                                            Crear y asignar
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </div>

                        <div className="flex gap-2">
                            <CreatableCombobox
                                options={skillOptions}
                                placeholder="Buscar skill existente..."
                                creatableLabel="Crear skill"
                                onSelect={handleSkillSelect}
                                onCreate={(nombre) => {
                                    setNewSkillNombre(nombre);
                                    setSkillDialogOpen(true);
                                }}
                                className="w-full flex-1"
                            />
                            <Select value={skillNivel} onValueChange={setSkillNivel} className="w-40 shrink-0">
                                <SelectItem value="basico">Básico</SelectItem>
                                <SelectItem value="intermedio">Intermedio</SelectItem>
                                <SelectItem value="avanzado">Avanzado</SelectItem>
                            </Select>
                        </div>

                        {(puesto.skills ?? []).length > 0 ? (
                            <ul className="space-y-2">
                                {(puesto.skills ?? []).map((skill) => (
                                    <li key={skill.id} className="flex items-center justify-between rounded border px-3 py-2">
                                        <div className="flex items-center gap-2">
                                            <span>{skill.nombre}</span>
                                            <Badge variant={skill.tipo === 'hard' ? 'default' : 'secondary'}>{skill.tipo}</Badge>
                                            {skill.pivot?.nivel_requerido && (
                                                <Badge variant="outline">{skill.pivot.nivel_requerido}</Badge>
                                            )}
                                        </div>
                                        <Button type="button" variant="ghost" size="icon" onClick={() => removeSkill(skill.id)}>
                                            <XIcon className="size-4" />
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-muted-foreground text-sm">No hay skills asignados.</p>
                        )}
                    </div>
                )}

                {/* Tab: Requerimientos */}
                {activeTab === 'requerimientos' && (
                    <div className="w-3/4 space-y-4">
                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-sm">Busca y asigna requerimientos al puesto, o crea uno nuevo.</p>
                            <Dialog open={reqDialogOpen} onOpenChange={setReqDialogOpen}>
                                <DialogTrigger asChild>
                                    <Button type="button">
                                        <PlusIcon className="mr-1 size-4" />
                                        Crear nuevo requerimiento
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Crear nuevo requerimiento</DialogTitle>
                                    </DialogHeader>
                                    <div className="space-y-3">
                                        <FormField label="Descripción" htmlFor="new_req_desc" required>
                                            <Input
                                                id="new_req_desc"
                                                value={newReqDescripcion}
                                                onChange={(e) => setNewReqDescripcion(e.target.value)}
                                                placeholder="Ej. Experiencia: 3 años en almacén"
                                                autoFocus
                                            />
                                        </FormField>
                                        <FormField label="Valor (opcional)" htmlFor="new_req_valor">
                                            <Input
                                                id="new_req_valor"
                                                value={newReqValor}
                                                onChange={(e) => setNewReqValor(e.target.value)}
                                                placeholder="Ej. INDISTINTO, 18-45 AÑOS"
                                            />
                                        </FormField>
                                    </div>
                                    <DialogFooter>
                                        <DialogClose asChild>
                                            <Button variant="outline" type="button">Cancelar</Button>
                                        </DialogClose>
                                        <Button type="button" onClick={submitNewRequerimiento} disabled={!newReqDescripcion.trim()}>
                                            Crear y asignar
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </div>

                        <div className="flex gap-2">
                            <CreatableCombobox
                                options={requerimientoOptions}
                                placeholder="Buscar requerimiento existente..."
                                creatableLabel="Crear requerimiento"
                                onSelect={handleRequerimientoSelect}
                                onCreate={(descripcion) => {
                                    setNewReqDescripcion(descripcion);
                                    setReqDialogOpen(true);
                                }}
                                className="w-full flex-1"
                            />
                            <Input
                                value={reqValor}
                                onChange={(e) => setReqValor(e.target.value)}
                                placeholder="Valor (opcional)"
                                className="w-40 shrink-0"
                            />
                        </div>

                        {(puesto.requerimientos ?? []).length > 0 ? (
                            <ul className="space-y-2">
                                {(puesto.requerimientos ?? []).map((req) => (
                                    <li key={req.id} className="flex items-center justify-between rounded border px-3 py-2">
                                        <div className="flex items-center gap-2">
                                            <span>{req.descripcion}</span>
                                            {req.valor && <Badge variant="outline">{req.valor}</Badge>}
                                        </div>
                                        <Button type="button" variant="ghost" size="icon" onClick={() => removeRequerimiento(req.id)}>
                                            <XIcon className="size-4" />
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-muted-foreground text-sm">No hay requerimientos asignados.</p>
                        )}
                    </div>
                )}

                {/* Tab: Actividades */}
                {activeTab === 'actividades' && (
                    <div className="w-3/4 space-y-4">
                        <div className="flex gap-2">
                            <Input
                                value={newActividad}
                                onChange={(e) => setNewActividad(e.target.value)}
                                placeholder="Descripción de la actividad"
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        e.preventDefault();
                                        addActividad();
                                    }
                                }}
                            />
                            <Button type="button" variant="outline" onClick={addActividad} disabled={!newActividad.trim()}>
                                <PlusIcon className="mr-1 size-4" />
                                Agregar
                            </Button>
                        </div>
                        {(puesto.actividades ?? []).length > 0 ? (
                            <ul className="space-y-2">
                                {(puesto.actividades ?? []).map((act: RhActividad) => (
                                    <li key={act.id} className="flex items-center justify-between rounded border px-3 py-2">
                                        <span>{act.descripcion}</span>
                                        <Button type="button" variant="ghost" size="icon" onClick={() => removeActividad(act.id)}>
                                            <TrashIcon className="size-4" />
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-muted-foreground text-sm">No hay actividades registradas.</p>
                        )}
                    </div>
                )}

                {/* Tab: Plantilla de Onboarding */}
                {activeTab === 'plantilla' && (
                    <div className="w-3/4 space-y-4">
                        <p className="text-muted-foreground text-sm">
                            Estas tareas se copian automáticamente al crear el onboarding de un nuevo periodo laboral.
                        </p>
                        <div className="grid grid-cols-12 gap-2">
                            <Input
                                value={newTplTitulo}
                                onChange={(e) => setNewTplTitulo(e.target.value)}
                                placeholder="Título"
                                className="col-span-4"
                            />
                            <Input
                                value={newTplDescripcion}
                                onChange={(e) => setNewTplDescripcion(e.target.value)}
                                placeholder="Descripción (opcional)"
                                className="col-span-5"
                            />
                            <Input
                                type="number"
                                min="0"
                                value={newTplDias}
                                onChange={(e) => setNewTplDias(e.target.value)}
                                placeholder="Días"
                                className="col-span-2"
                            />
                            <Button type="button" variant="outline" onClick={addPlantillaOnboarding} disabled={!newTplTitulo.trim()} className="col-span-1">
                                <PlusIcon className="size-4" />
                            </Button>
                        </div>
                        {(puesto.plantillas_onboarding ?? []).length > 0 ? (
                            <ul className="space-y-2">
                                {(puesto.plantillas_onboarding ?? []).map((tpl) => (
                                    <li key={tpl.id} className="flex items-start justify-between gap-2 rounded border px-3 py-2">
                                        <div className="flex-1">
                                            <div className="font-medium">{tpl.titulo}</div>
                                            {tpl.descripcion && <div className="text-muted-foreground text-sm">{tpl.descripcion}</div>}
                                            {tpl.dias_desde_inicio !== null && (
                                                <div className="text-muted-foreground text-xs">Vence a los {tpl.dias_desde_inicio} días</div>
                                            )}
                                        </div>
                                        <Button type="button" variant="ghost" size="icon" onClick={() => removePlantillaOnboarding(tpl.id)}>
                                            <TrashIcon className="size-4" />
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-muted-foreground text-sm">Sin plantilla. Al crear onboarding, no se copiarán tareas predefinidas.</p>
                        )}
                    </div>
                )}

                {/* Tab: Documentos */}
                {activeTab === 'documentos' && (
                    <div className="w-3/4 space-y-4">
                        <div className="flex gap-2">
                            <Input
                                value={newDocNombre}
                                onChange={(e) => setNewDocNombre(e.target.value)}
                                placeholder="Nombre del reporte"
                                className="flex-1"
                            />
                            <Input
                                value={newDocFrecuencia}
                                onChange={(e) => setNewDocFrecuencia(e.target.value)}
                                placeholder="Frecuencia"
                                className="w-36"
                            />
                            <Input
                                value={newDocCargo}
                                onChange={(e) => setNewDocCargo(e.target.value)}
                                placeholder="Cargo entrega"
                                className="w-40"
                            />
                            <Button type="button" variant="outline" onClick={addDocumentoPuesto} disabled={!newDocNombre.trim()}>
                                <PlusIcon className="mr-1 size-4" />
                                Agregar
                            </Button>
                        </div>
                        {(puesto.documentos_puesto ?? []).length > 0 ? (
                            <ul className="space-y-2">
                                {(puesto.documentos_puesto ?? []).map((doc: RhDocumentoPuesto) => (
                                    <li key={doc.id} className="flex items-center justify-between rounded border px-3 py-2">
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">{doc.nombre_reporte}</span>
                                            {doc.frecuencia_entrega && (
                                                <Badge variant="secondary">{doc.frecuencia_entrega}</Badge>
                                            )}
                                            {doc.cargo_entrega && (
                                                <Badge variant="outline">{doc.cargo_entrega}</Badge>
                                            )}
                                        </div>
                                        <Button type="button" variant="ghost" size="icon" onClick={() => removeDocumentoPuesto(doc.id)}>
                                            <TrashIcon className="size-4" />
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-muted-foreground text-sm">No hay documentos registrados.</p>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
