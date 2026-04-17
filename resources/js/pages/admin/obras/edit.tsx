import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Concepto, CostosObraRubro, CostosRubro, Obra } from '@/types/models';
import { OBRA_ESTATUS_LABELS, type ObraEstatus } from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Loader2Icon, PlusIcon, Trash2Icon, UploadIcon } from 'lucide-react';
import { useState, type FormEvent } from 'react';

type Props = {
    obra: Obra & { conceptos: Concepto[]; obra_rubros: CostosObraRubro[] };
    rubros: CostosRubro[];
};

export default function ObrasEdit({ obra, rubros }: Props) {
    const [activeTab, setActiveTab] = useState<'datos' | 'conceptos' | 'presupuesto'>('datos');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Obras', href: '/admin/obras' },
        { title: obra.no, href: `/admin/obras/${obra.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        no: obra.no,
        descripcion: obra.descripcion,
        fecha_inicio: obra.fecha_inicio ? obra.fecha_inicio.substring(0, 10) : '',
        fecha_fin: obra.fecha_fin ? obra.fecha_fin.substring(0, 10) : '',
        presupuesto_total: String(obra.presupuesto_total),
        ingreso_real: obra.ingreso_real !== null && obra.ingreso_real !== undefined ? String(obra.ingreso_real) : '',
        estatus: obra.estatus,
    });

    const csvForm = useForm<{ csv_file: File | null }>({
        csv_file: null,
    });

    const [newRubroId, setNewRubroId] = useState('');
    const [newPresupuestado, setNewPresupuestado] = useState('');
    const [addingRubro, setAddingRubro] = useState(false);

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/obras/${obra.id}`);
    };

    const handleCsvImport = (e: FormEvent) => {
        e.preventDefault();
        if (!csvForm.data.csv_file) return;

        csvForm.post(`/admin/obras/${obra.id}/import-conceptos`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => csvForm.reset('csv_file'),
        });
    };

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
        });
    };

    const assignedRubroIds = (obra.obra_rubros ?? []).map((or) => or.rubro_id);
    const availableRubros = rubros.filter((r) => !assignedRubroIds.includes(r.id));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${obra.no}`} />

            <div className="space-y-6 p-6">
                <div className="tabs tabs-boxed w-3/4">
                    <button type="button" className={`tab ${activeTab === 'datos' ? 'tab-active' : ''}`} onClick={() => setActiveTab('datos')}>
                        Datos
                    </button>
                    <button type="button" className={`tab ${activeTab === 'conceptos' ? 'tab-active' : ''}`} onClick={() => setActiveTab('conceptos')}>
                        Conceptos ({obra.conceptos?.length ?? 0})
                    </button>
                    <button type="button" className={`tab ${activeTab === 'presupuesto' ? 'tab-active' : ''}`} onClick={() => setActiveTab('presupuesto')}>
                        Presupuesto ({obra.obra_rubros?.length ?? 0})
                    </button>
                </div>

                {activeTab === 'datos' && (
                    <div className="w-3/4">
                        <div className="mb-6 flex items-center justify-between">
                            <h1 className="text-2xl font-semibold">Editar Obra</h1>
                            <DeleteDialog
                                title="Eliminar obra"
                                description={`¿Estas seguro de eliminar la obra "${obra.no}"? Esta accion no se puede deshacer.`}
                                deleteUrl={`/admin/obras/${obra.id}`}
                            />
                        </div>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <FormField label="Numero" htmlFor="no" error={errors.no} required>
                                    <Input
                                        id="no"
                                        value={data.no}
                                        onChange={(e) => setData('no', e.target.value)}
                                        placeholder="Ej: OBR-001"
                                    />
                                </FormField>

                                <FormField label="Estatus" htmlFor="estatus" error={errors.estatus}>
                                    <Select id="estatus" value={data.estatus} onValueChange={(value) => setData('estatus', value as ObraEstatus)}>
                                        {(Object.keys(OBRA_ESTATUS_LABELS) as ObraEstatus[]).map((key) => (
                                            <option key={key} value={key}>{OBRA_ESTATUS_LABELS[key]}</option>
                                        ))}
                                    </Select>
                                </FormField>
                            </div>

                            <FormField
                                label="Descripcion"
                                htmlFor="descripcion"
                                error={errors.descripcion}
                                required
                            >
                                <Input
                                    id="descripcion"
                                    value={data.descripcion}
                                    onChange={(e) => setData('descripcion', e.target.value)}
                                    placeholder="Descripcion de la obra"
                                />
                            </FormField>

                            <div className="grid grid-cols-3 gap-4">
                                <FormField label="Fecha Inicio" htmlFor="fecha_inicio" error={errors.fecha_inicio}>
                                    <Input
                                        id="fecha_inicio"
                                        type="date"
                                        value={data.fecha_inicio}
                                        onChange={(e) => setData('fecha_inicio', e.target.value)}
                                    />
                                </FormField>

                                <FormField label="Fecha Fin" htmlFor="fecha_fin" error={errors.fecha_fin}>
                                    <Input
                                        id="fecha_fin"
                                        type="date"
                                        value={data.fecha_fin}
                                        onChange={(e) => setData('fecha_fin', e.target.value)}
                                    />
                                </FormField>

                                <FormField label="Presupuesto Total" htmlFor="presupuesto_total" error={errors.presupuesto_total}>
                                    <Input
                                        id="presupuesto_total"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={data.presupuesto_total}
                                        onChange={(e) => setData('presupuesto_total', e.target.value)}
                                    />
                                </FormField>
                            </div>

                            <FormField label="Ingreso Real" htmlFor="ingreso_real" error={errors.ingreso_real}>
                                <Input
                                    id="ingreso_real"
                                    type="number"
                                    step="0.0001"
                                    min="0"
                                    value={data.ingreso_real}
                                    onChange={(e) => setData('ingreso_real', e.target.value)}
                                    placeholder="0.00"
                                />
                            </FormField>

                            <div className="flex justify-end gap-2">
                                <Button variant="outline" asChild>
                                    <Link href="/admin/obras">Cancelar</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Loader2Icon className="size-4 animate-spin" />}
                                    Guardar
                                </Button>
                            </div>
                        </form>
                    </div>
                )}

                {activeTab === 'conceptos' && (
                    <div className="space-y-6">
                        <h2 className="text-lg font-semibold">Conceptos de la Obra</h2>

                        {obra.conceptos && obra.conceptos.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="table table-zebra w-full">
                                    <thead>
                                        <tr>
                                            <th>Marca</th>
                                            <th>Descripcion</th>
                                            <th className="text-right">Peso Unit. (kg)</th>
                                            <th className="text-right">Version</th>
                                            <th>Activo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {obra.conceptos.map((concepto) => (
                                            <tr key={concepto.id} className="hover cursor-pointer" onClick={() => window.location.href = `/admin/prod/conceptos/${concepto.id}/edit`}>
                                                <td className="font-medium">{concepto.marca}</td>
                                                <td>{concepto.descripcion}</td>
                                                <td className="text-right font-mono text-sm">{Number(concepto.peso_unitario).toLocaleString('es-MX', { minimumFractionDigits: 3 })}</td>
                                                <td className="text-right font-mono text-sm">{concepto.version}</td>
                                                <td>
                                                    <span className={`badge badge-sm ${concepto.activo ? 'badge-success' : 'badge-ghost'}`}>
                                                        {concepto.activo ? 'Si' : 'No'}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500">No hay conceptos registrados para esta obra.</p>
                        )}

                        <div className="divider" />

                        <h2 className="text-lg font-semibold">Importar Conceptos desde CSV</h2>
                        <p className="text-sm text-gray-500">
                            Formato esperado: PLANO (marca), CONCEPTO (descripcion), KG.UNIT. (peso_unitario), OBSERVACIONES (version).
                            Si la marca ya existe, solo se actualiza si la version importada es mayor.
                        </p>

                        <form onSubmit={handleCsvImport} className="flex items-end gap-4">
                            <FormField label="Archivo CSV" htmlFor="csv_file" error={csvForm.errors.csv_file}>
                                <input
                                    id="csv_file"
                                    type="file"
                                    accept=".csv,.txt"
                                    className="file-input file-input-bordered w-full"
                                    onChange={(e) => csvForm.setData('csv_file', e.target.files?.[0] ?? null)}
                                />
                            </FormField>
                            <Button type="submit" disabled={csvForm.processing || !csvForm.data.csv_file}>
                                {csvForm.processing ? <Loader2Icon className="size-4 animate-spin" /> : <UploadIcon className="size-4" />}
                                Importar CSV
                            </Button>
                        </form>
                    </div>
                )}

                {activeTab === 'presupuesto' && (
                    <div className="space-y-6">
                        <h2 className="text-lg font-semibold">Presupuesto por Rubros</h2>

                        {obra.obra_rubros && obra.obra_rubros.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="table w-full">
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Rubro</th>
                                            <th>Tipo</th>
                                            <th className="text-right">Presupuestado</th>
                                            <th className="text-right">Acumulado</th>
                                            <th className="text-right">Disponible</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {obra.obra_rubros.map((or) => (
                                            <tr key={or.id}>
                                                <td className="font-mono text-sm">{or.rubro?.codigo}</td>
                                                <td>{or.rubro?.descripcion}</td>
                                                <td className="text-sm">{or.rubro?.tipo_rubro?.descripcion}</td>
                                                <td className="text-right font-mono">${Number(or.presupuestado).toLocaleString('es-MX', { minimumFractionDigits: 2 })}</td>
                                                <td className="text-right font-mono">${Number(or.acumulado).toLocaleString('es-MX', { minimumFractionDigits: 2 })}</td>
                                                <td className="text-right font-mono">
                                                    ${(Number(or.presupuestado) - Number(or.acumulado)).toLocaleString('es-MX', { minimumFractionDigits: 2 })}
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
                                            <td className="text-right font-mono">
                                                ${obra.obra_rubros.reduce((sum, or) => sum + Number(or.presupuestado), 0).toLocaleString('es-MX', { minimumFractionDigits: 2 })}
                                            </td>
                                            <td className="text-right font-mono">
                                                ${obra.obra_rubros.reduce((sum, or) => sum + Number(or.acumulado), 0).toLocaleString('es-MX', { minimumFractionDigits: 2 })}
                                            </td>
                                            <td className="text-right font-mono">
                                                ${obra.obra_rubros.reduce((sum, or) => sum + (Number(or.presupuestado) - Number(or.acumulado)), 0).toLocaleString('es-MX', { minimumFractionDigits: 2 })}
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        ) : (
                            <p className="text-sm text-base-content/60">No hay rubros asignados a esta obra.</p>
                        )}

                        <div className="divider" />

                        <h3 className="text-md font-medium">Agregar Rubro</h3>
                        {availableRubros.length > 0 ? (
                            <form onSubmit={handleAddObraRubro} className="flex items-end gap-4">
                                <FormField label="Rubro" htmlFor="new_rubro_id" className="flex-1">
                                    <Select id="new_rubro_id" value={newRubroId} onValueChange={setNewRubroId}>
                                        <option value="">Seleccionar rubro</option>
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
                            <p className="text-sm text-base-content/60">Todos los rubros ya están asignados a esta obra.</p>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
