import { Head, Link, router } from '@inertiajs/react';
import { ArrowDownIcon, ArrowLeftIcon, ArrowUpDownIcon, ArrowUpIcon, CheckIcon, ListPlusIcon, LockIcon, LockOpenIcon, Loader2Icon, PencilIcon, PlusIcon, Trash2Icon, XIcon } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SearchSelect } from '@/components/ui/search-select';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosObraRubro, CostosPresupuestoEstatus, CostosRubro, PresupuestableTipo, PresupuestoRow } from '@/types/models';

const TIPO_LABELS: Record<PresupuestableTipo, string> = {
    proyecto: 'Proyecto',
    obra: 'Obra',
    partida: 'Partida',
};

const ESTATUS_LABELS: Record<CostosPresupuestoEstatus, string> = {
    activo: 'Activo',
    cerrado: 'Cerrado',
};

const ESTATUS_COLORS: Record<CostosPresupuestoEstatus, string> = {
    activo: 'badge-success',
    cerrado: 'badge-ghost',
};

const fmt = (v: number) => `$${Number(v).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;

type PresupuestoDetalle = PresupuestoRow & { obra_rubros: CostosObraRubro[] };

type Props = {
    presupuesto: PresupuestoDetalle;
    rubros: CostosRubro[];
    presupuestables: { value: string; label: string }[];
};

export default function PresupuestosEdit({ presupuesto, rubros, presupuestables }: Props) {
    const [newRubroId, setNewRubroId] = useState('');
    const [newPresupuestado, setNewPresupuestado] = useState('');
    const [addingRubro, setAddingRubro] = useState(false);
    const [addingAll, setAddingAll] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editingField, setEditingField] = useState<'presupuestado' | 'acumulado'>('presupuestado');
    const [editValue, setEditValue] = useState('');
    const [updatingId, setUpdatingId] = useState<number | null>(null);
    const [sort, setSort] = useState<{ key: string; dir: 'asc' | 'desc' } | null>(null);
    const [nombreInterno, setNombreInterno] = useState(presupuesto.nombre_interno ?? '');
    const [opInterno, setOpInterno] = useState(presupuesto.op_interno ?? '');
    const [presupuestableSel, setPresupuestableSel] = useState(`${presupuesto.tipo}:${presupuesto.presupuestable_id}`);
    const [savingDatos, setSavingDatos] = useState(false);
    const [changingEstado, setChangingEstado] = useState(false);

    const cerrado = presupuesto.estatus === 'cerrado';

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/presupuestos' },
        { title: 'Presupuestos', href: '/admin/costos/presupuestos' },
        { title: presupuesto.nombre, href: `/admin/costos/presupuestos/${presupuesto.id}/edit` },
    ];

    const currentRubros = presupuesto.obra_rubros ?? [];

    const assignedRubroIds = currentRubros.map((or) => or.rubro_id);
    const availableRubros = rubros.filter((r) => !assignedRubroIds.includes(r.id));

    const handleAddObraRubro = (e: FormEvent) => {
        e.preventDefault();
        if (!newRubroId || !newPresupuestado) return;

        setAddingRubro(true);
        router.post('/admin/costos/obra-rubros', {
            presupuesto_id: presupuesto.id,
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

    const handleAddAll = () => {
        setAddingAll(true);
        router.post('/admin/costos/obra-rubros/todos', {
            presupuesto_id: presupuesto.id,
        }, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setAddingAll(false),
        });
    };

    const handleDeleteObraRubro = (obraRubroId: number) => {
        router.delete(`/admin/costos/obra-rubros/${obraRubroId}`, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const handleGuardarDatos = (e: FormEvent) => {
        e.preventDefault();
        const [tipo, id] = presupuestableSel.split(':');
        setSavingDatos(true);
        router.put(`/admin/costos/presupuestos/${presupuesto.id}`, {
            nombre_interno: nombreInterno,
            op_interno: opInterno,
            presupuestable_type: tipo,
            presupuestable_id: id,
        }, {
            preserveScroll: true,
            onFinish: () => setSavingDatos(false),
        });
    };

    const handleToggleEstado = () => {
        setChangingEstado(true);
        router.post(`/admin/costos/presupuestos/${presupuesto.id}/estado`, {}, {
            preserveScroll: true,
            onFinish: () => setChangingEstado(false),
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
    const totalApartado = currentRubros.reduce((sum, or) => sum + Number(or.apartado ?? 0), 0);
    const totalDisponible = totalPresupuestado - totalAcumulado - totalApartado;

    const disponibleDe = (or: CostosObraRubro) => Number(or.presupuestado) - Number(or.acumulado) - Number(or.apartado ?? 0);

    const sortValue = (or: CostosObraRubro, key: string): string | number => {
        switch (key) {
            case 'codigo': return or.rubro?.codigo ?? '';
            case 'descripcion': return or.rubro?.descripcion ?? '';
            case 'tipo': return or.rubro?.tipo_rubro?.descripcion ?? '';
            case 'presupuestado': return Number(or.presupuestado);
            case 'acumulado': return Number(or.acumulado);
            case 'apartado': return Number(or.apartado ?? 0);
            case 'disponible': return disponibleDe(or);
            default: return '';
        }
    };

    const sortedRubros = [...currentRubros];
    if (sort) {
        sortedRubros.sort((a, b) => {
            const va = sortValue(a, sort.key);
            const vb = sortValue(b, sort.key);
            const cmp = typeof va === 'number' && typeof vb === 'number'
                ? va - vb
                : String(va).localeCompare(String(vb), 'es', { numeric: true });
            return sort.dir === 'asc' ? cmp : -cmp;
        });
    }

    const toggleSort = (key: string) => {
        setSort((prev) => (prev?.key === key
            ? { key, dir: prev.dir === 'asc' ? 'desc' : 'asc' }
            : { key, dir: 'asc' }));
    };

    const SortHeader = ({ column, label, className }: { column: string; label: string; className?: string }) => (
        <th className={className}>
            <button type="button" className="inline-flex items-center gap-1 hover:text-primary" onClick={() => toggleSort(column)}>
                {label}
                {sort?.key === column
                    ? (sort.dir === 'asc' ? <ArrowUpIcon className="size-3" /> : <ArrowDownIcon className="size-3" />)
                    : <ArrowUpDownIcon className="size-3 opacity-30" />}
            </button>
        </th>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Presupuesto - ${presupuesto.nombre}`} />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div className="flex items-start gap-4">
                        <Button variant="outline" size="icon" asChild>
                            <Link href="/admin/costos/presupuestos">
                                <ArrowLeftIcon className="size-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-semibold">{presupuesto.nombre}</h1>
                            <div className="mt-1 flex items-center gap-3 text-sm text-base-content/60">
                                <span className={`badge badge-sm ${presupuesto.es_planta ? 'badge-info' : 'badge-neutral'}`}>
                                    {presupuesto.es_planta ? 'Planta' : TIPO_LABELS[presupuesto.tipo]}
                                </span>
                                <span className={`badge badge-sm ${ESTATUS_COLORS[presupuesto.estatus]}`}>
                                    {ESTATUS_LABELS[presupuesto.estatus]}
                                </span>
                                {presupuesto.op && (
                                    <span className="text-xs">OP: {presupuesto.op}</span>
                                )}
                            </div>
                        </div>
                    </div>
                    <Button variant="outline" onClick={handleToggleEstado} disabled={changingEstado}>
                        {changingEstado ? <Loader2Icon className="size-4 animate-spin" /> : (cerrado ? <LockOpenIcon className="size-4" /> : <LockIcon className="size-4" />)}
                        {cerrado ? 'Reabrir' : 'Cerrar'}
                    </Button>
                </div>

                {/* Datos del presupuesto */}
                <form onSubmit={handleGuardarDatos} className="card border border-base-300 bg-base-100">
                    <div className="card-body gap-4">
                        <h2 className="card-title text-base">Datos del presupuesto</h2>
                        <div className="grid gap-4 md:grid-cols-3">
                            <FormField label="Proyecto / Obra / Partida (cobranza)" htmlFor="presupuestable" className="md:col-span-3">
                                <SearchSelect
                                    value={presupuestableSel}
                                    onValueChange={setPresupuestableSel}
                                    options={presupuestables}
                                    placeholder="Buscar proyecto, obra o partida…"
                                />
                            </FormField>
                            <FormField label="Nombre interno (opcional)" htmlFor="nombre_interno">
                                <Input
                                    id="nombre_interno"
                                    value={nombreInterno}
                                    onChange={(e) => setNombreInterno(e.target.value)}
                                    placeholder={presupuesto.descripcion ?? 'Nombre a usar en costos'}
                                />
                            </FormField>
                            <FormField label="OP interna (opcional)" htmlFor="op_interno">
                                <Input
                                    id="op_interno"
                                    value={opInterno}
                                    onChange={(e) => setOpInterno(e.target.value)}
                                    placeholder={presupuesto.no ?? 'OP a usar en costos'}
                                />
                            </FormField>
                            <div className="flex items-end">
                                <Button type="submit" disabled={savingDatos}>
                                    {savingDatos ? <Loader2Icon className="size-4 animate-spin" /> : <CheckIcon className="size-4" />}
                                    Guardar
                                </Button>
                            </div>
                        </div>
                    </div>
                </form>

                {/* Tabla de rubros */}
                {currentRubros.length > 0 ? (
                    <div className="overflow-x-auto">
                        <table className="table w-full">
                            <thead>
                                <tr>
                                    <SortHeader column="codigo" label="Codigo" />
                                    <SortHeader column="descripcion" label="Centro de Costos" />
                                    <SortHeader column="tipo" label="Tipo" />
                                    <SortHeader column="presupuestado" label="Presupuestado" className="text-right" />
                                    <SortHeader column="acumulado" label="Ejercido" className="text-right" />
                                    <SortHeader column="apartado" label="Apartado" className="text-right" />
                                    <SortHeader column="disponible" label="Disponible" className="text-right" />
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                {sortedRubros.map((or) => (
                                    <tr key={or.id}>
                                        <td className="font-mono text-sm">{or.rubro?.codigo}</td>
                                        <td>{or.rubro?.descripcion}</td>
                                        <td className="text-sm">{or.rubro?.tipo_rubro?.descripcion}</td>
                                        {montoEditable(or, 'presupuestado')}
                                        {montoEditable(or, 'acumulado')}
                                        <td className="text-right font-mono">
                                            {Number(or.apartado ?? 0) > 0
                                                ? <span className="text-info">{fmt(Number(or.apartado ?? 0))}</span>
                                                : <span className="text-base-content/40">{fmt(0)}</span>}
                                        </td>
                                        <td className="text-right font-mono">
                                            {(() => {
                                                const d = disponibleDe(or);
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
                                    <td className="text-right font-mono">{fmt(totalApartado)}</td>
                                    <td className={`text-right font-mono ${totalDisponible < 0 ? 'text-error' : ''}`}>{fmt(totalDisponible)}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                ) : (
                    <p className="text-sm text-base-content/60">No hay centros de costos asignados a este presupuesto.</p>
                )}

                {/* Agregar centro de costos */}
                {availableRubros.length > 0 ? (
                    <div className="space-y-3">
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
                        <Button type="button" variant="outline" disabled={addingAll} onClick={handleAddAll}>
                            {addingAll ? <Loader2Icon className="size-4 animate-spin" /> : <ListPlusIcon className="size-4" />}
                            Agregar todos los centros de costos ({availableRubros.length})
                        </Button>
                    </div>
                ) : (
                    <p className="text-sm text-base-content/60">Todos los centros de costos ya estan asignados a este presupuesto.</p>
                )}
            </div>
        </AppLayout>
    );
}
