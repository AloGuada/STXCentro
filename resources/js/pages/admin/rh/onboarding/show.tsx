import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { RhOnboarding, RhOnboardingTarea } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import { CheckIcon, FileIcon, PlusIcon, TrashIcon, UploadIcon } from 'lucide-react';
import { useRef, useState } from 'react';

type Props = {
    onboarding: RhOnboarding;
    periodosActivos: { id: number; nombre: string }[];
};

export default function OnboardingShow({ onboarding, periodosActivos }: Props) {
    const personaNombre = onboarding.periodo?.persona
        ? `${onboarding.periodo.persona.nombre} ${onboarding.periodo.persona.apellido}`
        : 'Onboarding';

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'RH', href: '/admin/rh/puestos' },
        { title: 'Onboarding', href: '/admin/rh/periodos-laborales' },
        { title: personaNombre, href: `/admin/rh/onboarding/${onboarding.id}` },
    ];

    const [newTareaTitle, setNewTareaTitle] = useState('');
    const [newTareaDesc, setNewTareaDesc] = useState('');
    const [newTareaFecha, setNewTareaFecha] = useState('');
    const [newTareaResponsable, setNewTareaResponsable] = useState('');

    const tareas = onboarding.tareas ?? [];
    const totalTareas = tareas.length;
    const completadas = tareas.filter((t) => t.completada).length;
    const progreso = totalTareas > 0 ? Math.round((completadas / totalTareas) * 100) : 0;

    const toggleTarea = (tareaId: number) => {
        router.post(`/admin/rh/onboarding/${onboarding.id}/tareas/${tareaId}/toggle`, {}, { preserveScroll: true });
    };

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

    const deleteTarea = (tareaId: number) => {
        router.delete(`/admin/rh/onboarding/${onboarding.id}/tareas/${tareaId}`, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Onboarding - ${personaNombre}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Onboarding - {personaNombre}</h1>

                    {/* Info del periodo */}
                    {onboarding.periodo && (
                        <div className="mb-6 rounded border p-4">
                            <h2 className="mb-3 text-lg font-semibold">Periodo Laboral</h2>
                            <dl className="grid grid-cols-2 gap-x-6 gap-y-3">
                                <div>
                                    <dt className="text-muted-foreground text-sm">Puesto</dt>
                                    <dd>{onboarding.periodo.puesto?.nombre ?? '-'}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm">Fecha Inicio</dt>
                                    <dd>{onboarding.periodo.fecha_inicio}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm">Estado</dt>
                                    <dd>
                                        <Badge variant={onboarding.periodo.estado === 'activo' ? 'default' : 'secondary'}>
                                            {onboarding.periodo.estado}
                                        </Badge>
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm">Fecha Inicio Onboarding</dt>
                                    <dd>{onboarding.fecha_inicio ?? '-'}</dd>
                                </div>
                            </dl>
                        </div>
                    )}

                    {/* Progreso */}
                    <div className="mb-6 rounded border p-4">
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

                    {/* Tareas */}
                    <div className="rounded border p-4">
                        <h2 className="mb-4 text-lg font-semibold">Tareas</h2>

                        {/* Agregar tarea */}
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
                                <Select value={newTareaResponsable} onValueChange={setNewTareaResponsable} placeholder="Responsable" className="flex-1">
                                    {periodosActivos.map((p) => (
                                        <SelectItem key={p.id} value={String(p.id)}>
                                            {p.nombre}
                                        </SelectItem>
                                    ))}
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
            </div>
        </AppLayout>
    );
}

function TareaItem({ tarea, onboardingId, onToggle, onDelete }: {
    tarea: RhOnboardingTarea;
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
                    {tarea.evidencia_ruta ? (
                        <Button variant="outline" size="sm" asChild>
                            <a href={`/storage/${tarea.evidencia_ruta}`} target="_blank" rel="noopener noreferrer">
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
