import { Head, useForm } from '@inertiajs/react';
import {
    AlertCircleIcon,
    FileTextIcon,
    PlusIcon,
    Trash2Icon,
} from 'lucide-react';
import { useState } from 'react';
import { RubroSelector } from '@/components/costos/rubro-selector';
import { Button } from '@/components/ui/button';
import { CreatableCombobox } from '@/components/ui/creatable-combobox';
import { SearchSelect } from '@/components/ui/search-select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type {
    CostosProducto,
    CostosUsoCfdi,
    Departamento,
    Obra,
    ObraRubroOption,
} from '@/types/models';

type Detalle = {
    producto_id: number | null;
    descripcion: string;
    unidad: string;
    cantidad: number;
    obra_id: number | '';
    obra_rubro_id: number | '';
    uso_cfdi_id: number | '';
    notas: string;
};

type FormData = {
    departamento_id: number | '';
    obra_id: number | '';
    justificacion: string;
    detalles: Detalle[];
    documentos: File[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/requisiciones' },
    { title: 'Requisiciones', href: '/admin/costos/requisiciones' },
    { title: 'Nueva', href: '/admin/costos/requisiciones/create' },
];

type Props = {
    departamentos: Pick<Departamento, 'id' | 'descripcion'>[];
    obras: Pick<Obra, 'id' | 'no' | 'descripcion' | 'estatus'>[];
    obraRubros: ObraRubroOption[];
    usosCfdi: Pick<CostosUsoCfdi, 'id' | 'clave' | 'descripcion'>[];
    productos: Pick<
        CostosProducto,
        'id' | 'codigo' | 'descripcion' | 'unidad'
    >[];
};

export default function RequisicionesCreate({
    departamentos,
    obras,
    obraRubros,
    usosCfdi,
    productos,
}: Props) {
    const defaultUsoId = usosCfdi.find((u) => u.clave === 'G01')?.id ?? '';

    const productoOptions = productos.map((p) => ({
        value: String(p.id),
        label: p.codigo ? `${p.codigo} · ${p.descripcion}` : p.descripcion,
    }));

    const blankDetalle = (): Detalle => ({
        producto_id: null,
        descripcion: '',
        unidad: 'pza',
        cantidad: 1,
        obra_id: '',
        obra_rubro_id: '',
        uso_cfdi_id: defaultUsoId,
        notas: '',
    });

    const { data, setData, post, processing, errors, setError, clearErrors } =
        useForm<FormData>({
            departamento_id: '',
            obra_id: '',
            justificacion: '',
            detalles: [blankDetalle()],
            documentos: [],
        });

    const addDocumentos = (files: FileList | null) => {
        if (!files || files.length === 0) {
            return;
        }
        const soloPdf = Array.from(files).filter(
            (f) =>
                f.type === 'application/pdf' ||
                f.name.toLowerCase().endsWith('.pdf'),
        );
        if (soloPdf.length === 0) {
            setError('documentos', 'Solo se permiten archivos PDF.');
            return;
        }
        clearErrors('documentos');
        setData('documentos', [...data.documentos, ...soloPdf]);
    };
    const removeDocumento = (idx: number) =>
        setData(
            'documentos',
            data.documentos.filter((_, i) => i !== idx),
        );

    // Por defecto se ocultan obras/adicionales cerrados; el checkbox los incluye
    // para casos excepcionales (el cargo sigue el flujo normal de aprobación).
    const [incluirCerradas, setIncluirCerradas] = useState(false);

    const obrasVisibles = obras.filter(
        (o) => incluirCerradas || o.estatus !== 'cerrada',
    );

    // Multiobra: por defecto la requisición es de una sola obra (candado de
    // pertenencia). Al activarlo, cada partida elige su centro de costos de
    // cualquier obra (el obra_rubro ya encierra la obra).
    const [multiobra, setMultiobra] = useState(false);

    // Centros de costo de una obra dada (respetando el filtro de cerradas).
    const rubrosDe = (obraId: number | '') =>
        obraId
            ? obraRubros.filter(
                  (r) =>
                      r.obra_id === obraId && (incluirCerradas || !r.cerrado),
              )
            : [];

    const setObra = (value: number | '') => {
        // Cambiar la obra invalida los rubros elegidos (pertenecen a otra obra).
        setData((prev) => ({
            ...prev,
            obra_id: value,
            detalles: prev.detalles.map((d) => ({ ...d, obra_rubro_id: '' })),
        }));
    };

    const toggleMultiobra = (on: boolean) => {
        setMultiobra(on);
        // Al cambiar de modo se invalidan obra/rubro por renglón.
        setData((prev) => ({
            ...prev,
            obra_id: on ? '' : prev.obra_id,
            detalles: prev.detalles.map((d) => ({
                ...d,
                obra_id: '',
                obra_rubro_id: '',
            })),
        }));
    };

    const addDetalle = () =>
        setData('detalles', [...data.detalles, blankDetalle()]);
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
                          // Cambiar la obra del renglón invalida su centro de costos.
                          ...(field === 'obra_id'
                              ? { obra_rubro_id: '' as const }
                              : {}),
                      }
                    : d,
            ),
        );
    };
    const setDetalleFields = (idx: number, partial: Partial<Detalle>) => {
        setData(
            'detalles',
            data.detalles.map((d, i) => (i === idx ? { ...d, ...partial } : d)),
        );
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/costos/requisiciones', { forceFormData: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva requisición" />

            <form onSubmit={handleSubmit} className="p-6">
                <h1 className="mb-4 text-2xl font-semibold">
                    Nueva requisición
                </h1>

                {Object.keys(errors).length > 0 && (
                    <div className="mb-4 alert items-start alert-error">
                        <AlertCircleIcon className="size-5 shrink-0" />
                        <div>
                            <p className="font-medium">
                                No se pudo guardar la requisición
                            </p>
                            <p className="text-sm">
                                Revisa y corrige los campos resaltados en rojo
                                antes de continuar.
                            </p>
                        </div>
                    </div>
                )}

                <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label className="label-text label">
                            Departamento *
                        </label>
                        <select
                            className={`select-bordered select w-full ${errors.departamento_id ? 'select-error' : ''}`}
                            value={data.departamento_id}
                            onChange={(e) =>
                                setData(
                                    'departamento_id',
                                    e.target.value
                                        ? Number(e.target.value)
                                        : '',
                                )
                            }
                        >
                            <option value="">Selecciona un departamento</option>
                            {departamentos.map((d) => (
                                <option key={d.id} value={d.id}>
                                    {d.descripcion}
                                </option>
                            ))}
                        </select>
                        {errors.departamento_id && (
                            <p className="mt-1 text-sm text-error">
                                {errors.departamento_id}
                            </p>
                        )}
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
                                centro de costos (con su obra) en la tabla.
                            </p>
                        ) : (
                            <>
                                <SearchSelect
                                    value={
                                        data.obra_id === ''
                                            ? ''
                                            : String(data.obra_id)
                                    }
                                    onValueChange={(v) =>
                                        setObra(v ? Number(v) : '')
                                    }
                                    placeholder="Selecciona una obra"
                                    options={obrasVisibles.map((o) => ({
                                        value: String(o.id),
                                        label: `${o.no ? `OP-${o.no} · ` : ''}${o.descripcion}${o.estatus === 'cerrada' ? ' (Cerrada)' : ''}`,
                                    }))}
                                />
                                {errors.obra_id && (
                                    <p className="mt-1 text-sm text-error">
                                        {errors.obra_id}
                                    </p>
                                )}
                                <p className="mt-1 text-xs text-base-content/60">
                                    Las partidas eligen su centro de costos
                                    dentro de esta obra.
                                </p>
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
                            placeholder="Por qué se necesita y cuál es el impacto esperado"
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
                            Selecciona primero la obra para poder asignar el
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
                                {multiobra && (
                                    <th className="min-w-[180px]">Obra *</th>
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
                                <tr key={i}>
                                    <td>
                                        {d.descripcion ? (
                                            <div className="flex items-start justify-between gap-1">
                                                <span className="text-sm">
                                                    {d.descripcion}
                                                    {!d.producto_id && (
                                                        <span className="ml-1 text-[10px] text-primary">
                                                            (nuevo)
                                                        </span>
                                                    )}
                                                </span>
                                                <button
                                                    type="button"
                                                    className="btn px-1 btn-ghost btn-xs"
                                                    title="Cambiar producto"
                                                    onClick={() =>
                                                        setDetalleFields(i, {
                                                            producto_id: null,
                                                            descripcion: '',
                                                        })
                                                    }
                                                >
                                                    <Trash2Icon className="size-3" />
                                                </button>
                                            </div>
                                        ) : (
                                            <CreatableCombobox
                                                options={productoOptions}
                                                placeholder="Buscar o crear producto..."
                                                creatableLabel="Crear producto"
                                                className="[&_input]:input-sm"
                                                onSelect={(opt) => {
                                                    const p = productos.find(
                                                        (x) =>
                                                            String(x.id) ===
                                                            opt.value,
                                                    );
                                                    if (p) {
                                                        setDetalleFields(i, {
                                                            producto_id: p.id,
                                                            descripcion:
                                                                p.descripcion,
                                                            unidad: p.unidad,
                                                        });
                                                    }
                                                }}
                                                onCreate={(text) =>
                                                    setDetalleFields(i, {
                                                        producto_id: null,
                                                        descripcion: text,
                                                    })
                                                }
                                            />
                                        )}
                                        {errors[
                                            `detalles.${i}.descripcion` as keyof typeof errors
                                        ] && (
                                            <p className="mt-1 text-xs text-error">
                                                {
                                                    errors[
                                                        `detalles.${i}.descripcion` as keyof typeof errors
                                                    ]
                                                }
                                            </p>
                                        )}
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
                                            className={`input-bordered input input-sm w-full text-right ${errors[`detalles.${i}.cantidad` as keyof typeof errors] ? 'input-error' : ''}`}
                                            value={d.cantidad}
                                            onChange={(e) =>
                                                updateDetalle(
                                                    i,
                                                    'cantidad',
                                                    Number(e.target.value),
                                                )
                                            }
                                        />
                                        {errors[
                                            `detalles.${i}.cantidad` as keyof typeof errors
                                        ] && (
                                            <p className="mt-1 text-xs text-error">
                                                {
                                                    errors[
                                                        `detalles.${i}.cantidad` as keyof typeof errors
                                                    ]
                                                }
                                            </p>
                                        )}
                                    </td>
                                    {multiobra && (
                                        <td>
                                            <SearchSelect
                                                value={
                                                    d.obra_id === ''
                                                        ? ''
                                                        : String(d.obra_id)
                                                }
                                                onValueChange={(v) =>
                                                    updateDetalle(
                                                        i,
                                                        'obra_id',
                                                        v ? Number(v) : '',
                                                    )
                                                }
                                                placeholder="Buscar obra..."
                                                options={obrasVisibles.map(
                                                    (o) => ({
                                                        value: String(o.id),
                                                        label: `${o.no ? `OP-${o.no} · ` : ''}${o.descripcion}${o.estatus === 'cerrada' ? ' (Cerrada)' : ''}`,
                                                    }),
                                                )}
                                            />
                                        </td>
                                    )}
                                    <td>
                                        <RubroSelector
                                            value={d.obra_rubro_id}
                                            options={rubrosDe(
                                                multiobra
                                                    ? d.obra_id
                                                    : data.obra_id,
                                            )}
                                            rubroOnly
                                            disabled={
                                                !(multiobra
                                                    ? d.obra_id
                                                    : data.obra_id)
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
                                            className={`select-bordered select w-full select-sm ${errors[`detalles.${i}.uso_cfdi_id` as keyof typeof errors] ? 'select-error' : ''}`}
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

                {/* Documentos adicionales (solo PDF), opcionales */}
                <div className="mt-6 rounded-lg border border-base-300 p-3">
                    <h3 className="mb-3 text-xs tracking-wider text-base-content/60 uppercase">
                        Documentos adicionales (solo PDF) · opcional
                    </h3>

                    {data.documentos.length > 0 && (
                        <div className="mb-3 space-y-2">
                            {data.documentos.map((file, i) => (
                                <div
                                    key={`${file.name}-${i}`}
                                    className="flex items-center gap-2 rounded border border-base-300 bg-base-200 p-2 text-sm"
                                >
                                    <FileTextIcon className="size-4 text-base-content/60" />
                                    <span className="font-medium">
                                        {file.name}
                                    </span>
                                    <button
                                        type="button"
                                        className="btn ml-auto text-error btn-ghost btn-xs"
                                        title="Quitar"
                                        onClick={() => removeDocumento(i)}
                                    >
                                        <Trash2Icon className="size-3.5" />
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}

                    <input
                        type="file"
                        accept="application/pdf"
                        multiple
                        className="file-input-bordered file-input w-72 file-input-sm"
                        onChange={(e) => {
                            addDocumentos(e.target.files);
                            e.target.value = '';
                        }}
                    />
                    {errors.documentos && (
                        <p className="mt-2 text-sm text-error">
                            {errors.documentos}
                        </p>
                    )}
                </div>

                <div className="mt-6 flex justify-end gap-2">
                    <Button type="button" variant="outline" asChild>
                        <a href="/admin/costos/requisiciones">Cancelar</a>
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Guardando...' : 'Guardar requisición'}
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
