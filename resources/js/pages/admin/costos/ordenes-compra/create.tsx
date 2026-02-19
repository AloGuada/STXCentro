import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosObraRubro, Departamento, Obra, Proveedor } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangleIcon, Loader2Icon, PlusIcon, Trash2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/ordenes-compra' },
    { title: 'Ordenes de Compra', href: '/admin/costos/ordenes-compra' },
    { title: 'Nueva', href: '/admin/costos/ordenes-compra/create' },
];

type DetalleForm = {
    obra_id: string;
    obra_rubro_id: string;
    monto: string;
};

type Props = {
    proveedores: Proveedor[];
    obras: Obra[];
    departamentos: Departamento[];
    obraRubros: CostosObraRubro[];
};

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
        total: '',
        fecha_entrega_esperada: '',
        notas: '',
        archivo: null,
        detalles: [{ obra_id: '', obra_rubro_id: '', monto: '' }],
    });

    const addDetalle = () => {
        setData('detalles', [...data.detalles, { obra_id: '', obra_rubro_id: '', monto: '' }]);
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

                                <FormField label="Total de la OC" htmlFor="total" error={errors.total} required>
                                    <Input
                                        id="total"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        value={data.total}
                                        onChange={(e) => setData('total', e.target.value)}
                                        placeholder="Monto total de la orden"
                                    />
                                </FormField>

                                <FormField label="Fecha de Entrega Esperada" htmlFor="fecha_entrega_esperada" error={errors.fecha_entrega_esperada}>
                                    <Input
                                        id="fecha_entrega_esperada"
                                        type="date"
                                        value={data.fecha_entrega_esperada}
                                        onChange={(e) => setData('fecha_entrega_esperada', e.target.value)}
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

                        {/* Rubros */}
                        <div className="space-y-4">
                            <div className="flex items-center justify-between border-b border-base-300 pb-2">
                                <h2 className="text-lg font-medium">Rubros</h2>
                                <Button type="button" variant="outline" onClick={addDetalle}>
                                    <PlusIcon className="size-4" />
                                    Agregar
                                </Button>
                            </div>

                            {data.detalles.map((det, index) => {
                                const monto = parseFloat(det.monto) || 0;
                                const disponible = getDisponible(det.obra_rubro_id);
                                const excede = disponible !== null && monto > disponible;

                                return (
                                    <div key={index} className="rounded-lg border border-base-300 p-4 space-y-3">
                                        <div className="flex items-start justify-between">
                                            <h3 className="font-medium">Rubro {index + 1}</h3>
                                            {data.detalles.length > 1 && (
                                                <button type="button" className="btn btn-ghost btn-sm text-error" onClick={() => removeDetalle(index)}>
                                                    <Trash2Icon className="size-4" />
                                                </button>
                                            )}
                                        </div>

                                        <div className="grid grid-cols-3 gap-4">
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

                                            <FormField label="Rubro" htmlFor={`det_rubro_${index}`} error={errors[`detalles.${index}.obra_rubro_id` as keyof typeof errors]} required>
                                                <select
                                                    id={`det_rubro_${index}`}
                                                    className="select select-bordered w-full"
                                                    value={det.obra_rubro_id}
                                                    onChange={(e) => updateDetalle(index, 'obra_rubro_id', e.target.value)}
                                                    disabled={!det.obra_id}
                                                >
                                                    <option value="">{det.obra_id ? 'Seleccionar rubro' : 'Seleccione obra primero'}</option>
                                                    {obraRubros
                                                        .filter((or) => or.obra_id === Number(det.obra_id))
                                                        .map((or) => (
                                                            <option key={or.id} value={or.id}>{getRubroOptionLabel(or)}</option>
                                                        ))}
                                                </select>
                                            </FormField>

                                            <FormField label="Monto" htmlFor={`det_monto_${index}`} error={errors[`detalles.${index}.monto` as keyof typeof errors]} required>
                                                <Input
                                                    id={`det_monto_${index}`}
                                                    type="number"
                                                    step="0.01"
                                                    min="0.01"
                                                    value={det.monto}
                                                    onChange={(e) => updateDetalle(index, 'monto', e.target.value)}
                                                />
                                            </FormField>
                                        </div>

                                        {excede && (
                                            <div className="rounded-lg bg-warning/10 border border-warning/30 px-3 py-2 text-xs text-warning inline-flex items-center gap-1">
                                                <AlertTriangleIcon className="size-3" /> El monto (${formatMoney(monto)}) excede el disponible (${formatMoney(disponible!)})
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/costos/ordenes-compra">Cancelar</Link>
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
