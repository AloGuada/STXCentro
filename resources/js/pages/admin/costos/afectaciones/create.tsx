import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertTriangleIcon,
    FileTextIcon,
    Loader2Icon,
    PlusIcon,
    Trash2Icon,
    UploadIcon,
} from 'lucide-react';
import { type FormEvent, useRef, useState } from 'react';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosObraRubro, Obra } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/afectaciones' },
    { title: 'Afectaciones', href: '/admin/costos/afectaciones' },
    { title: 'Nueva', href: '/admin/costos/afectaciones/create' },
];

type DetalleForm = {
    obra_id: string;
    obra_rubro_id: string;
    monto: string;
};

type Props = {
    obras: Obra[];
    obraRubros: CostosObraRubro[];
};

const fmtMoney = (n: number) =>
    n.toLocaleString('es-MX', { minimumFractionDigits: 2 });

export default function AfectacionesCreate({ obras, obraRubros }: Props) {
    const { data, setData, post, processing, errors } = useForm<{
        fecha: string;
        tipo_origen: string;
        descripcion: string;
        detalles: DetalleForm[];
        documentos: File[];
    }>({
        fecha: new Date().toISOString().slice(0, 10),
        tipo_origen: '',
        descripcion: '',
        detalles: [],
        documentos: [],
    });

    const fileInputRef = useRef<HTMLInputElement | null>(null);

    // Modal de confirmación antes de crear la afectación.
    const [showConfirmModal, setShowConfirmModal] = useState(false);

    const rubroLabel = (obraRubroId: string) => {
        const or = obraRubros.find((r) => r.id === Number(obraRubroId));
        return or
            ? `${or.rubro?.codigo ?? ''} - ${or.rubro?.descripcion ?? ''}`
            : 'Sin centro de costos';
    };

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
        // Cambiar la obra invalida el centro de costos elegido.
        if (field === 'obra_id') {
            updated[index].obra_rubro_id = '';
        }
        setData('detalles', updated);
    };

    const rubrosDeObra = (obraId: string) =>
        obraId
            ? obraRubros.filter((or) => or.obra_id === Number(obraId))
            : [];

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

    const addDocumentos = (files: FileList | null) => {
        if (!files || files.length === 0) {
            return;
        }
        setData('documentos', [...data.documentos, ...Array.from(files)]);
    };

    const removeDocumento = (index: number) =>
        setData(
            'documentos',
            data.documentos.filter((_, i) => i !== index),
        );

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        setShowConfirmModal(true);
    };

    const guardarAfectacion = () => {
        setShowConfirmModal(false);
        post('/admin/costos/afectaciones', { forceFormData: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva Afectación Presupuestal" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">
                        Nueva Afectación Presupuestal
                    </h1>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Información general */}
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
                                    placeholder="Motivo de la afectación"
                                />
                            </FormField>
                        </div>

                        {/* Documentos de sustento */}
                        <div className="space-y-3">
                            <h2 className="border-b border-base-300 pb-2 text-lg font-medium">
                                Documento(s) de sustento
                            </h2>

                            {data.documentos.length > 0 && (
                                <div className="space-y-2">
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
                                                onClick={() =>
                                                    removeDocumento(i)
                                                }
                                            >
                                                <Trash2Icon className="size-3.5" />
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            )}

                            <input
                                ref={fileInputRef}
                                type="file"
                                className="hidden"
                                accept="application/pdf,image/*"
                                multiple
                                onChange={(e) => {
                                    addDocumentos(e.target.files);
                                    e.target.value = '';
                                }}
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => fileInputRef.current?.click()}
                            >
                                <UploadIcon className="size-4" />
                                Adjuntar documento (PDF o imagen)
                            </Button>
                            {errors.documentos && (
                                <p className="text-sm text-error">
                                    {errors.documentos}
                                </p>
                            )}
                        </div>

                        {/* Centros de costos a afectar */}
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

                            {data.detalles.length === 0 ? (
                                <p className="text-sm text-base-content/60">
                                    No hay centros de costos agregados.
                                </p>
                            ) : (
                                data.detalles.map((det, index) => {
                                    const monto = parseFloat(det.monto) || 0;
                                    const disponible = getDisponible(
                                        det.obra_rubro_id,
                                    );
                                    const excede =
                                        disponible !== null &&
                                        monto > disponible;

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
                                                        value={
                                                            det.obra_rubro_id
                                                        }
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
                                                                {
                                                                    or.rubro
                                                                        ?.codigo
                                                                }{' '}
                                                                -{' '}
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
                                                                Excede
                                                                presupuesto
                                                            </span>
                                                        )}
                                                    </p>
                                                )}
                                            </FormField>
                                        </div>
                                    );
                                })
                            )}

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
                            <Button type="submit" disabled={processing}>
                                {processing && (
                                    <Loader2Icon className="size-4 animate-spin" />
                                )}
                                Guardar
                            </Button>
                        </div>
                    </form>
                </div>
            </div>

            {showConfirmModal && (
                <dialog className="modal modal-open">
                    <div className="modal-box">
                        <h3 className="mb-3 text-lg font-bold">
                            Confirmar afectación
                        </h3>
                        <p className="mb-3 text-sm text-base-content/70">
                            Vas a registrar una afectación sobre{' '}
                            {data.detalles.length}{' '}
                            {data.detalles.length === 1
                                ? 'centro de costos'
                                : 'centros de costos'}
                            . Revisa que los montos sean correctos:
                        </p>
                        <div className="max-h-52 overflow-y-auto rounded-lg border border-base-300">
                            <table className="table table-sm">
                                <tbody>
                                    {data.detalles.map((d, i) => (
                                        <tr key={i}>
                                            <td className="text-sm">
                                                {rubroLabel(d.obra_rubro_id)}
                                            </td>
                                            <td className="text-right font-medium whitespace-nowrap">
                                                $
                                                {fmtMoney(
                                                    parseFloat(d.monto) || 0,
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td className="text-right font-bold">
                                            Total
                                        </td>
                                        <td className="text-right font-bold whitespace-nowrap">
                                            ${fmtMoney(total)}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div className="modal-action">
                            <Button
                                variant="outline"
                                onClick={() => setShowConfirmModal(false)}
                                disabled={processing}
                            >
                                Volver
                            </Button>
                            <Button
                                onClick={guardarAfectacion}
                                disabled={processing}
                            >
                                {processing && (
                                    <Loader2Icon className="size-4 animate-spin" />
                                )}
                                Aceptar
                            </Button>
                        </div>
                    </div>
                    <div
                        className="modal-backdrop"
                        onClick={
                            processing
                                ? undefined
                                : () => setShowConfirmModal(false)
                        }
                    ></div>
                </dialog>
            )}
        </AppLayout>
    );
}
