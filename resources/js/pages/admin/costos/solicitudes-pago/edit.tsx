import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosObraRubro, CostosSolicitudPago, CostosTipoSolicitud, Departamento, Obra, Proveedor } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangleIcon, Loader2Icon, PlusIcon, Trash2Icon } from 'lucide-react';
import { type FormEvent, useMemo } from 'react';

type DetalleForm = {
    id?: number;
    obra_rubro_id: string;
    concepto: string;
    cantidad: string;
    precio_unitario: string;
};

type Props = {
    solicitud: CostosSolicitudPago;
    departamentos: Departamento[];
    proveedores: Proveedor[];
    tipoSolicitudes: CostosTipoSolicitud[];
    obras: Obra[];
    obraRubros: CostosObraRubro[];
};

export default function SolicitudesPagoEdit({ solicitud, departamentos, proveedores, tipoSolicitudes, obras, obraRubros }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/solicitudes-pago' },
        { title: 'Solicitudes Pago', href: '/admin/costos/solicitudes-pago' },
        { title: solicitud.folio, href: `/admin/costos/solicitudes-pago/${solicitud.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm<{
        departamento_id: string;
        proveedor_id: string;
        tipo_solicitud_id: string;
        concepto: string;
        justificacion: string;
        tipo_pago: string;
        fecha_pago_solicitada: string;
        detalles: DetalleForm[];
    }>({
        departamento_id: String(solicitud.departamento_id),
        proveedor_id: solicitud.proveedor_id ? String(solicitud.proveedor_id) : '',
        tipo_solicitud_id: String(solicitud.tipo_solicitud_id),
        concepto: solicitud.concepto,
        justificacion: solicitud.justificacion ?? '',
        tipo_pago: solicitud.tipo_pago,
        fecha_pago_solicitada: solicitud.fecha_pago_solicitada ?? '',
        detalles: (solicitud.detalles ?? []).map((d) => ({
            id: d.id,
            obra_rubro_id: String(d.obra_rubro_id),
            concepto: d.concepto,
            cantidad: String(d.cantidad),
            precio_unitario: String(d.precio_unitario),
        })),
    });

    const selectedTipo = useMemo(
        () => tipoSolicitudes.find((t) => t.id === Number(data.tipo_solicitud_id)),
        [data.tipo_solicitud_id, tipoSolicitudes],
    );

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

    const calcSubtotal = (d: DetalleForm) => {
        const cant = parseFloat(d.cantidad) || 0;
        const precio = parseFloat(d.precio_unitario) || 0;
        return cant * precio;
    };

    const total = data.detalles.reduce((sum, d) => sum + calcSubtotal(d), 0);

    const getDisponible = (obraRubroId: string) => {
        const or = obraRubros.find((r) => r.id === Number(obraRubroId));
        if (!or) {
            return null;
        }
        return Number(or.presupuestado) - Number(or.acumulado);
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/costos/solicitudes-pago/${solicitud.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${solicitud.folio}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <div className="mb-6 flex items-center justify-between">
                        <h1 className="text-2xl font-semibold">Editar Solicitud {solicitud.folio}</h1>
                        <DeleteDialog
                            title="Eliminar solicitud"
                            description={`¿Estás seguro de eliminar "${solicitud.folio}"? Esta acción no se puede deshacer.`}
                            deleteUrl={`/admin/costos/solicitudes-pago/${solicitud.id}`}
                        />
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Sección 1: Info Básica */}
                        <div className="space-y-4">
                            <h2 className="text-lg font-medium border-b border-base-300 pb-2">Información Básica</h2>

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

                            <FormField label="Concepto" htmlFor="concepto" error={errors.concepto} required>
                                <textarea
                                    id="concepto"
                                    className="textarea textarea-bordered w-full"
                                    value={data.concepto}
                                    onChange={(e) => setData('concepto', e.target.value)}
                                    rows={2}
                                />
                            </FormField>

                            <FormField label="Justificación" htmlFor="justificacion" error={errors.justificacion}>
                                <textarea
                                    id="justificacion"
                                    className="textarea textarea-bordered w-full"
                                    value={data.justificacion}
                                    onChange={(e) => setData('justificacion', e.target.value)}
                                    rows={2}
                                />
                            </FormField>

                            <div className="grid grid-cols-2 gap-4">
                                <FormField label="Tipo de Pago" htmlFor="tipo_pago" error={errors.tipo_pago} required>
                                    <select
                                        id="tipo_pago"
                                        className="select select-bordered w-full"
                                        value={data.tipo_pago}
                                        onChange={(e) => setData('tipo_pago', e.target.value)}
                                    >
                                        <option value="transferencia">Transferencia</option>
                                        <option value="cheque">Cheque</option>
                                        <option value="efectivo">Efectivo</option>
                                    </select>
                                </FormField>

                                <FormField label="Fecha de Pago Solicitada" htmlFor="fecha_pago_solicitada" error={errors.fecha_pago_solicitada}>
                                    <Input
                                        id="fecha_pago_solicitada"
                                        type="date"
                                        value={data.fecha_pago_solicitada}
                                        onChange={(e) => setData('fecha_pago_solicitada', e.target.value)}
                                    />
                                </FormField>
                            </div>
                        </div>

                        {/* Sección 2: Tipo Solicitud */}
                        <div className="space-y-4">
                            <h2 className="text-lg font-medium border-b border-base-300 pb-2">Tipo de Solicitud</h2>
                            <FormField label="Tipo de Solicitud" htmlFor="tipo_solicitud_id" error={errors.tipo_solicitud_id} required>
                                <select
                                    id="tipo_solicitud_id"
                                    className="select select-bordered w-full"
                                    value={data.tipo_solicitud_id}
                                    onChange={(e) => setData('tipo_solicitud_id', e.target.value)}
                                >
                                    <option value="">Seleccionar tipo</option>
                                    {tipoSolicitudes.map((ts) => (
                                        <option key={ts.id} value={ts.id}>{ts.titulo}</option>
                                    ))}
                                </select>
                            </FormField>
                        </div>

                        {/* Sección 3: Detalles/Rubros */}
                        {selectedTipo?.rubros && (
                            <div className="space-y-4">
                                <div className="flex items-center justify-between border-b border-base-300 pb-2">
                                    <h2 className="text-lg font-medium">Detalles / Rubros</h2>
                                    <Button type="button" variant="outline" onClick={addDetalle}>
                                        <PlusIcon className="size-4" />
                                        Agregar
                                    </Button>
                                </div>

                                {data.detalles.map((det, index) => {
                                    const subtotal = calcSubtotal(det);
                                    const disponible = getDisponible(det.obra_rubro_id);
                                    const excede = disponible !== null && subtotal > disponible;

                                    return (
                                        <div key={index} className="rounded-lg border border-base-300 p-4 space-y-3">
                                            <div className="flex items-start justify-between">
                                                <h3 className="font-medium">Detalle {index + 1}</h3>
                                                <button type="button" className="btn btn-ghost btn-sm text-error" onClick={() => removeDetalle(index)}>
                                                    <Trash2Icon className="size-4" />
                                                </button>
                                            </div>

                                            <FormField label="Rubro" htmlFor={`det_rubro_${index}`} required>
                                                <select
                                                    id={`det_rubro_${index}`}
                                                    className="select select-bordered w-full"
                                                    value={det.obra_rubro_id}
                                                    onChange={(e) => updateDetalle(index, 'obra_rubro_id', e.target.value)}
                                                >
                                                    <option value="">Seleccionar rubro</option>
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
                                                <FormField label="Subtotal" htmlFor={`det_sub_${index}`}>
                                                    <Input
                                                        id={`det_sub_${index}`}
                                                        readOnly
                                                        value={`$${subtotal.toLocaleString('es-MX', { minimumFractionDigits: 2 })}`}
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
                        )}

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/costos/solicitudes-pago">Cancelar</Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
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
