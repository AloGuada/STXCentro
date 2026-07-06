import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertCircleIcon,
    FileTextIcon,
    Loader2Icon,
    PlusIcon,
    Trash2Icon,
    UploadIcon,
} from 'lucide-react';
import {
    Fragment,
    type FormEvent,
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SearchSelect } from '@/components/ui/search-select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type {
    CostosObraRubro,
    CostosTipoSolicitud,
    Departamento,
    Obra,
    Proveedor,
} from '@/types/models';

function getMinViernes(): string {
    const now = new Date();
    const day = now.getDay(); // 0=dom, 1=lun, ..., 5=vie
    const hour = now.getHours();

    // Calcular el viernes de esta semana
    const viernes = new Date(now);
    viernes.setDate(now.getDate() + ((5 - day + 7) % 7));
    viernes.setHours(0, 0, 0, 0);

    // Si ya pasó el miércoles a la 1pm (day>=3 && hour>=13, o day>3 sin ser viernes futuro),
    // el viernes de esta semana queda bloqueado → mínimo es el siguiente viernes
    const pasoCorteMiercoles = day > 3 || (day === 3 && hour >= 13);
    // Si hoy es jueves o viernes o sábado/domingo, el viernes calculado podría ser esta semana o la siguiente
    if (day <= 5 && pasoCorteMiercoles) {
        viernes.setDate(viernes.getDate() + 7);
    }

    return viernes.toISOString().split('T')[0];
}

function esViernes(dateStr: string): boolean {
    const date = new Date(dateStr + 'T00:00:00');
    return date.getDay() === 5;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/solicitudes-pago' },
    { title: 'Solicitudes Pago', href: '/admin/costos/solicitudes-pago' },
    { title: 'Nueva', href: '/admin/costos/solicitudes-pago/create' },
];

type DetalleForm = {
    obra_id: string;
    obra_rubro_id: string;
    concepto: string;
    cantidad: string;
    precio_unitario: string;
};

type Props = {
    departamentos: Departamento[];
    proveedores: Proveedor[];
    tipoSolicitudes: CostosTipoSolicitud[];
    obras: Obra[];
    obraRubros: CostosObraRubro[];
};

