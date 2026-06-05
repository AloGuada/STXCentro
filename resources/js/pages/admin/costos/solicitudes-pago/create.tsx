import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosObraRubro, CostosTipoSolicitud, Departamento, Obra, Proveedor } from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangleIcon, FileTextIcon, Loader2Icon, PlusIcon, Trash2Icon, UploadIcon } from 'lucide-react';
import { type FormEvent, useCallback, useMemo, useRef } from 'react';

function getMinViernes(): string {
    const now = new Date();
    const day = now.getDay(); // 0=dom, 1=lun, ..., 5=vie
    const hour = now.getHours();

    // Calcular el viernes de esta semana
    const viernes = new Date(now);
    viernes.setDate(now.getDate() + (5 - day + 7) % 7);
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

export default function SolicitudesPagoCreate({ departamentos, proveedores, tipoSolicitudes, obras, obraRubros }: Props) {
    const { data, setData, post, processing, errors } = useForm<{
        departamento_id: string;
        proveedor_id: string;
        tipo_solicitud_id: string;
        concepto: string;
        tipo_pago: string;
        tipo_moneda: string;
        fecha_pago_solicitada: string;
        detalles: DetalleForm[];
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
        archivos: {},
        archivos_texto: {},
    });

    const fileInputRefs = useRef<Record<string, HTMLInputElement | null>>({});
    const minViernes = useMemo(() => getMinViernes(), []);

    const handleFechaChange = useCallback((value: string) => {
        if (!value || esViernes(value)) {
            setData('fecha_pago_solicitada', value);
        }
    }, [setData]);

    const selectedTipo = useMemo(
        () => tipoSolicitudes.find((t) => t.id === Number(data.tipo_solicitud_id)),
        [data.tipo_solicitud_id, tipoSolicitudes],
    );

    const addDetalle = () => {
        setData('detalles', [...data.detalles, { obra_id: '', obra_rubro_id: '', concepto: '', cantidad: '1', precio_unitario: '0' }]);
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

    const formatMoney = (n: number) => n.toLocaleString('es-MX', { minimumFractionDigits: 2 });

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

        const formData = new FormData();
        formData.append('departamento_id', data.departamento_id);
        formData.append('proveedor_id', data.proveedor_id);
        formData.append('tipo_solicitud_id', data.tipo_solicitud_id);
        formData.append('concepto', data.concepto);
        formData.append('tipo_pago', data.tipo_pago);
        formData.append('tipo_moneda', data.tipo_moneda);
        formData.append('fecha_pago_solicitada', data.fecha_pago_solicitada);

        data.detalles.forEach((det, i) => {
            formData.append(`detalles[${i}][obra_rubro_id]`, det.obra_rubro_id);
            formData.append(`detalles[${i}][concepto]`, det.concepto);
            formData.append(`detalles[${i}][cantidad]`, det.cantidad);
            formData.append(`detalles[${i}][precio_unitario]`, det.precio_unitario);
        });

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

        router.post('/admin/costos/solicitudes-pago', formData);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva Solicitud de Pago" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nueva Solicitud de Pago</h1>

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

                            <div className="grid grid-cols-3 gap-4">
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

                                <FormField label="Moneda" htmlFor="tipo_moneda" error={errors.tipo_moneda} required>
                                    <select
                                        id="tipo_moneda"
                                        className="select select-bordered w-full"
                                        value={data.tipo_moneda}
                                        onChange={(e) => setData('tipo_moneda', e.target.value)}
                                    >
                                        <option value="mxn">MXN</option>
                                        <option value="usd">USD</option>
                                        <option value="eur">EUR</option>
                                    </select>
                                </FormField>

                                <FormField label="Fecha de Pago Solicitada" htmlFor="fecha_pago_solicitada" error={errors.fecha_pago_solicitada}>
                                    <Input
                                        id="fecha_pago_solicitada"
                                        type="date"
                                        min={minViernes}
                                        value={data.fecha_pago_solicitada}
                                        onChange={(e) => handleFechaChange(e.target.value)}
                                    />
                                    <p className="mt-1 text-[11px] text-base-content/50">Solo viernes. Corte: miércoles 1:00 PM</p>
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

                            {selectedTipo?.descripcion && (
                                <p className="text-sm text-base-content/60">{selectedTipo.descripcion}</p>
                            )}
                        </div>

                        {/* Sección 3: Detalles/Rubros (condicional) */}
                        {selectedTipo?.rubros && (
                            <div className="space-y-4">
                                <div className="flex items-center justify-between border-b border-base-300 pb-2">
                                    <h2 className="text-lg font-medium">Detalles / Centros de Costos</h2>
                                    <Button type="button" variant="outline" onClick={addDetalle}>
                                        <PlusIcon className="size-4" />
                                        Agregar
                                    </Button>
                                </div>

                                {data.detalles.length === 0 && (
                                    <p className="text-sm text-base-content/60">No hay detalles agregados.</p>
                                )}

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
                                                                <option key={or.id} value={or.id}>
                                                                    {getRubroOptionLabel(or)}
                                                                </option>
                                                            ))}
                                                    </select>
                                                </FormField>
                                            </div>

                                            {disponible !== null && (() => {
                                                const or = obraRubros.find((r) => r.id === Number(det.obra_rubro_id));
                                                const presupuestado = or ? Number(or.presupuestado) : 0;
                                                const porcentajeUsado = presupuestado > 0 ? ((presupuestado - disponible) / presupuestado) * 100 : 0;
                                                const sobregiro = disponible <= 0;

                                                return (
                                                    <div className={`rounded-lg px-3 py-2 text-xs ${sobregiro ? 'bg-error/10 border border-error/30' : excede ? 'bg-warning/10 border border-warning/30' : 'bg-base-200'}`}>
                                                        <div className="flex items-center justify-between mb-1">
                                                            <span className={sobregiro ? 'text-error font-semibold' : excede ? 'text-warning font-semibold' : 'text-base-content/70'}>
                                                                {sobregiro ? (
                                                                    <span className="inline-flex items-center gap-1">
                                                                        <AlertTriangleIcon className="size-3" /> SOBREGIRO: -${formatMoney(Math.abs(disponible))}
                                                                    </span>
                                                                ) : (
                                                                    <>Disponible: ${formatMoney(disponible)}</>
                                                                )}
                                                            </span>
                                                            <span className="text-base-content/50">
                                                                Presupuestado: ${formatMoney(presupuestado)}
                                                            </span>
                                                        </div>
                                                        {presupuestado > 0 && (
                                                            <div className="w-full bg-base-300 rounded-full h-1.5">
                                                                <div
                                                                    className={`h-1.5 rounded-full ${sobregiro ? 'bg-error' : porcentajeUsado > 80 ? 'bg-warning' : 'bg-success'}`}
                                                                    style={{ width: `${Math.min(porcentajeUsado, 100)}%` }}
                                                                />
                                                            </div>
                                                        )}
                                                        {excede && !sobregiro && (
                                                            <p className="text-warning mt-1 inline-flex items-center gap-1">
                                                                <AlertTriangleIcon className="size-3" /> El subtotal (${formatMoney(subtotal)}) excede el disponible
                                                            </p>
                                                        )}
                                                    </div>
                                                );
                                            })()}

                                            <FormField label="Concepto" htmlFor={`det_concepto_${index}`} error={errors[`detalles.${index}.concepto` as keyof typeof errors]} required>
                                                <Input
                                                    id={`det_concepto_${index}`}
                                                    value={det.concepto}
                                                    onChange={(e) => updateDetalle(index, 'concepto', e.target.value)}
                                                />
                                            </FormField>

                                            <div className="grid grid-cols-3 gap-4">
                                                <FormField label="Cantidad" htmlFor={`det_cant_${index}`} error={errors[`detalles.${index}.cantidad` as keyof typeof errors]} required>
                                                    <Input
                                                        id={`det_cant_${index}`}
                                                        type="number"
                                                        step="0.01"
                                                        min="0.01"
                                                        value={det.cantidad}
                                                        onChange={(e) => updateDetalle(index, 'cantidad', e.target.value)}
                                                    />
                                                </FormField>

                                                <FormField label="Precio Unitario" htmlFor={`det_precio_${index}`} error={errors[`detalles.${index}.precio_unitario` as keyof typeof errors]} required>
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

                        {/* Sección 4: Documentos (condicional) */}
                        {selectedTipo?.documentos && selectedTipo.documentos.length > 0 && (
                            <div className="space-y-4">
                                <h2 className="text-lg font-medium border-b border-base-300 pb-2">Documentos</h2>
                                {selectedTipo.documentos.map((doc) => {
                                    const docKey = String(doc.id);
                                    const files = data.archivos[docKey] ?? [];
                                    const canAdd = doc.multiple || files.length === 0;

                                    return (
                                        <div key={doc.id} className="rounded-lg border border-base-300 p-4">
                                            <div className="flex items-center justify-between mb-3">
                                                <div>
                                                    <h4 className="font-medium">
                                                        {doc.titulo}
                                                        {doc.multiple && <span className="ml-2 badge badge-sm badge-ghost">Múltiple</span>}
                                                    </h4>
                                                    {doc.texto && <p className="text-xs text-base-content/60">{doc.texto}</p>}
                                                </div>
                                            </div>

                                            {files.length > 0 && (
                                                <div className="space-y-2 mb-3">
                                                    {files.map((file, fileIdx) => (
                                                        <div key={fileIdx} className="rounded bg-base-200 p-2 space-y-2">
                                                            <div className="flex items-center justify-between">
                                                                <div className="flex items-center gap-2">
                                                                    <FileTextIcon className="size-4 text-base-content/60" />
                                                                    <span className="text-sm">{file.name}</span>
                                                                </div>
                                                                <button
                                                                    type="button"
                                                                    className="btn btn-ghost btn-sm text-error"
                                                                    onClick={() => {
                                                                        const updatedArchivos = { ...data.archivos };
                                                                        const updatedTextos = { ...data.archivos_texto };
                                                                        const newFiles = [...files];
                                                                        const newTextos = [...(data.archivos_texto[docKey] ?? [])];
                                                                        newFiles.splice(fileIdx, 1);
                                                                        newTextos.splice(fileIdx, 1);
                                                                        if (newFiles.length === 0) {
                                                                            delete updatedArchivos[docKey];
                                                                            delete updatedTextos[docKey];
                                                                        } else {
                                                                            updatedArchivos[docKey] = newFiles;
                                                                            updatedTextos[docKey] = newTextos;
                                                                        }
                                                                        setData({ ...data, archivos: updatedArchivos, archivos_texto: updatedTextos });
                                                                    }}
                                                                >
                                                                    <Trash2Icon className="size-4" />
                                                                </button>
                                                            </div>
                                                            {doc.texto_adicional && doc.texto && (
                                                                <Input
                                                                    placeholder={doc.texto}
                                                                    value={data.archivos_texto[docKey]?.[fileIdx] ?? ''}
                                                                    onChange={(e) => {
                                                                        const updatedTextos = { ...data.archivos_texto };
                                                                        const textos = [...(updatedTextos[docKey] ?? [])];
                                                                        textos[fileIdx] = e.target.value;
                                                                        updatedTextos[docKey] = textos;
                                                                        setData('archivos_texto', updatedTextos);
                                                                    }}
                                                                />
                                                            )}
                                                        </div>
                                                    ))}
                                                </div>
                                            )}

                                            {canAdd && (
                                                <div>
                                                    <input
                                                        ref={(el) => { fileInputRefs.current[docKey] = el; }}
                                                        type="file"
                                                        className="hidden"
                                                        multiple={doc.multiple}
                                                        onChange={(e) => {
                                                            const files = e.target.files;
                                                            if (files && files.length > 0) {
                                                                const currentFiles = data.archivos[docKey] ?? [];
                                                                const currentTextos = data.archivos_texto[docKey] ?? [];
                                                                const newFiles = Array.from(files);
                                                                setData({
                                                                    ...data,
                                                                    archivos: { ...data.archivos, [docKey]: [...currentFiles, ...newFiles] },
                                                                    archivos_texto: { ...data.archivos_texto, [docKey]: [...currentTextos, ...newFiles.map(() => '')] },
                                                                });
                                                            }
                                                            if (fileInputRefs.current[docKey]) {
                                                                fileInputRefs.current[docKey]!.value = '';
                                                            }
                                                        }}
                                                    />
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => fileInputRefs.current[docKey]?.click()}
                                                    >
                                                        <UploadIcon className="size-4" />
                                                        {files.length > 0 ? 'Agregar otro archivo' : 'Seleccionar archivo'}
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
