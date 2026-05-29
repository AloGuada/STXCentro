import { Head, useForm } from '@inertiajs/react';
import { PlusIcon, Trash2Icon } from 'lucide-react';
import { RubroSelector } from '@/components/costos/rubro-selector';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosRequisicion, CostosUsoCfdi, Departamento, Obra, ObraRubroOption } from '@/types/models';

type Detalle = {
    id?: number;
    descripcion: string;
    codigo_producto: string;
    unidad: string;
    cantidad: number;
    obra_rubro_id: number | '';
    uso_cfdi_id: number | '';
    tipo_fiscal: string;
    notas: string;
};

type FormData = {
    departamento_id: number;
    obra_id: number | '';
    justificacion: string;
    detalles: Detalle[];
    _version: string;
};

type Props = {
    requisicion: CostosRequisicion;
    departamentos: Pick<Departamento, 'id' | 'descripcion'>[];
    obras: Pick<Obra, 'id' | 'no' | 'descripcion'>[];
    obraRubros: ObraRubroOption[];
    usosCfdi: Pick<CostosUsoCfdi, 'id' | 'clave' | 'descripcion'>[];
};

export default function RequisicionesEdit({ requisicion, departamentos, obras, obraRubros, usosCfdi }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/requisiciones' },
        { title: 'Requisiciones', href: '/admin/costos/requisiciones' },
        { title: requisicion.folio, href: `/admin/costos/requisiciones/${requisicion.id}` },
        { title: 'Editar', href: `/admin/costos/requisiciones/${requisicion.id}/edit` },
    ];

    const defaultUsoId = usosCfdi.find((u) => u.clave === 'G01')?.id ?? '';

    const { data, setData, put, processing, errors } = useForm<FormData>({
        departamento_id: requisicion.departamento_id,
        obra_id: requisicion.obra_id ?? '',
        justificacion: requisicion.justificacion ?? '',
        detalles: (requisicion.detalles ?? []).map((d) => ({
            id: d.id,
            descripcion: d.descripcion,
            codigo_producto: d.codigo_producto ?? '',
            unidad: d.unidad,
            cantidad: Number(d.cantidad),
            obra_rubro_id: d.obra_rubro_id ?? '',
            uso_cfdi_id: d.uso_cfdi_id ?? '',
            tipo_fiscal: d.tipo_fiscal ?? 'mercancia',
            notas: d.notas ?? '',
        })),
        _version: requisicion.updated_at,
    });

    const rubrosDeObra = data.obra_id ? obraRubros.filter((r) => r.obra_id === data.obra_id) : [];

    const setObra = (value: number | '') => {
        setData((prev) => ({
            ...prev,
            obra_id: value,
            detalles: prev.detalles.map((d) => ({ ...d, obra_rubro_id: '' })),
        }));
    };

    const addDetalle = () => {
        setData('detalles', [...data.detalles, { descripcion: '', codigo_producto: '', unidad: 'pza', cantidad: 1, obra_rubro_id: '', uso_cfdi_id: defaultUsoId, tipo_fiscal: 'mercancia', notas: '' }]);
    };

    const removeDetalle = (idx: number) => setData('detalles', data.detalles.filter((_, i) => i !== idx));
    const updateDetalle = (idx: number, field: keyof Detalle, value: string | number) => {
        setData('detalles', data.detalles.map((d, i) => (i === idx ? { ...d, [field]: value } : d)));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/costos/requisiciones/${requisicion.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${requisicion.folio}`} />

            <form onSubmit={handleSubmit} className="p-6">
                <h1 className="mb-4 text-2xl font-semibold">Editar {requisicion.folio}</h1>

                <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label className="label label-text">Departamento *</label>
                        <select
                            className="select select-bordered w-full"
                            value={data.departamento_id}
                            onChange={(e) => setData('departamento_id', Number(e.target.value))}
                        >
                            {departamentos.map((d) => (
                                <option key={d.id} value={d.id}>{d.descripcion}</option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className="label label-text">Obra / Proyecto *</label>
                        <select
                            className="select select-bordered w-full"
                            value={data.obra_id}
                            onChange={(e) => setObra(e.target.value ? Number(e.target.value) : '')}
                        >
                            <option value="">Selecciona una obra</option>
                            {obras.map((o) => (
                                <option key={o.id} value={o.id}>{o.no ? `OP-${o.no} · ` : ''}{o.descripcion}</option>
                            ))}
                        </select>
                        {errors.obra_id && <p className="text-error text-sm mt-1">{errors.obra_id}</p>}
                    </div>

                    <div className="md:col-span-2">
                        <label className="label label-text">Justificación</label>
                        <textarea
                            className="textarea textarea-bordered w-full"
                            rows={3}
                            value={data.justificacion}
                            onChange={(e) => setData('justificacion', e.target.value)}
                        />
                    </div>
                </div>

                <div className="mb-3 flex items-center justify-between">
                    <h2 className="text-lg font-medium">Partidas</h2>
                    <Button type="button" variant="outline" onClick={addDetalle}>
                        <PlusIcon className="size-3.5" /> Agregar partida
                    </Button>
                </div>

                {!data.obra_id && (
                    <div className="alert alert-info mb-3">
                        <span>Selecciona primero la obra para asignar el rubro (centro de costo) de cada partida.</span>
                    </div>
                )}

                <div className="overflow-x-auto rounded-lg border border-base-300">
                    <table className="table table-sm">
                        <thead>
                            <tr>
                                <th>Descripción *</th>
                                <th className="w-36">Código producto</th>
                                <th className="min-w-[200px]">Rubro (C. Costo) *</th>
                                <th className="min-w-[180px]">Uso CFDI *</th>
                                <th className="min-w-[150px]">Tipo fiscal</th>
                                <th className="w-24">Unidad</th>
                                <th className="w-28 text-right">Cantidad *</th>
                                <th>Notas</th>
                                <th className="w-12"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.detalles.map((d, i) => (
                                <tr key={d.id ?? `new-${i}`}>
                                    <td>
                                        <input
                                            type="text"
                                            className="input input-bordered input-sm w-full"
                                            value={d.descripcion}
                                            onChange={(e) => updateDetalle(i, 'descripcion', e.target.value)}
                                        />
                                    </td>
                                    <td>
                                        <input
                                            type="text"
                                            className="input input-bordered input-sm w-full"
                                            value={d.codigo_producto}
                                            onChange={(e) => updateDetalle(i, 'codigo_producto', e.target.value)}
                                        />
                                    </td>
                                    <td>
                                        <RubroSelector
                                            value={d.obra_rubro_id}
                                            options={rubrosDeObra}
                                            rubroOnly
                                            disabled={!data.obra_id}
                                            onChange={(value) => updateDetalle(i, 'obra_rubro_id', value)}
                                        />
                                        {errors[`detalles.${i}.obra_rubro_id` as keyof typeof errors] && (
                                            <p className="text-error text-xs mt-1">{errors[`detalles.${i}.obra_rubro_id` as keyof typeof errors]}</p>
                                        )}
                                    </td>
                                    <td>
                                        <select
                                            className="select select-bordered select-sm w-full"
                                            value={d.uso_cfdi_id}
                                            onChange={(e) => updateDetalle(i, 'uso_cfdi_id', e.target.value ? Number(e.target.value) : '')}
                                        >
                                            <option value="">Selecciona...</option>
                                            {usosCfdi.map((u) => (
                                                <option key={u.id} value={u.id}>{u.clave} - {u.descripcion}</option>
                                            ))}
                                        </select>
                                        {errors[`detalles.${i}.uso_cfdi_id` as keyof typeof errors] && (
                                            <p className="text-error text-xs mt-1">{errors[`detalles.${i}.uso_cfdi_id` as keyof typeof errors]}</p>
                                        )}
                                    </td>
                                    <td>
                                        <select
                                            className="select select-bordered select-sm w-full"
                                            value={d.tipo_fiscal}
                                            onChange={(e) => updateDetalle(i, 'tipo_fiscal', e.target.value)}
                                        >
                                            <option value="mercancia">Mercancía</option>
                                            <option value="flete">Flete</option>
                                            <option value="servicio_profesional">Servicio profesional</option>
                                            <option value="renta">Renta</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input
                                            type="text"
                                            className="input input-bordered input-sm w-full"
                                            value={d.unidad}
                                            onChange={(e) => updateDetalle(i, 'unidad', e.target.value)}
                                        />
                                    </td>
                                    <td>
                                        <input
                                            type="number"
                                            step="0.01"
                                            className="input input-bordered input-sm w-full text-right"
                                            value={d.cantidad}
                                            onChange={(e) => updateDetalle(i, 'cantidad', Number(e.target.value))}
                                        />
                                    </td>
                                    <td>
                                        <input
                                            type="text"
                                            className="input input-bordered input-sm w-full"
                                            value={d.notas}
                                            onChange={(e) => updateDetalle(i, 'notas', e.target.value)}
                                        />
                                    </td>
                                    <td>
                                        {data.detalles.length > 1 && (
                                            <button
                                                type="button"
                                                onClick={() => removeDetalle(i)}
                                                className="btn btn-ghost btn-sm text-error"
                                            >
                                                <Trash2Icon className="size-3.5" />
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {errors.detalles && <p className="text-error text-sm mt-2">{errors.detalles}</p>}

                <div className="mt-6 flex justify-end gap-2">
                    <Button type="button" variant="outline" asChild>
                        <a href={`/admin/costos/requisiciones/${requisicion.id}`}>Cancelar</a>
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Guardando...' : 'Guardar cambios'}
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
