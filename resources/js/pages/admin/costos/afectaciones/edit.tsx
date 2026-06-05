import { EditLockBanner } from '@/components/costos/edit-lock-banner';
import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useEditLock } from '@/hooks/use-edit-lock';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosAfectacionPresupuestal, CostosObraRubro, Departamento, Obra, Proveedor } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangleIcon, Loader2Icon, PlusIcon, Trash2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type DetalleForm = {
    id?: number;
    obra_rubro_id: string;
    concepto: string;
    cantidad: string;
    precio_unitario: string;
};

type Props = {
    afectacion: CostosAfectacionPresupuestal;
    departamentos: Departamento[];
    proveedores: Proveedor[];
    obras: Obra[];
    obraRubros: CostosObraRubro[];
};

export default function AfectacionesEdit({ afectacion, departamentos, proveedores, obraRubros }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/afectaciones' },
        { title: 'Afectaciones', href: '/admin/costos/afectaciones' },
        { title: afectacion.folio, href: `/admin/costos/afectaciones/${afectacion.id}/edit` },
    ];

    const lockState = useEditLock('afectacion', afectacion.id);
    const readonly = lockState.status !== 'owned';

    const { data, setData, put, processing, errors } = useForm<{
        fecha: string;
        tipo_origen: string;
        descripcion: string;
        departamento_id: string;
        proveedor_id: string;
        detalles: DetalleForm[];
        _version: string;
    }>({
        fecha: afectacion.fecha ? afectacion.fecha.slice(0, 10) : '',
        tipo_origen: afectacion.tipo_origen,
        descripcion: afectacion.descripcion,
        departamento_id: String(afectacion.departamento_id),
        proveedor_id: afectacion.proveedor_id ? String(afectacion.proveedor_id) : '',
        _version: afectacion.updated_at,
        detalles: (afectacion.detalles ?? []).map((d) => ({
            id: d.id,
            obra_rubro_id: String(d.obra_rubro_id),
            concepto: d.concepto,
            cantidad: String(d.cantidad),
            precio_unitario: String(d.precio_unitario),
        })),
    });

    const addDetalle = () => {
        setData('detalles', [...data.detalles, { obra_rubro_id: '', concepto: '', cantidad: '1', precio_unitario: '0' }]);
    };

    const removeDetalle = (index: number) => {
        setData('detalles', data.detalles.filter((_, i) => i !== index));
    };

    const updateDetalle = (index: number, field: keyof DetalleForm, value: string) => {
        const updated = [...data.detalles];
        updated[index] = { ...updated[index], [field]: value };
        setData('detalles', updated);
    };

    const calcMonto = (d: DetalleForm) => {
        const cant = parseFloat(d.cantidad) || 0;
        const precio = parseFloat(d.precio_unitario) || 0;
        return cant * precio;
    };

    const total = data.detalles.reduce((sum, d) => sum + calcMonto(d), 0);

    const getDisponible = (obraRubroId: string) => {
        const or = obraRubros.find((r) => r.id === Number(obraRubroId));
        if (!or) {
            return null;
        }
        return Number(or.presupuestado) - Number(or.acumulado);
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/costos/afectaciones/${afectacion.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${afectacion.folio}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <div className="mb-6 flex items-center justify-between">
                        <h1 className="text-2xl font-semibold">Editar Afectación {afectacion.folio}</h1>
                        <DeleteDialog
                            title="Eliminar afectación"
                            description={`¿Estás seguro de eliminar "${afectacion.folio}"? Esta acción no se puede deshacer.`}
                            deleteUrl={`/admin/costos/afectaciones/${afectacion.id}`}
                        />
                    </div>

                    <EditLockBanner state={lockState} />

                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Info General */}
                        <div className="space-y-4">
                            <h2 className="text-lg font-medium border-b border-base-300 pb-2">Información General</h2>

                            <div className="grid grid-cols-2 gap-4">
                                <FormField label="Fecha" htmlFor="fecha" error={errors.fecha} required>
                                    <Input
                                        id="fecha"
                                        type="date"
                                        value={data.fecha}
                                        onChange={(e) => setData('fecha', e.target.value)}
                                    />
                                </FormField>

                                <FormField label="Tipo de Origen" htmlFor="tipo_origen" error={errors.tipo_origen} required>
                                    <select
                                        id="tipo_origen"
                                        className="select select-bordered w-full"
                                        value={data.tipo_origen}
                                        onChange={(e) => setData('tipo_origen', e.target.value)}
                                    >
                                        <option value="">Seleccionar</option>
                                        <option value="nomina">Nómina</option>
                                        <option value="gasto_directo">Gasto Directo</option>
                                        <option value="reembolso">Reembolso</option>
                                        <option value="ajuste_presupuestal">Ajuste Presupuestal</option>
                                        <option value="otro">Otro</option>
                                    </select>
                                </FormField>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <FormField label="Departamento" htmlFor="departamento_id" error={errors.departamento_id} required>
                                    <select
                                        id="departamento_id"
                                        className="select select-bordered w-full"
                                        value={data.departamento_id}
                                        onChange={(e) => setData('departamento_id', e.target.value)}
                                    >
                                        <option value="">Seleccionar</option>
                                        {departamentos.map((d) => (
                                            <option key={d.id} value={d.id}>{d.descripcion}</option>
                                        ))}
                                    </select>
                                </FormField>

                                <FormField label="Proveedor" htmlFor="proveedor_id" error={errors.proveedor_id}>
                                    <select
                                        id="proveedor_id"
                                        className="select select-bordered w-full"
                                        value={data.proveedor_id}
                                        onChange={(e) => setData('proveedor_id', e.target.value)}
                                    >
                                        <option value="">Sin proveedor</option>
                                        {proveedores.map((p) => (
                                            <option key={p.id} value={p.id}>{p.nombre_comercial || p.razon_social}</option>
                                        ))}
                                    </select>
                                </FormField>
                            </div>

                            <FormField label="Descripción" htmlFor="descripcion" error={errors.descripcion} required>
                                <textarea
                                    id="descripcion"
                                    className="textarea textarea-bordered w-full"
                                    value={data.descripcion}
                                    onChange={(e) => setData('descripcion', e.target.value)}
                                    rows={3}
                                />
                            </FormField>
                        </div>

                        {/* Detalles */}
                        <div className="space-y-4">
                            <div className="flex items-center justify-between border-b border-base-300 pb-2">
                                <h2 className="text-lg font-medium">Detalles / Centros de Costos</h2>
                                <Button type="button" variant="outline" onClick={addDetalle}>
                                    <PlusIcon className="size-4" />
                                    Agregar
                                </Button>
                            </div>

                            {data.detalles.map((det, index) => {
                                const monto = calcMonto(det);
                                const disponible = getDisponible(det.obra_rubro_id);
                                const excede = disponible !== null && monto > disponible;

                                return (
                                    <div key={index} className="rounded-lg border border-base-300 p-4 space-y-3">
                                        <div className="flex items-start justify-between">
                                            <h3 className="font-medium">Detalle {index + 1}</h3>
                                            <button type="button" className="btn btn-ghost btn-sm text-error" onClick={() => removeDetalle(index)}>
                                                <Trash2Icon className="size-4" />
                                            </button>
                                        </div>

                                        <FormField label="Centro de Costos" htmlFor={`det_rubro_${index}`} required>
                                            <select
                                                id={`det_rubro_${index}`}
                                                className="select select-bordered w-full"
                                                value={det.obra_rubro_id}
                                                onChange={(e) => updateDetalle(index, 'obra_rubro_id', e.target.value)}
                                            >
                                                <option value="">Seleccionar centro de costos</option>
                                                {obraRubros.map((or) => (
                                                    <option key={or.id} value={or.id}>
                                                        {or.rubro?.codigo} - {or.rubro?.descripcion}
                                                    </option>
                                                ))}
                                            </select>
                                        </FormField>

                                        {disponible !== null && (
                                            <p className={`text-xs ${excede ? 'text-error' : 'text-base-content/60'}`}>
                                                Disponible: ${disponible.toLocaleString('es-MX', { minimumFractionDigits: 2 })}
                                                {excede && (
                                                    <span className="inline-flex items-center gap-1 ml-2">
                                                        <AlertTriangleIcon className="size-3" /> Excede presupuesto
                                                    </span>
                                                )}
                                            </p>
                                        )}

                                        <FormField label="Concepto" htmlFor={`det_concepto_${index}`} required>
                                            <Input
                                                id={`det_concepto_${index}`}
                                                value={det.concepto}
                                                onChange={(e) => updateDetalle(index, 'concepto', e.target.value)}
                                            />
                                        </FormField>

                                        <div className="grid grid-cols-3 gap-4">
                                            <FormField label="Cantidad" htmlFor={`det_cant_${index}`} required>
                                                <Input
                                                    id={`det_cant_${index}`}
                                                    type="number"
                                                    step="0.01"
                                                    min="0.01"
                                                    value={det.cantidad}
                                                    onChange={(e) => updateDetalle(index, 'cantidad', e.target.value)}
                                                />
                                            </FormField>
                                            <FormField label="Precio Unitario" htmlFor={`det_precio_${index}`} required>
                                                <Input
                                                    id={`det_precio_${index}`}
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    value={det.precio_unitario}
                                                    onChange={(e) => updateDetalle(index, 'precio_unitario', e.target.value)}
                                                />
                                            </FormField>
                                            <FormField label="Monto" htmlFor={`det_monto_${index}`}>
                                                <Input
                                                    id={`det_monto_${index}`}
                                                    readOnly
                                                    value={`$${monto.toLocaleString('es-MX', { minimumFractionDigits: 2 })}`}
                                                    className="bg-base-200"
                                                />
                                            </FormField>
                                        </div>
                                    </div>
                                );
                            })}

                            {data.detalles.length > 0 && (
                                <div className="text-right text-lg font-semibold">
                                    Total: ${total.toLocaleString('es-MX', { minimumFractionDigits: 2 })}
                                </div>
                            )}
                        </div>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/costos/afectaciones">Cancelar</Link>
                            </Button>
                            <Button type="submit" disabled={processing || readonly}>
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