export default function SolicitudesPagoCreate({
    departamentos,
    proveedores,
    tipoSolicitudes,
    obras,
    obraRubros,
}: Props) {
    const {
        data,
        setData,
        post,
        transform,
        processing,
        errors,
        setError,
        clearErrors,
    } = useForm<{
        departamento_id: string;
        proveedor_id: string;
        tipo_solicitud_id: string;
        concepto: string;
        tipo_pago: string;
        tipo_moneda: string;
        fecha_pago_solicitada: string;
        detalles: DetalleForm[];
        monto_total: string;
        archivos: Record<string, File[]>;
        archivos_texto: Record<string, string[]>;
    }>({
        departamento_id: '',
        proveedor_id: '',
        tipo_solicitud_id: '',
        concepto: '',
        tipo_pago: 'transferencia',
        tipo_moneda: 'mxn',
        fecha_pago_solicitada: '',
        detalles: [],
        monto_total: '',
        archivos: {},
        archivos_texto: {},
    });

    const fileInputRefs = useRef<Record<string, HTMLInputElement | null>>({});
    const minViernes = useMemo(() => getMinViernes(), []);

    const handleFechaChange = useCallback(
        (value: string) => {
            if (!value || esViernes(value)) {
                setData('fecha_pago_solicitada', value);
                clearErrors('fecha_pago_solicitada');
            } else {
                setError(
                    'fecha_pago_solicitada',
                    'La fecha de pago debe ser un viernes.',
                );
            }
        },
        [setData, setError, clearErrors],
    );

    const selectedTipo = useMemo(
        () =>
            tipoSolicitudes.find(
                (t) => t.id === Number(data.tipo_solicitud_id),
            ),
        [data.tipo_solicitud_id, tipoSolicitudes],
    );

    const addDetalle = () => {
        setData('detalles', [
            ...data.detalles,
            {
                obra_id: '',
                obra_rubro_id: '',
                concepto: '',
                cantidad: '1',
                precio_unitario: '0',
            },
        ]);
        clearErrors('detalles');
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
        if (field === 'obra_rubro_id' && value) {
            clearErrors(
                `detalles.${index}.obra_rubro_id` as Parameters<
                    typeof clearErrors
                >[0],
            );
        }
    };

    const calcSubtotal = (d: DetalleForm) => {
        const cant = parseFloat(d.cantidad) || 0;
        const precio = parseFloat(d.precio_unitario) || 0;
        return cant * precio;
    };

    const total = data.detalles.reduce((sum, d) => sum + calcSubtotal(d), 0);

    // El total del pago es editable. Mientras no se edite a mano, se mantiene
    // sincronizado con la suma de los detalles; al editarlo, se respeta el valor.
    const [montoManual, setMontoManual] = useState(false);

    useEffect(() => {
        if (selectedTipo?.rubros && !montoManual) {
            setData('monto_total', total > 0 ? total.toFixed(2) : '');
        }
    }, [total, selectedTipo?.rubros, montoManual, setData]);

    // Por defecto se ocultan obras/adicionales cerrados; el checkbox los incluye.
    const [incluirCerradas, setIncluirCerradas] = useState(false);

    const esCerrado = (or: CostosObraRubro) => or.presupuesto?.estatus === 'cerrado';

    const obrasVisibles = obras.filter(
        (o) => incluirCerradas || o.estatus !== 'cerrada',
    );

    const formatMoney = (n: number) =>
        n.toLocaleString('es-MX', { minimumFractionDigits: 2 });

    const getRubroOptionLabel = (or: CostosObraRubro) => {
        const disp = Number(or.presupuestado) - Number(or.acumulado);
        const prefix = `${or.rubro?.codigo} - ${or.rubro?.descripcion}`;
        if (disp <= 0) {
            return `${prefix}  |  SOBREGIRO: -$${formatMoney(Math.abs(disp))}`;
        }
        return `${prefix}  |  Disp: $${formatMoney(disp)}`;
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();

        clearErrors();

        const validationErrors: Record<string, string> = {};

        if (!data.fecha_pago_solicitada) {
            validationErrors.fecha_pago_solicitada =
                'Selecciona la fecha de pago solicitada (un viernes).';
        }

        if (selectedTipo?.rubros) {
            if (data.detalles.length === 0) {
                validationErrors.detalles =
                    'Agrega al menos un detalle con su centro de costos.';
            }
            data.detalles.forEach((det, i) => {
                if (!det.obra_rubro_id) {
                    validationErrors[`detalles.${i}.obra_rubro_id`] =
                        'El centro de costos es obligatorio.';
                }
            });
        } else if (selectedTipo) {
            const monto = parseFloat(data.monto_total);
            if (!data.monto_total || Number.isNaN(monto) || monto <= 0) {
                validationErrors.monto_total =
                    'El monto total debe ser mayor a 0.';
            }
        }

        (selectedTipo?.documentos ?? []).forEach((doc) => {
            const docKey = String(doc.id);
            if ((data.archivos[docKey]?.length ?? 0) === 0) {
                validationErrors[`archivos.${docKey}`] =
                    `Adjunta el documento requerido: ${doc.titulo}.`;
            }
        });

        if (Object.keys(validationErrors).length > 0) {
            setError(validationErrors as Parameters<typeof setError>[0]);
            return;
        }

        transform(() => {
            const formData = new FormData();
            formData.append('departamento_id', data.departamento_id);
            formData.append('proveedor_id', data.proveedor_id);
            formData.append('tipo_solicitud_id', data.tipo_solicitud_id);
            formData.append('concepto', data.concepto);
            formData.append('tipo_pago', data.tipo_pago);
            formData.append('tipo_moneda', data.tipo_moneda);
            formData.append(
                'fecha_pago_solicitada',
                data.fecha_pago_solicitada,
            );

            data.detalles.forEach((det, i) => {
                formData.append(
                    `detalles[${i}][obra_rubro_id]`,
                    det.obra_rubro_id,
                );
                formData.append(`detalles[${i}][concepto]`, det.concepto);
                formData.append(`detalles[${i}][cantidad]`, det.cantidad);
                formData.append(
                    `detalles[${i}][precio_unitario]`,
                    det.precio_unitario,
                );
            });

            // El total del pago es editable en ambos flujos; si se capturó, se
            // envía (manda sobre la suma de detalles en el backend).
            if (data.monto_total) {
                formData.append('monto_total', data.monto_total);
            }

            Object.entries(data.archivos).forEach(([docId, files]) => {
                files.forEach((file, i) => {
                    formData.append(`archivos[${docId}][${i}]`, file);
                });
            });

            Object.entries(data.archivos_texto).forEach(([docId, textos]) => {
                textos.forEach((texto, i) => {
                    formData.append(`archivos_texto[${docId}][${i}]`, texto);
                });
            });

            return formData;
        });

        post('/admin/costos/solicitudes-pago', { forceFormData: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva Solicitud de Pago" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">
                        Nueva Solicitud de Pago
                    </h1>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        {Object.keys(errors).length > 0 && (
                            <div className="alert items-start alert-error">
                                <AlertCircleIcon className="size-5 shrink-0" />
                                <div>
                                    <p className="font-medium">
                                        No se pudo guardar la solicitud
                                    </p>
                                    <p className="text-sm">
                                        Revisa y corrige los campos resaltados
                                        en rojo antes de continuar.
                                    </p>
                                </div>
                            </div>
                        )}

                        {/* Sección 1: Info Básica */}
                        <div className="space-y-4">
                            <h2 className="border-b border-base-300 pb-2 text-lg font-medium">
                                Información Básica
                            </h2>

                            <div className="grid grid-cols-2 gap-4">
                                <FormField
                                    label="Departamento"
                                    htmlFor="departamento_id"
                                    error={errors.departamento_id}
                                    required
                                >
                                    <select
                                        id="departamento_id"
                                        className={`select-bordered select w-full ${errors.departamento_id ? 'select-error' : ''}`}
                                        value={data.departamento_id}
                                        onChange={(e) =>
                                            setData(
                                                'departamento_id',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="">Seleccionar</option>
                                        {departamentos.map((d) => (
                                            <option key={d.id} value={d.id}>
                                                {d.descripcion}
                                            </option>
                                        ))}
                                    </select>
                                </FormField>

                                <FormField
                                    label="Beneficiario"
                                    htmlFor="proveedor_id"
                                    error={errors.proveedor_id}
                                >
                                    <SearchSelect
                                        value={data.proveedor_id}
                                        onValueChange={(value) =>
                                            setData('proveedor_id', value)
                                        }
                                        placeholder="Buscar beneficiario..."
                                        options={[
                                            {
                                                value: '',
                                                label: 'Sin beneficiario',
                                            },
                                            ...proveedores.map((p) => ({
                                                value: String(p.id),
                                                label:
                                                    p.nombre_comercial ||
                                                    p.razon_social,
                                            })),
                                        ]}
                                    />
                                </FormField>
                            </div>

                            <FormField
                                label="Concepto"
                                htmlFor="concepto"
                                error={errors.concepto}
                                required
                            >
                                <textarea
                                    id="concepto"
                                    className={`textarea-bordered textarea w-full ${errors.concepto ? 'textarea-error' : ''}`}
                                    value={data.concepto}
                                    onChange={(e) =>
                                        setData('concepto', e.target.value)
                                    }
                                    rows={2}
                                />
                            </FormField>

                            <div className="grid grid-cols-3 gap-4">
                                <FormField
                                    label="Tipo de Pago"
                                    htmlFor="tipo_pago"
                                    error={errors.tipo_pago}
                                    required
                                >
                                    <select
                                        id="tipo_pago"
                                        className={`select-bordered select w-full ${errors.tipo_pago ? 'select-error' : ''}`}
                                        value={data.tipo_pago}
                                        onChange={(e) =>
                                            setData('tipo_pago', e.target.value)
                                        }
                                    >
                                        <option value="transferencia">
                                            Transferencia
                                        </option>
                                        <option value="cheque">Cheque</option>
                                        <option value="efectivo">
                                            Efectivo
                                        </option>
                                    </select>
                                </FormField>

                                <FormField
                                    label="Moneda"
                                    htmlFor="tipo_moneda"
                                    error={errors.tipo_moneda}
                                    required
                                >
                                    <select
                                        id="tipo_moneda"
                                        className={`select-bordered select w-full ${errors.tipo_moneda ? 'select-error' : ''}`}
                                        value={data.tipo_moneda}
                                        onChange={(e) =>
                                            setData(
                                                'tipo_moneda',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="mxn">MXN</option>
                                        <option value="usd">USD</option>
                                        <option value="eur">EUR</option>
                                    </select>
                                </FormField>

                                <FormField
                                    label="Fecha de Pago Solicitada"
                                    htmlFor="fecha_pago_solicitada"
                                    error={errors.fecha_pago_solicitada}
                                    required
                                >
                                    <Input
                                        id="fecha_pago_solicitada"
                                        type="date"
                                        min={minViernes}
                                        error={!!errors.fecha_pago_solicitada}
                                        value={data.fecha_pago_solicitada}
                                        onChange={(e) =>
                                            handleFechaChange(e.target.value)
                                        }
                                    />
                                    <p className="mt-1 text-[11px] text-base-content/50">
                                        Solo viernes. Corte: miércoles 1:00 PM
                                    </p>
                                </FormField>
                            </div>
                        </div>

                        {/* Sección 2: Tipo Solicitud */}
                        <div className="space-y-4">
                            <h2 className="border-b border-base-300 pb-2 text-lg font-medium">
                                Tipo de Solicitud
                            </h2>

                            <FormField
                                label="Tipo de Solicitud"
                                htmlFor="tipo_solicitud_id"
                                error={errors.tipo_solicitud_id}
                                required
                            >
                                <select
                                    id="tipo_solicitud_id"
                                    className={`select-bordered select w-full ${errors.tipo_solicitud_id ? 'select-error' : ''}`}
                                    value={data.tipo_solicitud_id}
                                    onChange={(e) =>
                                        setData(
                                            'tipo_solicitud_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">Seleccionar tipo</option>
                                    {tipoSolicitudes.map((ts) => (
                                        <option key={ts.id} value={ts.id}>
                                            {ts.titulo}
                                        </option>
                                    ))}
                                </select>
                            </FormField>

                            {selectedTipo?.descripcion && (
                                <p className="text-sm text-base-content/60">
                                    {selectedTipo.descripcion}
                                </p>
                            )}
                        </div>

                        {/* Sección 3: Detalles/Rubros (condicional) */}
                        {selectedTipo?.rubros && (
                            <div className="space-y-4">
                                <div className="flex items-center justify-between border-b border-base-300 pb-2">
                                    <h2 className="text-lg font-medium">
                                        Detalles / Centros de Costos
                                    </h2>
                                    <div className="flex items-center gap-3">
                                        <label className="label cursor-pointer gap-2 py-0">
                                            <input
                                                type="checkbox"
                                                className="checkbox checkbox-xs"
                                                checked={incluirCerradas}
                                                onChange={(e) =>
                                                    setIncluirCerradas(
                                                        e.target.checked,
                                                    )
                                                }
                                            />
                                            <span className="label-text text-xs">
                                                Incluir cerradas
                                            </span>
                                        </label>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={addDetalle}
                                        >
                                            <PlusIcon className="size-4" />
                                            Agregar
                                        </Button>
                                    </div>
                                </div>

                                {errors.detalles && (
                                    <p className="text-sm text-error">
                                        {errors.detalles}
                                    </p>
                                )}

                                {data.detalles.length === 0 ? (
                                    <p
                                        className={`text-sm ${errors.detalles ? 'text-error' : 'text-base-content/60'}`}
                                    >
                                        No hay detalles agregados.
                                    </p>
                                ) : (
                                    <div className="overflow-x-auto rounded-lg border border-base-300">
                                        <table className="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th className="w-8 text-center">
                                                        #
                                                    </th>
                                                    <th className="min-w-[180px]">
                                                        Obra
                                                    </th>
                                                    <th className="min-w-[220px]">
                                                        Centro de Costos
                                                    </th>
                                                    <th className="min-w-[160px]">
                                                        Concepto
                                                    </th>
                                                    <th className="w-24 text-right">
                                                        Cantidad
                                                    </th>
                                                    <th className="w-32 text-right">
                                                        P. Unitario
                                                    </th>
                                                    <th className="w-32 text-right">
                                                        Subtotal
                                                    </th>
                                                    <th className="w-10"></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {data.detalles.map(
                                                    (det, index) => {
                                                        const subtotal =
                                                            calcSubtotal(det);

                                                        return (
                                                            <Fragment
                                                                key={index}
                                                            >
                                                                <tr className="align-top">
                                                                    <td className="text-center text-base-content/50">
                                                                        {index +
                                                                            1}
                                                                    </td>
                                                                    <td>
                                                                        <SearchSelect
                                                                            value={
                                                                                det.obra_id
                                                                            }
                                                                            onValueChange={(
                                                                                v,
                                                                            ) =>
                                                                                updateDetalle(
                                                                                    index,
                                                                                    'obra_id',
                                                                                    v,
                                                                                )
                                                                            }
                                                                            placeholder="Buscar obra..."
                                                                            options={obrasVisibles.map(
                                                                                (
                                                                                    o,
                                                                                ) => ({
                                                                                    value: String(
                                                                                        o.id,
                                                                                    ),
                                                                                    label: `${o.no} - ${o.descripcion}${o.estatus === 'cerrada' ? ' (Cerrada)' : ''}`,
                                                                                }),
                                                                            )}
                                                                        />
                                                                    </td>
                                                                    <td>
                                                                        <SearchSelect
                                                                            value={
                                                                                det.obra_rubro_id
                                                                            }
                                                                            onValueChange={(
                                                                                v,
                                                                            ) =>
                                                                                updateDetalle(
                                                                                    index,
                                                                                    'obra_rubro_id',
                                                                                    v,
                                                                                )
                                                                            }
                                                                            placeholder={
                                                                                det.obra_id
                                                                                    ? 'Buscar centro de costos...'
                                                                                    : 'Seleccione obra primero'
                                                                            }
                                                                            disabled={
                                                                                !det.obra_id
                                                                            }
                                                                            options={obraRubros
                                                                                .filter(
                                                                                    (
                                                                                        or,
                                                                                    ) =>
                                                                                        or.obra_id ===
                                                                                            Number(
                                                                                                det.obra_id,
                                                                                            ) &&
                                                                                        (incluirCerradas ||
                                                                                            !esCerrado(
                                                                                                or,
                                                                                            )),
                                                                                )
                                                                                .map(
                                                                                    (
                                                                                        or,
                                                                                    ) => ({
                                                                                        value: String(
                                                                                            or.id,
                                                                                        ),
                                                                                        label: getRubroOptionLabel(
                                                                                            or,
                                                                                        ),
                                                                                        danger:
                                                                                            Number(
                                                                                                or.presupuestado,
                                                                                            ) -
                                                                                                Number(
                                                                                                    or.acumulado,
                                                                                                ) <=
                                                                                            0,
                                                                                    }),
                                                                                )}
                                                                        />
                                                                        {errors[
                                                                            `detalles.${index}.obra_rubro_id` as keyof typeof errors
                                                                        ] && (
                                                                            <p className="mt-1 text-xs text-error">
                                                                                {
                                                                                    errors[
                                                                                        `detalles.${index}.obra_rubro_id` as keyof typeof errors
                                                                                    ]
                                                                                }
                                                                            </p>
                                                                        )}
                                                                    </td>
                                                                    <td>
                                                                        <input
                                                                            className={`input-bordered input input-sm w-full ${errors[`detalles.${index}.concepto` as keyof typeof errors] ? 'input-error' : ''}`}
                                                                            value={
                                                                                det.concepto
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateDetalle(
                                                                                    index,
                                                                                    'concepto',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                        />
                                                                        {errors[
                                                                            `detalles.${index}.concepto` as keyof typeof errors
                                                                        ] && (
                                                                            <p className="mt-1 text-xs text-error">
                                                                                {
                                                                                    errors[
                                                                                        `detalles.${index}.concepto` as keyof typeof errors
                                                                                    ]
                                                                                }
                                                                            </p>
                                                                        )}
                                                                    </td>
                                                                    <td>
                                                                        <input
                                                                            type="number"
                                                                            step="0.01"
                                                                            min="0.01"
                                                                            className={`input-bordered input input-sm w-full text-right ${errors[`detalles.${index}.cantidad` as keyof typeof errors] ? 'input-error' : ''}`}
                                                                            value={
                                                                                det.cantidad
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateDetalle(
                                                                                    index,
                                                                                    'cantidad',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                        />
                                                                        {errors[
                                                                            `detalles.${index}.cantidad` as keyof typeof errors
                                                                        ] && (
                                                                            <p className="mt-1 text-xs text-error">
                                                                                {
                                                                                    errors[
                                                                                        `detalles.${index}.cantidad` as keyof typeof errors
                                                                                    ]
                                                                                }
                                                                            </p>
                                                                        )}
                                                                    </td>
                                                                    <td>
                                                                        <input
                                                                            type="number"
                                                                            step="0.01"
                                                                            min="0"
                                                                            className={`input-bordered input input-sm w-full text-right ${errors[`detalles.${index}.precio_unitario` as keyof typeof errors] ? 'input-error' : ''}`}
                                                                            value={
                                                                                det.precio_unitario
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateDetalle(
                                                                                    index,
                                                                                    'precio_unitario',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                        />
                                                                        {errors[
                                                                            `detalles.${index}.precio_unitario` as keyof typeof errors
                                                                        ] && (
                                                                            <p className="mt-1 text-xs text-error">
                                                                                {
                                                                                    errors[
                                                                                        `detalles.${index}.precio_unitario` as keyof typeof errors
                                                                                    ]
                                                                                }
                                                                            </p>
                                                                        )}
                                                                    </td>
                                                                    <td className="text-right font-medium whitespace-nowrap">
                                                                        $
                                                                        {subtotal.toLocaleString(
                                                                            'es-MX',
                                                                            {
                                                                                minimumFractionDigits: 2,
                                                                            },
                                                                        )}
                                                                    </td>
                                                                    <td>
                                                                        <button
                                                                            type="button"
                                                                            className="btn text-error btn-ghost btn-xs"
                                                                            onClick={() =>
                                                                                removeDetalle(
                                                                                    index,
                                                                                )
                                                                            }
                                                                        >
                                                                            <Trash2Icon className="size-4" />
                                                                        </button>
                                                                    </td>
                                                                </tr>
                                                            </Fragment>
                                                        );
                                                    },
                                                )}
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td
                                                        colSpan={6}
                                                        className="text-right text-base font-semibold"
                                                    >
                                                        Total
                                                    </td>
                                                    <td className="text-right text-base font-semibold whitespace-nowrap">
                                                        $
                                                        {total.toLocaleString(
                                                            'es-MX',
                                                            {
                                                                minimumFractionDigits: 2,
                                                            },
                                                        )}
                                                    </td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Total del pago: capturado directo (sin rubros) o
                            calculado desde los detalles (con rubros). */}
                        {selectedTipo && (
                            <div className="space-y-4">
                                <div className="border-b border-base-300 pb-2">
                                    <h2 className="text-lg font-medium">
                                        Total del pago
                                    </h2>
                                </div>
                                <FormField
                                    label="Monto total"
                                    htmlFor="monto_total"
                                    error={errors.monto_total}
                                    required
                                >
                                    <Input
                                        id="monto_total"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        placeholder="0.00"
                                        className="w-48"
                                        error={!!errors.monto_total}
                                        value={data.monto_total}
                                        onChange={(e) => {
                                            setMontoManual(true);
                                            setData(
                                                'monto_total',
                                                e.target.value,
                                            );
                                            if (
                                                parseFloat(e.target.value) > 0
                                            ) {
                                                clearErrors('monto_total');
                                            }
                                        }}
                                    />
                                    {selectedTipo.rubros && (
                                        <p className="mt-1 text-xs text-base-content/50">
                                            Suma de detalles: $
                                            {total.toLocaleString('es-MX', {
                                                minimumFractionDigits: 2,
                                            })}
                                            {montoManual && (
                                                <button
                                                    type="button"
                                                    className="ml-2 link link-primary"
                                                    onClick={() => {
                                                        setMontoManual(false);
                                                        setData(
                                                            'monto_total',
                                                            total > 0
                                                                ? total.toFixed(
                                                                      2,
                                                                  )
                                                                : '',
                                                        );
                                                    }}
                                                >
                                                    Usar suma
                                                </button>
                                            )}
                                        </p>
                                    )}
                                </FormField>
                            </div>
                        )}

                        {/* Sección 4: Documentos (condicional) */}
                        {selectedTipo?.documentos &&
                            selectedTipo.documentos.length > 0 && (
                                <div className="space-y-4">
                                    <h2 className="border-b border-base-300 pb-2 text-lg font-medium">
                                        Documentos
                                    </h2>
                                    {selectedTipo.documentos.map((doc) => {
                                        const docKey = String(doc.id);
                                        const files =
                                            data.archivos[docKey] ?? [];
                                        const canAdd =
                                            doc.multiple || files.length === 0;
                                        const docError =
                                            errors[
                                                `archivos.${docKey}` as keyof typeof errors
                                            ];

                                        return (
                                            <div
                                                key={doc.id}
                                                className={`rounded-lg border p-4 ${docError ? 'border-error' : 'border-base-300'}`}
                                            >
                                                <div className="mb-3 flex items-center justify-between">
                                                    <div>
                                                        <h4 className="font-medium">
                                                            {doc.titulo}
                                                            <span className="ml-1 text-error">
                                                                *
                                                            </span>
                                                            {doc.multiple && (
                                                                <span className="ml-2 badge badge-ghost badge-sm">
                                                                    Múltiple
                                                                </span>
                                                            )}
                                                        </h4>
                                                        {doc.texto && (
                                                            <p className="text-xs text-base-content/60">
                                                                {doc.texto}
                                                            </p>
                                                        )}
                                                        {docError && (
                                                            <p className="mt-1 text-xs text-error">
                                                                {docError}
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>

                                                {files.length > 0 && (
                                                    <div className="mb-3 space-y-2">
                                                        {files.map(
                                                            (file, fileIdx) => (
                                                                <div
                                                                    key={
                                                                        fileIdx
                                                                    }
                                                                    className="space-y-2 rounded bg-base-200 p-2"
                                                                >
                                                                    <div className="flex items-center justify-between">
                                                                        <div className="flex items-center gap-2">
                                                                            <FileTextIcon className="size-4 text-base-content/60" />
                                                                            <span className="text-sm">
                                                                                {
                                                                                    file.name
                                                                                }
                                                                            </span>
                                                                        </div>
                                                                        <button
                                                                            type="button"
                                                                            className="btn text-error btn-ghost btn-sm"
                                                                            onClick={() => {
                                                                                const updatedArchivos =
                                                                                    {
                                                                                        ...data.archivos,
                                                                                    };
                                                                                const updatedTextos =
                                                                                    {
                                                                                        ...data.archivos_texto,
                                                                                    };
                                                                                const newFiles =
                                                                                    [
                                                                                        ...files,
                                                                                    ];
                                                                                const newTextos =
                                                                                    [
                                                                                        ...(data
                                                                                            .archivos_texto[
                                                                                            docKey
                                                                                        ] ??
                                                                                            []),
                                                                                    ];
                                                                                newFiles.splice(
                                                                                    fileIdx,
                                                                                    1,
                                                                                );
                                                                                newTextos.splice(
                                                                                    fileIdx,
                                                                                    1,
                                                                                );
                                                                                if (
                                                                                    newFiles.length ===
                                                                                    0
                                                                                ) {
                                                                                    delete updatedArchivos[
                                                                                        docKey
                                                                                    ];
                                                                                    delete updatedTextos[
                                                                                        docKey
                                                                                    ];
                                                                                } else {
                                                                                    updatedArchivos[
                                                                                        docKey
                                                                                    ] =
                                                                                        newFiles;
                                                                                    updatedTextos[
                                                                                        docKey
                                                                                    ] =
                                                                                        newTextos;
                                                                                }
                                                                                setData(
                                                                                    {
                                                                                        ...data,
                                                                                        archivos:
                                                                                            updatedArchivos,
                                                                                        archivos_texto:
                                                                                            updatedTextos,
                                                                                    },
                                                                                );
                                                                            }}
                                                                        >
                                                                            <Trash2Icon className="size-4" />
                                                                        </button>
                                                                    </div>
                                                                    {doc.texto_adicional &&
                                                                        doc.texto && (
                                                                            <Input
                                                                                placeholder={
                                                                                    doc.texto
                                                                                }
                                                                                value={
                                                                                    data
                                                                                        .archivos_texto[
                                                                                        docKey
                                                                                    ]?.[
                                                                                        fileIdx
                                                                                    ] ??
                                                                                    ''
                                                                                }
                                                                                onChange={(
                                                                                    e,
                                                                                ) => {
                                                                                    const updatedTextos =
                                                                                        {
                                                                                            ...data.archivos_texto,
                                                                                        };
                                                                                    const textos =
                                                                                        [
                                                                                            ...(updatedTextos[
                                                                                                docKey
                                                                                            ] ??
                                                                                                []),
                                                                                        ];
                                                                                    textos[
                                                                                        fileIdx
                                                                                    ] =
                                                                                        e.target.value;
                                                                                    updatedTextos[
                                                                                        docKey
                                                                                    ] =
                                                                                        textos;
                                                                                    setData(
                                                                                        'archivos_texto',
                                                                                        updatedTextos,
                                                                                    );
                                                                                }}
                                                                            />
                                                                        )}
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                )}

                                                {canAdd && (
                                                    <div>
                                                        <input
                                                            ref={(el) => {
                                                                fileInputRefs.current[
                                                                    docKey
                                                                ] = el;
                                                            }}
                                                            type="file"
                                                            className="hidden"
                                                            multiple={
                                                                doc.multiple
                                                            }
                                                            onChange={(e) => {
                                                                const files =
                                                                    e.target
                                                                        .files;
                                                                if (
                                                                    files &&
                                                                    files.length >
                                                                        0
                                                                ) {
                                                                    const currentFiles =
                                                                        data
                                                                            .archivos[
                                                                            docKey
                                                                        ] ?? [];
                                                                    const currentTextos =
                                                                        data
                                                                            .archivos_texto[
                                                                            docKey
                                                                        ] ?? [];
                                                                    const newFiles =
                                                                        Array.from(
                                                                            files,
                                                                        );
                                                                    setData({
                                                                        ...data,
                                                                        archivos:
                                                                            {
                                                                                ...data.archivos,
                                                                                [docKey]:
                                                                                    [
                                                                                        ...currentFiles,
                                                                                        ...newFiles,
                                                                                    ],
                                                                            },
                                                                        archivos_texto:
                                                                            {
                                                                                ...data.archivos_texto,
                                                                                [docKey]:
                                                                                    [
                                                                                        ...currentTextos,
                                                                                        ...newFiles.map(
                                                                                            () =>
                                                                                                '',
                                                                                        ),
                                                                                    ],
                                                                            },
                                                                    });
                                                                    clearErrors(
                                                                        `archivos.${docKey}` as Parameters<
                                                                            typeof clearErrors
                                                                        >[0],
                                                                    );
                                                                }
                                                                if (
                                                                    fileInputRefs
                                                                        .current[
                                                                        docKey
                                                                    ]
                                                                ) {
                                                                    fileInputRefs.current[
                                                                        docKey
                                                                    ]!.value =
                                                                        '';
                                                                }
                                                            }}
                                                        />
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() =>
                                                                fileInputRefs.current[
                                                                    docKey
                                                                ]?.click()
                                                            }
                                                        >
                                                            <UploadIcon className="size-4" />
                                                            {files.length > 0
                                                                ? 'Agregar otro archivo'
                                                                : 'Seleccionar archivo'}
                                                        </Button>
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            )}

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/costos/solicitudes-pago">
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
        </AppLayout>
    );
}
