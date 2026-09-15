import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosObraRubro, Departamento, Obra, Proveedor } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangleIcon, Loader2Icon, PlusIcon, Trash2Icon } from 'lucide-react';
import { type FormEvent, useMemo } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/ordenes-compra' },
    { title: 'Ordenes de Compra', href: '/admin/costos/ordenes-compra' },
    { title: 'Nueva', href: '/admin/costos/ordenes-compra/create' },
];

type DetalleForm = {
    obra_id: string;
    obra_rubro_id: string;
    descripcion: string;
    unidad: string;
    cantidad: string;
    precio_unitario: string;
};

type Props = {
    proveedores: Proveedor[];
    obras: Obra[];
    departamentos: Departamento[];
    obraRubros: CostosObraRubro[];
};

const unidadesSugeridas = ['pza', 'kg', 'm', 'm2', 'm3', 'lt', 'ton', 'hr', 'lote', 'servicio'];

function emptyDetalle(): DetalleForm {
    return {
        obra_id: '',
        obra_rubro_id: '',
        descripcion: '',
        unidad: 'pza',
        cantidad: '1',
        precio_unitario: '',
    };
}

function subtotalDe(d: DetalleForm): number {
    return (parseFloat(d.cantidad) || 0) * (parseFloat(d.precio_unitario) || 0);
}

