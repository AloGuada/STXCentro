import { Head, useForm } from '@inertiajs/react';
import { PlusIcon, Trash2Icon } from 'lucide-react';
import { useState } from 'react';
import { RubroSelector } from '@/components/costos/rubro-selector';
import { Button } from '@/components/ui/button';
import { SearchSelect } from '@/components/ui/search-select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type {
    CostosRequisicion,
    CostosUsoCfdi,
    Departamento,
    ObraRubroOption,
    PresupuestoOption,
} from '@/types/models';

type Detalle = {
    id?: number;
    descripcion: string;
    unidad: string;
    cantidad: number;
    presupuesto_id: number | '';
    obra_rubro_id: number | '';
    uso_cfdi_id: number | '';
    notas: string;
};

type FormData = {
    departamento_id: number;
    presupuesto_id: number | '';
    justificacion: string;
    detalles: Detalle[];
    _version: string;
};

type Props = {
    requisicion: CostosRequisicion;
    departamentos: Pick<Departamento, 'id' | 'descripcion'>[];
    presupuestos: PresupuestoOption[];
    obraRubros: ObraRubroOption[];
    usosCfdi: Pick<CostosUsoCfdi, 'id' | 'clave' | 'descripcion'>[];
};

export default function RequisicionesEdit({
    requisicion,
    departamentos,
    presupuestos,
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
        presupuesto_id: requisicion.presupuesto_id ?? '',
        justificacion: requisicion.justificacion ?? '',
        detalles: (requisicion.detalles ?? []).map((d) => ({
            id: d.id,
            descripcion: d.descripcion,
            unidad: d.unidad,
            cantidad: Number(d.cantidad),
            presupuesto_id:
                obraRubros.find((r) => r.id === d.obra_rubro_id)
                    ?.presupuesto_id ?? '',
            obra_rubro_id: d.obra_rubro_id ?? '',
            uso_cfdi_id: d.uso_cfdi_id ?? '',
            notas: d.notas ?? '',
        })),
        _version: requisicion.updated_at,
    });

    const [incluirCerradas, setIncluirCerradas] = useState(false);
    // Multipresupuesto: se infiere de la requisición cargada (sin presupuesto = multi).
    const [multipresupuesto, setMultipresupuesto] = useState(
        !requisicion.presupuesto_id,
    );
    const presupuestosVisibles = presupuestos.filter(
        (p) => incluirCerradas || !p.cerrado,
    );
    const rubrosDe = (presupuestoId: number | '') =>
        presupuestoId
            ? obraRubros.filter(
                  (r) =>
                      r.presupuesto_id === presupuestoId &&
                      (incluirCerradas || !r.cerrado),
              )
            : [];

    const setPresupuesto = (value: number | '') => {
        setData((prev) => ({
            ...prev,
            presupuesto_id: value,
            detalles: prev.detalles.map((d) => ({ ...d, obra_rubro_id: '' })),
        }));
    };

    const toggleMultipresupuesto = (on: boolean) => {
        setMultipresupuesto(on);
        setData((prev) => ({
            ...prev,
            presupuesto_id: on ? '' : prev.presupuesto_id,
            detalles: prev.detalles.map((d) => ({
                ...d,
                presupuesto_id: '',
                obra_rubro_id: '',
            })),
        }));
    };

    const addDetalle = () => {
        setData('detalles', [
            ...data.detalles,
            {
                descripcion: '',
                unidad: 'pza',
                cantidad: 1,
                presupuesto_id: '',
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
                i === idx
                    ? {
                          ...d,
                          [field]: value,
                          ...(field === 'presupuesto_id'
                              ? { obra_rubro_id: '' as const }
                              : {}),
                      }
                    : d,
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
                                Presupuesto {!multipresupuesto && '*'}
                            </label>
                            <div className="flex items-center gap-3">
                                <label className="label cursor-pointer gap-2 py-0">
                                    <input
                                        type="checkbox"
                                        className="checkbox checkbox-xs"
                                        checked={multipresupuesto}
                                        onChange={(e) =>
                                            toggleMultipresupuesto(
                                                e.target.checked,
                                            )
                                        }
                                    />
                                    <span className="label-text text-xs">
                                        Multipresupuesto
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
                                        Incluir cerrados
                                    </span>
                                </label>
                            </div>
                        </div>
                        {multipresupuesto ? (
                            <p className="text-sm text-base-content/60">
                                Requisición multipresupuesto: cada partida elige
                                su centro de costos (con su presupuesto).
                            </p>
                        ) : (
                            <>
                                <select
                                    className="select-bordered select w-full"
                                    value={data.presupuesto_id}
                                    onChange={(e) =>
                                        setPresupuesto(
                                            e.target.value
                                                ? Number(e.target.value)
                                                : '',
                                        )
                                    }
                                >
                                    <option value="">
                                        Selecciona un presupuesto
                                    </option>
                                    {presupuestosVisibles.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.label}
                                            {p.cerrado ? ' (Cerrado)' : ''}
                                        </option>
                                    ))}
                                </select>
                                {errors.presupuesto_id && (
                                    <p className="mt-1 text-sm text-error">
                                        {errors.presupuesto_id}
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

                {!multipresupuesto && !data.presupuesto_id && (
                    <div className="mb-3 alert alert-info">
                        <span>
                            Selecciona primero el presupuesto para asignar el
                            centro de costos de cada partida.
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
                                {multipresupuesto && (
                                    <th className="min-w-[180px]">
                                        Presupuesto *
                                    </th>
                                )}
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
                                    {multipresupuesto && (
                                        <td>
                                            <SearchSelect
                                                value={
                                                    d.presupuesto_id === ''
                                                        ? ''
                                                        : String(
                                                              d.presupuesto_id,
                                                          )
                                                }
                                                onValueChange={(v) =>
                                                    updateDetalle(
                                                        i,
                                                        'presupuesto_id',
                                                        v ? Number(v) : '',
                                                    )
                                                }
                                                placeholder="Buscar presupuesto..."
                                                options={presupuestosVisibles.map(
                                                    (p) => ({
                                                        value: String(p.id),
                                                        label: `${p.label}${p.cerrado ? ' (Cerrado)' : ''}`,
                                                    }),
                                                )}
                                            />
                                        </td>
                                    )}
                                    <td>
                                        <RubroSelector
                                            value={d.obra_rubro_id}
                                            options={rubrosDe(
                                                multipresupuesto
                                                    ? d.presupuesto_id
                                                    : data.presupuesto_id,
                                            )}
                                            rubroOnly
                                            disabled={
                                                !(multipresupuesto
                                                    ? d.presupuesto_id
                                                    : data.presupuesto_id)
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
