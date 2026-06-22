import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeftIcon, CheckIcon, Loader2Icon, PencilIcon, PlusIcon, Trash2Icon, XIcon } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosObraRubro, CostosRubro, Obra, ObraEstatus } from '@/types/models';
import { OBRA_ESTATUS_LABELS } from '@/types/models';

const ESTATUS_COLORS: Record<ObraEstatus, string> = {
    abierta: 'badge-success',
    cerrada: 'badge-ghost',
};

const fmt = (v: number) => `$${Number(v).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;

type Props = {
    obra: Obra & { obra_rubros: CostosObraRubro[] };
    rubros: CostosRubro[];
};

export default function PresupuestosEdit({ obra, rubros }: Props) {
    const [newRubroId, setNewRubroId] = useState('');
    const [newPresupuestado, setNewPresupuestado] = useState('');
    const [addingRubro, setAddingRubro] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editingField, setEditingField] = useState<'presupuestado' | 'acumulado'>('presupuestado');
    const [editValue, setEditValue] = useState('');
    const [updatingId, setUpdatingId] = useState<number | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/presupuestos' },
        { title: 'Presupuestos', href: '/admin/costos/presupuestos' },
        { title: obra.no, href: `/admin/costos/presupuestos/${obra.id}/edit` },
    ];

    const currentRubros = obra.obra_rubros ?? [];

    const assignedRubroIds = currentRubros.map((or) => or.rubro_id);
    const availableRubros = rubros.filter((r) => !assignedRubroIds.includes(r.id));

    const handleAddObraRubro = (e: FormEvent) => {
        e.preventDefault();
        if (!newRubroId || !newPresupuestado) return;

        setAddingRubro(true);
        router.post('/admin/costos/obra-rubros', {
            obra_id: obra.id,
            rubro_id: newRubroId,
            presupuestado: newPresupuestado,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setNewRubroId('');
                setNewPresupuestado('');
            },
            onFinish: () => setAddingRubro(false),
        });
    };

    const handleDeleteObraRubro = (obraRubroId: number) => {
        router.delete(`/admin/costos/obra-rubros/${obraRubroId}`, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const handleStartEdit = (or: CostosObraRubro, field: 'presupuestado' | 'acumulado') => {
        setEditingId(or.id);
        setEditingField(field);
        setEditValue(String(field === 'presupuestado' ? or.presupuestado : or.acumulado));
    };

    const handleCancelEdit = () => {
        setEditingId(null);
        setEditValue('');
    };

    const handleSaveEdit = (or: CostosObraRubro) => {
        setUpdatingId(or.id);
        router.put(`/admin/costos/obra-rubros/${or.id}`, {
            presupuestado: editingField === 'presupuestado' ? editValue : or.presupuestado,
            acumulado: editingField === 'acumulado' ? editValue : or.acumulado,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setEditingId(null);
                setEditValue('');
            },
            onFinish: () => setUpdatingId(null),
        });
    };

    const montoEditable = (or: CostosObraRubro, field: 'presupuestado' | 'acumulado') => {
        const editando = editingId === or.id && editingField === field;
        const valor = field === 'presupuestado' ? or.presupuestado : or.acumulado;

        return (
            <td className="text-right font-mono">
                {editando ? (
                    <div className="flex items-center justify-end gap-1">
                        <Input
                            type="number"
                            step="0.01"
                            min="0"
                            value={editValue}
                            onChange={(e) => setEditValue(e.target.value)}
                            className="w-32 text-right"
                        />
                        <button
                            type="button"
                            className="btn btn-ghost btn-xs text-success"
                            disabled={updatingId === or.id}
                            onClick={() => handleSaveEdit(or)}
                        >
                            {updatingId === or.id ? <Loader2Icon className="size-3 animate-spin" /> : <CheckIcon className="size-3" />}
                        </button>
                        <button type="button" className="btn btn-ghost btn-xs" onClick={handleCancelEdit}>
                            <XIcon className="size-3" />
                        </button>
                    </div>
                ) : (
                    <span
                        className="cursor-pointer hover:text-primary"
                        onClick={() => handleStartEdit(or, field)}
                        title="Clic para editar"
                    >
                        {fmt(valor)}
                        <PencilIcon className="ml-1 inline size-3 opacity-30" />
                    </span>
                )}
            </td>
        );
    };

    const totalPresupuestado = currentRubros.reduce((sum, or) => sum + Number(or.presupuestado), 0);
    const totalAcumulado = currentRubros.reduce((sum, or) => sum + Number(or.acumulado), 0);
    const totalDisponible = totalPresupuestado - totalAcumulado;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Presupuesto - ${obra.no}`} />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <Link href="/admin/costos/presupuestos">
                            <ArrowLeftIcon className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-semibold">{obra.no} - {obra.descripcion}</h1>
                        <div className="mt-1 flex items-center gap-3 text-sm text-base-content/60">
                            <span className={`badge badge-sm ${ESTATUS_COLORS[obra.estatus]}`}>
                                {OBRA_ESTATUS_LABELS[obra.estatus]}
                            </span>
                            {obra.es_planta && <span className="badge badge-info badge-sm">Planta</span>}
                        </div>
                    </div>
                </div>

                {/* Tabla de rubros */}
                {currentRubros.length > 0 ? (
                    <div className="overflow-x-auto">
                        <table className="table w-full">
                            <thead>
                                <tr>
                                    <th>Codigo</th>
                                    <th>Centro de Costos</th>
                                    <th>Tipo</th>
                                    <th className="text-right">Presupuestado</th>
                                    <th className="text-right">Acumulado</th>
                                    <th className="text-right">Disponible</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                {currentRubros.map((or) => (
                                    <tr key={or.id}>
                                        <td className="font-mono text-sm">{or.rubro?.codigo}</td>
                                        <td>{or.rubro?.descripcion}</td>
                                        <td className="text-sm">{or.rubro?.tipo_rubro?.descripcion}</td>
                                        {montoEditable(or, 'presupuestado')}
                                        {montoEditable(or, 'acumulado')}
                                        <td className="text-right font-mono">
                                            {(() => {
                                                const d = Number(or.presupuestado) - Number(or.acumulado);
                                                return <span className={d < 0 ? 'text-error' : ''}>{fmt(d)}</span>;
                                            })()}
                                        </td>
                                        <td>
                                            <button
                                                type="button"
                                                className="btn btn-ghost btn-sm text-error"
                                                onClick={() => handleDeleteObraRubro(or.id)}
                                            >
                                                <Trash2Icon className="size-4" />
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr className="font-bold">
                                    <td colSpan={3}>Total</td>
                                    <td className="text-right font-mono">{fmt(totalPresupuestado)}</td>
                                    <td className="text-right font-mono">{fmt(totalAcumulado)}</td>
                                    <td className={`text-right font-mono ${totalDisponible < 0 ? 'text-error' : ''}`}>{fmt(totalDisponible)}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                ) : (
                    <p className="text-sm text-base-content/60">No hay centros de costos asignados a esta obra.</p>
                )}

                {/* Agregar centro de costos */}
                {availableRubros.length > 0 ? (
                    <form onSubmit={handleAddObraRubro} className="flex items-end gap-4">
                        <FormField label="Centro de Costos" htmlFor="new_rubro_id" className="flex-1">
                            <Select id="new_rubro_id" value={newRubroId} onValueChange={setNewRubroId}>
                                <option value="">Seleccionar centro de costos</option>
                                {availableRubros.map((r) => (
                                    <option key={r.id} value={r.id}>
                                        {r.codigo} - {r.descripcion} ({r.tipo_rubro?.descripcion})
                                    </option>
                                ))}
                            </Select>
                        </FormField>
                        <FormField label="Presupuestado" htmlFor="new_presupuestado" className="w-48">
                            <Input
                                id="new_presupuestado"
                                type="number"
                                step="0.01"
                                min="0"
                                value={newPresupuestado}
                                onChange={(e) => setNewPresupuestado(e.target.value)}
                            />
                        </FormField>
                        <Button type="submit" disabled={addingRubro || !newRubroId || !newPresupuestado}>
                            {addingRubro ? <Loader2Icon className="size-4 animate-spin" /> : <PlusIcon className="size-4" />}
                            Agregar
                        </Button>
                    </form>
                ) : (
                    <p className="text-sm text-base-content/60">Todos los centros de costos ya estan asignados a esta obra.</p>
                )}
            </div>
        </AppLayout>
    );
}
