import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertTriangleIcon,
    Loader2Icon,
    PlusIcon,
    Trash2Icon,
} from 'lucide-react';
import { type FormEvent } from 'react';
import { EditLockBanner } from '@/components/costos/edit-lock-banner';
import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useEditLock } from '@/hooks/use-edit-lock';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type {
    CostosAfectacionPresupuestal,
    CostosObraRubro,
    Obra,
} from '@/types/models';

type DetalleForm = {
    id?: number;
    obra_id: string;
    obra_rubro_id: string;
    monto: string;
};

type Props = {
    afectacion: CostosAfectacionPresupuestal;
    obras: Obra[];
    obraRubros: CostosObraRubro[];
};

const fmtMoney = (n: number) =>
    n.toLocaleString('es-MX', { minimumFractionDigits: 2 });

export default function AfectacionesEdit({
    afectacion,
    obras,
    obraRubros,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/afectaciones' },
        { title: 'Afectaciones', href: '/admin/costos/afectaciones' },
        {
            title: afectacion.folio,
            href: `/admin/costos/afectaciones/${afectacion.id}/edit`,
        },
    ];

    const lockState = useEditLock('afectacion', afectacion.id);
    const readonly = lockState.status !== 'owned';

    const { data, setData, put, processing, errors } = useForm<{
        fecha: string;
        tipo_origen: string;
        descripcion: string;
        detalles: DetalleForm[];
        _version: string;
    }>({
        fecha: afectacion.fecha ? afectacion.fecha.slice(0, 10) : '',
        tipo_origen: afectacion.tipo_origen,
        descripcion: afectacion.descripcion,
        _version: afectacion.updated_at,
        detalles: (afectacion.detalles ?? []).map((d) => {
            const or = obraRubros.find((r) => r.id === d.obra_rubro_id);
            return {
                id: d.id,
                obra_id: or?.obra_id ? String(or.obra_id) : '',
                obra_rubro_id: String(d.obra_rubro_id),
                monto: String(d.monto),
            };
        }),
    });

    const addDetalle = () => {
        setData('detalles', [
            ...data.detalles,
            { obra_id: '', obra_rubro_id: '', monto: '' },
        ]);
    };

    const removeDetalle = (index: number) => {
        setData(
            'detalles',
            data.detalles.filter((_, i) => i !== index),
        );
    };

    const updateDetalle = (
        index: number,
        field: keyof DetalleForm,
        value: string,
    ) => {
        const updated = [...data.detalles];
        updated[index] = { ...updated[index], [field]: value };
        if (field === 'obra_id') {
            updated[index].obra_rubro_id = '';
        }
        setData('detalles', updated);
    };

    const rubrosDeObra = (obraId: string) =>
        obraId ? obraRubros.filter((or) => or.obra_id === Number(obraId)) : [];

    const getDisponible = (obraRubroId: string) => {
        const or = obraRubros.find((r) => r.id === Number(obraRubroId));
        if (!or) {
            return null;
        }
        return Number(or.presupuestado) - Number(or.acumulado);
    };

    const total = data.detalles.reduce(
        (sum, d) => sum + (parseFloat(d.monto) || 0),
        0,
    );

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
                        <h1 className="text-2xl font-semibold">
                            Editar Afectación {afectacion.folio}
                        </h1>
                        <DeleteDialog
                            title="Eliminar afectación"
                            description={`¿Estás seguro de eliminar "${afectacion.folio}"? Esta acción no se puede deshacer.`}
                            deleteUrl={`/admin/costos/afectaciones/${afectacion.id}`}
                        />
                    </div>

                    <EditLockBanner state={lockState} />

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="space-y-4">
                            <h2 className="border-b border-base-300 pb-2 text-lg font-medium">
                                Información General
                            </h2>

                            <div className="grid grid-cols-2 gap-4">
                                <FormField
                                    label="Fecha"
                                    htmlFor="fecha"
                                    error={errors.fecha}
                                    required
                                >
                                    <Input
                                        id="fecha"
                                        type="date"
                                        value={data.fecha}
                                        onChange={(e) =>
                                            setData('fecha', e.target.value)
                                        }
                                    />
                                </FormField>

                                <FormField
                                    label="Tipo de Origen"
                                    htmlFor="tipo_origen"
                                    error={errors.tipo_origen}
                                    required
                                >
                                    <select
                                        id="tipo_origen"
                                        className="select-bordered select w-full"
                                        value={data.tipo_origen}
                                        onChange={(e) =>
                                            setData(
                                                'tipo_origen',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="">Seleccionar</option>
                                        <option value="nomina">Nómina</option>
                                        <option value="gasto_directo">
                                            Gasto Directo
                                        </option>
                                        <option value="reembolso">
                                            Reembolso
                                        </option>
                                        <option value="ajuste_presupuestal">
                                            Ajuste Presupuestal
                                        </option>
                                        <option value="otro">Otro</option>
                                    </select>
                                </FormField>
                            </div>

                            <FormField
                                label="Razón / Descripción"
                                htmlFor="descripcion"
                                error={errors.descripcion}
                                required
                            >
                                <textarea
                                    id="descripcion"
                                    className="textarea-bordered textarea w-full"
                                    value={data.descripcion}
                                    onChange={(e) =>
                                        setData('descripcion', e.target.value)
                                    }
                                    rows={3}
                                />
                            </FormField>
                        </div>

                        <div className="space-y-4">
                            <div className="flex items-center justify-between border-b border-base-300 pb-2">
                                <h2 className="text-lg font-medium">
                                    Centros de costos a afectar
                                </h2>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={addDetalle}
                                >
                                    <PlusIcon className="size-4" />
                                    Agregar
                                </Button>
                            </div>

                            {errors.detalles && (
                                <p className="text-sm text-error">
                                    {errors.detalles}
                                </p>
                            )}

                            {data.detalles.map((det, index) => {
                                const monto = parseFloat(det.monto) || 0;
                                const disponible = getDisponible(
                                    det.obra_rubro_id,
                                );
                                const excede =
                                    disponible !== null && monto > disponible;

                                return (
                                    <div
                                        key={index}
                                        className="space-y-3 rounded-lg border border-base-300 p-4"
                                    >
                                        <div className="flex items-start justify-between">
                                            <h3 className="font-medium">
                                                Afectación {index + 1}
                                            </h3>
                                            <button
                                                type="button"
                                                className="btn text-error btn-ghost btn-sm"
                                                onClick={() =>
                                                    removeDetalle(index)
                                                }
                                            >
                                                <Trash2Icon className="size-4" />
                                            </button>
                                        </div>

                                        <div className="grid grid-cols-2 gap-4">
                                            <FormField
                                                label="Obra"
                                                htmlFor={`det_obra_${index}`}
                                            >
                                                <select
                                                    id={`det_obra_${index}`}
                                                    className="select-bordered select w-full"
                                                    value={det.obra_id}
                                                    onChange={(e) =>
                                                        updateDetalle(
                                                            index,
                                                            'obra_id',
                                                            e.target.value,
                                                        )
                                                    }
                                                >
                                                    <option value="">
                                                        Seleccionar obra
                                                    </option>
                                                    {obras.map((o) => (
                                                        <option
                                                            key={o.id}
                                                            value={o.id}
                                                        >
                                                            {o.no} -{' '}
                                                            {o.descripcion}
                                                        </option>
                                                    ))}
                                                </select>
                                            </FormField>

                                            <FormField
                                                label="Centro de Costos"
                                                htmlFor={`det_rubro_${index}`}
                                                error={
                                                    errors[
                                                        `detalles.${index}.obra_rubro_id` as keyof typeof errors
                                                    ]
                                                }
                                                required
                                            >
                                                <select
                                                    id={`det_rubro_${index}`}
                                                    className="select-bordered select w-full"
                                                    value={det.obra_rubro_id}
                                                    disabled={!det.obra_id}
                                                    onChange={(e) =>
                                                        updateDetalle(
                                                            index,
                                                            'obra_rubro_id',
                                                            e.target.value,
                                                        )
                                                    }
                                                >
                                                    <option value="">
                                                        {det.obra_id
                                                            ? 'Seleccionar centro de costos'
                                                            : 'Selecciona la obra primero'}
                                                    </option>
                                                    {rubrosDeObra(
                                                        det.obra_id,
                                                    ).map((or) => (
                                                        <option
                                                            key={or.id}
                                                            value={or.id}
                                                        >
                                                            {or.rubro?.codigo} -{' '}
                                                            {
                                                                or.rubro
                                                                    ?.descripcion
                                                            }
                                                        </option>
                                                    ))}
                                                </select>
                                            </FormField>
                                        </div>

                                        <FormField
                                            label="Monto a afectar"
                                            htmlFor={`det_monto_${index}`}
                                            error={
                                                errors[
                                                    `detalles.${index}.monto` as keyof typeof errors
                                                ]
                                            }
                                            required
                                        >
                                            <Input
                                                id={`det_monto_${index}`}
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                className="w-48"
                                                value={det.monto}
                                                onChange={(e) =>
                                                    updateDetalle(
                                                        index,
                                                        'monto',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            {disponible !== null && (
                                                <p
                                                    className={`mt-1 text-xs ${excede ? 'text-error' : 'text-base-content/60'}`}
                                                >
                                                    Disponible: $
                                                    {fmtMoney(disponible)}
                                                    {excede && (
                                                        <span className="ml-2 inline-flex items-center gap-1">
                                                            <AlertTriangleIcon className="size-3" />{' '}
                                                            Excede presupuesto
                                                        </span>
                                                    )}
                                                </p>
                                            )}
                                        </FormField>
                                    </div>
                                );
                            })}

                            {data.detalles.length > 0 && (
                                <div className="text-right text-lg font-semibold">
                                    Total: ${fmtMoney(total)}
                                </div>
                            )}
                        </div>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/costos/afectaciones">
                                    Cancelar
                                </Link>
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing || readonly}
                            >
                                {processing && (
                                    <Loader2Icon className="size-4 animate-spin" />
                                )}
                                Guardar
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