export default function OrdenesCompraCreate({ proveedores, obras, departamentos, obraRubros }: Props) {
    const { data, setData, post, processing, errors } = useForm<{
        referencia: string;
        proveedor_id: string;
        departamento_id: string;
        moneda: string;
        total: string;
        fecha_entrega_esperada: string;
        notas: string;
        archivo: File | null;
        detalles: DetalleForm[];
    }>({
        referencia: '',
        proveedor_id: '',
        departamento_id: '',
        moneda: 'mxn',
        total: '0',
        fecha_entrega_esperada: '',
        notas: '',
        archivo: null,
        detalles: [emptyDetalle()],
    });

    const totalCalculado = useMemo(
        () => data.detalles.reduce((acc, d) => acc + subtotalDe(d), 0),
        [data.detalles],
    );

    const addDetalle = () => {
        setData('detalles', [...data.detalles, emptyDetalle()]);
    };

    const removeDetalle = (index: number) => {
        setData('detalles', data.detalles.filter((_, i) => i !== index));
    };

    const updateDetalle = (index: number, field: keyof DetalleForm, value: string) => {
        const updated = [...data.detalles];
        updated[index] = { ...updated[index], [field]: value };
        if (field === 'obra_id') {
            updated[index].obra_rubro_id = '';
        }
        setData('detalles', updated);
    };

    const formatMoney = (n: number) => n.toLocaleString('es-MX', { minimumFractionDigits: 2 });

    const getDisponible = (obraRubroId: string) => {
        const or = obraRubros.find((r) => r.id === Number(obraRubroId));
        if (!or) return null;
        return Number(or.presupuestado) - Number(or.acumulado);
    };

    const getRubroOptionLabel = (or: CostosObraRubro) => {
        const disp = Number(or.presupuestado) - Number(or.acumulado);
        const prefix = `${or.rubro?.codigo} - ${or.rubro?.descripcion}`;
        if (disp <= 0) return `${prefix}  |  SOBREGIRO: -$${formatMoney(Math.abs(disp))}`;
        return `${prefix}  |  Disp: $${formatMoney(disp)}`;
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        setData('total', totalCalculado.toFixed(2));
        post('/admin/costos/ordenes-compra', { forceFormData: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva Orden de Compra" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nueva Orden de Compra</h1>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="space-y-4">
                            <h2 className="text-lg font-medium border-b border-base-300 pb-2">Información General</h2>

                            <div className="grid grid-cols-2 gap-4">
                                <FormField label="Proveedor" htmlFor="proveedor_id" error={errors.proveedor_id} required>
                                    <select
                                        id="proveedor_id"
                                        className="select select-bordered w-full"
                                        value={data.proveedor_id}
                                        onChange={(e) => setData('proveedor_id', e.target.value)}
                                    >
                                        <option value="">Seleccionar</option>
                                        {proveedores.map((p) => (
                                            <option key={p.id} value={p.id}>{p.nombre_comercial || p.razon_social}</option>
                                        ))}
                                    </select>
                                </FormField>

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
                            </div>

                            <FormField label="Folio Referencia" htmlFor="referencia" error={errors.referencia}>
                                <Input
                                    id="referencia"
                                    value={data.referencia}
                                    onChange={(e) => setData('referencia', e.target.value)}
                                    placeholder="Folio del documento externo"
                                />
                            </FormField>

                            <div className="grid grid-cols-2 gap-4">
                                <FormField label="Moneda" htmlFor="moneda" error={errors.moneda} required>
                                    <select
                                        id="moneda"
                                        className="select select-bordered w-full"
                                        value={data.moneda}
                                        onChange={(e) => setData('moneda', e.target.value)}
                                    >
                                        <option value="mxn">MXN</option>
                                        <option value="usd">USD</option>
                                        <option value="eur">EUR</option>
                                    </select>
                                </FormField>

                                <FormField label="Fecha de Entrega Esperada" htmlFor="fecha_entrega_esperada" error={errors.fecha_entrega_esperada} required>
                                    <Input
                                        id="fecha_entrega_esperada"
                                        type="date"
                                        value={data.fecha_entrega_esperada}
                                        onChange={(e) => setData('fecha_entrega_esperada', e.target.value)}
                                        required
                                    />
                                </FormField>
                            </div>

                            <FormField label="Notas" htmlFor="notas" error={errors.notas}>
                                <textarea
                                    id="notas"
                                    className="textarea textarea-bordered w-full"
                                    value={data.notas}
                                    onChange={(e) => setData('notas', e.target.value)}
                                    rows={2}
                                />
                            </FormField>

                            <FormField label="Archivo" htmlFor="archivo" error={errors.archivo}>
                                <input
                                    id="archivo"
                                    type="file"
                                    className="file-input file-input-bordered w-full"
                                    onChange={(e) => setData('archivo', e.target.files?.[0] ?? null)}
                                />
                            </FormField>
                        </div>

                        {/* Partidas */}
                        <div className="space-y-4">
                            <div className="flex items-center justify-between border-b border-base-300 pb-2">
                                <h2 className="text-lg font-medium">Partidas</h2>
                                <Button type="button" variant="outline" onClick={addDetalle}>
                                    <PlusIcon className="size-4" />
                                    Agregar partida
                                </Button>
                            </div>

                            {data.detalles.map((det, index) => {
                                const subtotal = subtotalDe(det);
                                const disponible = getDisponible(det.obra_rubro_id);
                                const excede = disponible !== null && subtotal > disponible;

                                return (
                                    <div key={index} className="rounded-lg border border-base-300 p-4 space-y-3">
                                        <div className="flex items-start justify-between">
                                            <h3 className="font-medium">Partida {index + 1}</h3>
                                            {data.detalles.length > 1 && (
                                                <button type="button" className="btn btn-ghost btn-sm text-error" onClick={() => removeDetalle(index)}>
                                                    <Trash2Icon className="size-4" />
                                                </button>
                                            )}
                                        </div>

                                        <div className="grid grid-cols-2 gap-4">
                                            <FormField label="Obra" htmlFor={`det_obra_${index}`} required>
                                                <select
                                                    id={`det_obra_${index}`}
                                                    className="select select-bordered w-full"
                                                    value={det.obra_id}
                                                    onChange={(e) => updateDetalle(index, 'obra_id', e.target.value)}
                                                >
                                                    <option value="">Seleccionar obra</option>
                                                    {obras.map((o) => (
                                                        <option key={o.id} value={o.id}>{o.no} - {o.descripcion}</option>
                                                    ))}
                                                </select>
                                            </FormField>

                                            <FormField label="Centro de Costos" htmlFor={`det_rubro_${index}`} error={errors[`detalles.${index}.obra_rubro_id` as keyof typeof errors]} required>
                                                <select
                                                    id={`det_rubro_${index}`}
                                                    className="select select-bordered w-full"
                                                    value={det.obra_rubro_id}
                                                    onChange={(e) => updateDetalle(index, 'obra_rubro_id', e.target.value)}
                                                    disabled={!det.obra_id}
                                                >
                                                    <option value="">{det.obra_id ? 'Seleccionar centro de costos' : 'Seleccione obra primero'}</option>
                                                    {obraRubros
                                                        .filter((or) => or.obra_id === Number(det.obra_id))
                                                        .map((or) => (
                                                            <option key={or.id} value={or.id}>{getRubroOptionLabel(or)}</option>
                                                        ))}
                                                </select>
                                            </FormField>
                                        </div>

                                        <FormField label="Descripción" htmlFor={`det_desc_${index}`} error={errors[`detalles.${index}.descripcion` as keyof typeof errors]} required>
                                            <Input
                                                id={`det_desc_${index}`}
                                                value={det.descripcion}
                                                onChange={(e) => updateDetalle(index, 'descripcion', e.target.value)}
                                                placeholder="p.ej. Cemento gris CPC 30R 50kg"
                                            />
                                        </FormField>

                                        <div className="grid grid-cols-4 gap-3">
                                            <FormField label="Unidad" htmlFor={`det_unidad_${index}`} error={errors[`detalles.${index}.unidad` as keyof typeof errors]} required>
                                                <input
                                                    id={`det_unidad_${index}`}
                                                    className="input input-bordered w-full"
                                                    list={`unidades-${index}`}
                                                    value={det.unidad}
                                                    onChange={(e) => updateDetalle(index, 'unidad', e.target.value)}
                                                    maxLength={20}
                                                />
                                                <datalist id={`unidades-${index}`}>
                                                    {unidadesSugeridas.map((u) => (
                                                        <option key={u} value={u} />
                                                    ))}
                                                </datalist>
                                            </FormField>

                                            <FormField label="Cantidad" htmlFor={`det_cantidad_${index}`} error={errors[`detalles.${index}.cantidad` as keyof typeof errors]} required>
                                                <Input
                                                    id={`det_cantidad_${index}`}
                                                    type="number"
                                                    step="0.0001"
                                                    min="0.0001"
                                                    value={det.cantidad}
                                                    onChange={(e) => updateDetalle(index, 'cantidad', e.target.value)}
                                                />
                                            </FormField>

                                            <FormField label="Precio unitario" htmlFor={`det_precio_${index}`} error={errors[`detalles.${index}.precio_unitario` as keyof typeof errors]} required>
                                                <Input
                                                    id={`det_precio_${index}`}
                                                    type="number"
                                                    step="0.0001"
                                                    min="0"
                                                    value={det.precio_unitario}
                                                    onChange={(e) => updateDetalle(index, 'precio_unitario', e.target.value)}
                                                />
                                            </FormField>

                                            <FormField label="Subtotal" htmlFor={`det_subtotal_${index}`}>
                                                <Input
                                                    id={`det_subtotal_${index}`}
                                                    value={`$${formatMoney(subtotal)}`}
                                                    readOnly
                                                    className="bg-base-200"
                                                />
                                            </FormField>
                                        </div>

                                        {excede && (
                                            <div className="rounded-lg bg-warning/10 border border-warning/30 px-3 py-2 text-xs text-warning inline-flex items-center gap-1">
                                                <AlertTriangleIcon className="size-3" /> El subtotal (${formatMoney(subtotal)}) excede el disponible (${formatMoney(disponible!)})
                                            </div>
                                        )}
                                    </div>
                                );
                            })}

                            <div className="flex justify-end border-t border-base-300 pt-3">
                                <div className="text-right">
                                    <div className="text-sm text-base-content/60">Total de la OC</div>
                                    <div className="text-2xl font-semibold">${formatMoney(totalCalculado)}</div>
                                </div>
                            </div>
                        </div>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/costos/ordenes-compra">Cancelar</Link>
                            </Button>
                            <Button type="submit" disabled={processing || totalCalculado <= 0}>
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
