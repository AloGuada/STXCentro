import { Head, useForm } from '@inertiajs/react';
import { PlusIcon, Trash2Icon } from 'lucide-react';
import { useState } from 'react';
import { RubroSelector } from '@/components/costos/rubro-selector';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type {
    CostosRequisicion,
    CostosUsoCfdi,
    Departamento,
    Obra,
    ObraRubroOption,
} from '@/types/models';

type Detalle = {
    id?: number;
    descripcion: string;
    unidad: string;
    cantidad: number;
    obra_rubro_id: number | '';
    uso_cfdi_id: number | '';
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
    obras: Pick<Obra, 'id' | 'no' | 'descripcion' | 'estatus'>[];
    obraRubros: ObraRubroOption[];
    usosCfdi: Pick<CostosUsoCfdi, 'id' | 'clave' | 'descripcion'>[];
};

export default function RequisicionesEdit({
    requisicion,
    departamentos,
    obras,
    obraRubros,
    usosCfdi,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/requisiciones' },
        { title: 'Requisiciones', href: '/admin/costos/requisiciones' },
        {
            title: requisicion.folio,
            href: `/admin/costos/requisiciones/${requisicion.id}`,
        },
        {
            title: 'Editar',
            href: `/admin/costos/requisiciones/${requisicion.id}/edit`,
        },
    ];

    const defaultUsoId = usosCfdi.find((u) => u.clave === 'G01')?.id ?? '';

    const { data, setData, put, processing, errors } = useForm<FormData>({
        departamento_id: requisicion.departamento_id,
        obra_id: requisicion.obra_id ?? '',
        justificacion: requisicion.justificacion ?? '',
        detalles: (requisicion.detalles ?? []).map((d) => ({
            id: d.id,
            descripcion: d.descripcion,
            unidad: d.unidad,
            cantidad: Number(d.cantidad),
            obra_rubro_id: d.obra_rubro_id ?? '',
            uso_cfdi_id: d.uso_cfdi_id ?? '',
            notas: d.notas ?? '',
        })),
        _version: requisicion.updated_at,
    });

    const [incluirCerradas, setIncluirCerradas] = useState(false);
    // Multiobra: se infiere de la requisición cargada (sin obra = multiobra).
    const [multiobra, setMultiobra] = useState(!requisicion.obra_id);
    const obrasVisibles = obras.filter(
        (o) => incluirCerradas || o.estatus !== 'cerrada',
    );
    const rubrosDeObra = data.obra_id
        ? obraRubros.filter(
              (r) =>
                  r.obra_id === data.obra_id && (incluirCerradas || !r.cerrado),
          )
        : [];
    const rubrosDisponibles = multiobra
        ? obraRubros.filter((r) => incluirCerradas || !r.cerrado)
        : rubrosDeObra;

    const setObra = (value: number | '') => {
        setData((prev) => ({
            ...prev,
            obra_id: value,
            detalles: prev.detalles.map((d) => ({ ...d, obra_rubro_id: '' })),
        }));
    };

    const toggleMultiobra = (on: boolean) => {
        setMultiobra(on);
        setData((prev) => ({
            ...prev,
            obra_id: on ? '' : prev.obra_id,
            detalles: prev.detalles.map((d) => ({ ...d, obra_rubro_id: '' })),
        }));
    };

    const addDetalle = () => {
        setData('detalles', [
            ...data.detalles,
            {
                descripcion: '',
                unidad: 'pza',
                cantidad: 1,
                obra_rubro_id: '',
                uso_cfdi_id: defaultUsoId,
                notas: '',
            },
        ]);
    };

    const removeDetalle = (idx: number) =>
        setData(
            'detalles',
            data.detalles.filter((_, i) => i !== idx),
        );
    const updateDetalle = (
        idx: number,
        field: keyof Detalle,
        value: string | number,
    ) => {
        setData(
            'detalles',
            data.detalles.map((d, i) =>
                i === idx ? { ...d, [field]: value } : d,
            ),
        );
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/costos/requisiciones/${requisicion.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${requisicion.folio}`} />

            <form onSubmit={handleSubmit} className="p-6">
                <h1 className="mb-4 text-2xl font-semibold">
                    Editar {requisicion.folio}
                </h1>

                <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label className="label-text label">
                            Departamento *
                        </label>
                        <select
                            className="select-bordered select w-full"
                            value={data.departamento_id}
                            onChange={(e) =>
                                setData(
                                    'departamento_id',
                                    Number(e.target.value),
                                )
                            }
                        >
                            {departamentos.map((d) => (
                                <option key={d.id} value={d.id}>
                                    {d.descripcion}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <div className="flex items-center justify-between">
                            <label className="label-text label">
                                Obra / Proyecto {!multiobra && '*'}
                            </label>
                            <div className="flex items-center gap-3">
                                <label className="label cursor-pointer gap-2 py-0">
                                    <input
                                        type="checkbox"
                                        className="checkbox checkbox-xs"
                                        checked={multiobra}
                                        onChange={(e) =>
                                            toggleMultiobra(e.target.checked)
                                        }
                                    />
                                    <span className="label-text text-xs">
                                        Multiobra
                                    </span>
                                </label>
                                <label className="label cursor-pointer gap-2 py-0">
                                    <input
                                        type="checkbox"
                                        className="checkbox checkbox-xs"
                                        checked={incluirCerradas}
                                        onChange={(e) =>
                                            setIncluirCerradas(e.target.checked)
                                        }
                                    />
                                    <span className="label-text text-xs">
                                        Incluir cerradas
                                    </span>
                                </label>
                            </div>
                        </div>
                        {multiobra ? (
                            <p className="text-sm text-base-content/60">
                                Requisición multiobra: cada partida elige su
                                centro de costos (con su obra).
                            </p>
                        ) : (
                            <>
                                <select
                                    className="select-bordered select w-full"
                                    value={data.obra_id}
                                    onChange={(e) =>
                                        setObra(
                                            e.target.value
                                                ? Number(e.target.value)
                                                : '',
                                        )
                                    }
                                >
                                    <option value="">
                                        Selecciona una obra
                                    </option>
                                    {obrasVisibles.map((o) => (
                                        <option key={o.id} value={o.id}>
                                            {o.no ? `OP-${o.no} · ` : ''}
                                            {o.descripcion}
                                            {o.estatus === 'cerrada'
                                                ? ' (Cerrada)'
                                                : ''}
                                        </option>
                                    ))}
                                </select>
                                {errors.obra_id && (
                                    <p className="mt-1 text-sm text-error">
                                        {errors.obra_id}
                                    </p>
                                )}
                            </>
                        )}
                    </div>

                    <div className="md:col-span-2">
                        <label className="label-text label">
                            Justificación
                        </label>
                        <textarea
                            className="textarea-bordered textarea w-full"
                            rows={3}
                            value={data.justificacion}
                            onChange={(e) =>
                                setData('justificacion', e.target.value)
                            }
                        />
                    </div>
                </div>

                <div className="mb-3 flex items-center justify-between">
                    <h2 className="text-lg font-medium">Partidas</h2>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={addDetalle}
                    >
                        <PlusIcon className="size-3.5" /> Agregar partida
                    </Button>
                </div>

                {!multiobra && !data.obra_id && (
                    <div className="mb-3 alert alert-info">
                        <span>
                            Selecciona primero la obra para asignar el centro de
                            costos de cada partida.
                        </span>
                    </div>
                )}

                <div className="overflow-x-auto rounded-lg border border-base-300">
                    <table className="table table-sm">
                        <thead>
                            <tr>
                                <th>Descripción *</th>
                                <th className="w-24">Unidad</th>
                                <th className="w-28 text-right">Cantidad *</th>
                                <th className="min-w-[200px]">
                                    Centro de Costo *
                                </th>
                                <th className="min-w-[180px]">Uso CFDI *</th>
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
                                            className="input-bordered input input-sm w-full"
                                            value={d.descripcion}
                                            onChange={(e) =>
                                                updateDetalle(
                                                    i,
                                                    'descripcion',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </td>
                                    <td>
                                        <input
                                            type="text"
                                            className="input-bordered input input-sm w-full"
                                            value={d.unidad}
                                            onChange={(e) =>
                                                updateDetalle(
                                                    i,
                                                    'unidad',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </td>
                                    <td>
                                        <input
                                            type="number"
                                            step="0.01"
                                            className="input-bordered input input-sm w-full text-right"
                                            value={d.cantidad}
                                            onChange={(e) =>
                                                updateDetalle(
                                                    i,
                                                    'cantidad',
                                                    Number(e.target.value),
                                                )
                                            }
                                        />
                                    </td>
                                    <td>
                                        <RubroSelector
                                            value={d.obra_rubro_id}
                                            options={rubrosDisponibles}
                                            rubroOnly={!multiobra}
                                            disabled={
                                                !multiobra && !data.obra_id
                                            }
                                            onChange={(value) =>
                                                updateDetalle(
                                                    i,
                                                    'obra_rubro_id',
                                                    value,
                                                )
                                            }
                                        />
                                        {errors[
                                            `detalles.${i}.obra_rubro_id` as keyof typeof errors
                                        ] && (
                                            <p className="mt-1 text-xs text-error">
                                                {
                                                    errors[
                                                        `detalles.${i}.obra_rubro_id` as keyof typeof errors
                                                    ]
                                                }
                                            </p>
                                        )}
                                    </td>
                                    <td>
                                        <select
                                            className="select-bordered select w-full select-sm"
                                            value={d.uso_cfdi_id}
                                            onChange={(e) =>
                                                updateDetalle(
                                                    i,
                                                    'uso_cfdi_id',
                                                    e.target.value
                                                        ? Number(e.target.value)
                                                        : '',
                                                )
                                            }
                                        >
                                            <option value="">
                                                Selecciona...
                                            </option>
                                            {usosCfdi.map((u) => (
                                                <option key={u.id} value={u.id}>
                                                    {u.clave} - {u.descripcion}
                                                </option>
                                            ))}
                                        </select>
                                        {errors[
                                            `detalles.${i}.uso_cfdi_id` as keyof typeof errors
                                        ] && (
                                            <p className="mt-1 text-xs text-error">
                                                {
                                                    errors[
                                                        `detalles.${i}.uso_cfdi_id` as keyof typeof errors
                                                    ]
                                                }
                                            </p>
                                        )}
                                    </td>
                                    <td>
                                        <input
                                            type="text"
                                            className="input-bordered input input-sm w-full"
                                            value={d.notas}
                                            onChange={(e) =>
                                                updateDetalle(
                                                    i,
                                                    'notas',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </td>
                                    <td>
                                        {data.detalles.length > 1 && (
                                            <button
                                                type="button"
                                                onClick={() => removeDetalle(i)}
                                                className="btn text-error btn-ghost btn-sm"
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

                {errors.detalles && (
                    <p className="mt-2 text-sm text-error">{errors.detalles}</p>
                )}

                <div className="mt-6 flex justify-end gap-2">
                    <Button type="button" variant="outline" asChild>
                        <a
                            href={`/admin/costos/requisiciones/${requisicion.id}`}
                        >
                            Cancelar
                        </a>
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Guardando...' : 'Guardar cambios'}
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
