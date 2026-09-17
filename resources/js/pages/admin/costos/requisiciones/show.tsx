import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangleIcon } from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import { ActivityTimeline } from '@/components/costos/activity-timeline';
import { CancelarModal } from '@/components/costos/cancelar-modal';
import { CotizacionMatriz } from '@/components/costos/cotizacion-matriz';
import { DocumentosCotizacion } from '@/components/costos/documentos-cotizacion';
import { LiberarRequisicionModal } from '@/components/costos/liberar-requisicion-modal';
import { formatMoney as fmtMonto } from '@/components/costos/monto';
import { OcBuilder } from '@/components/costos/oc-builder';
import {
    baseImpuestos,
    calcularRetenciones,
    IVA_RATE,
    type LineaFiscal,
} from '@/components/costos/retenciones';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { SharedData } from '@/types';
import type {
    CostosRequisicion,
    CostosRequisicionDetalle,
    CostosTipoFiscalPartida,
    CostosTipoMoneda,
    CostosUsoCfdi,
    ObraRubroOption,
    Proveedor,
} from '@/types/models';
import {
    REQUISICION_ESTATUS_COLORS,
    REQUISICION_ESTATUS_LABELS,
    TIPO_MONEDA_LABELS,
} from '@/types/models';

type Alternativa = {
    cotizacion_precio_id: number;
    proveedor_id: number;
    proveedor: string | null;
    precio_unitario: number;
    moneda: string;
};

type PartidaValidar = {
    requisicion_detalle_id: number;
    descripcion: string;
    alternativas: Alternativa[];
};

type ProveedorPorValidar = {
    id: number;
    razon_social: string;
    rfc: string | null;
    tipo_persona: string | null;
    regimen: string | null;
    banco: string | null;
    titular_cuenta: string | null;
    numero_cuenta: string | null;
    clabe: string | null;
    tarjeta: string | null;
    moneda_cuenta: string | null;
    constancia_url: string | null;
    caratula_url: string | null;
    partidas: PartidaValidar[];
};

type Props = {
    requisicion: CostosRequisicion;
    proveedores: Pick<
        Proveedor,
        | 'id'
        | 'razon_social'
        | 'nombre_comercial'
        | 'maneja_credito'
        | 'tipo_persona'
        | 'regimen_fiscal'
    >[];
    obraRubros: ObraRubroOption[];
    usosCfdi: Pick<CostosUsoCfdi, 'id' | 'clave' | 'descripcion'>[];
    // Último precio de cada proveedor para el insumo de cada partida, en otras
    // requisiciones. Clave: "productoId|proveedorId".
    preciosPrevios: Record<string, number>;
    aprobacionPendienteId: number | null;
    esUltimoNivel: boolean;
    proveedoresPorValidar: ProveedorPorValidar[];
};

type Tab = 'datos' | 'cotizacion' | 'definir-oc' | 'aprobacion' | 'ocs';

/**
 * El bloque "Total de las órdenes de compra" del resumen queda oculto de
 * momento: se confunde con el total del comparativo, que sí incluye las
 * partidas "solo cotización". El cálculo se conserva para volver a mostrarlo.
 */
const MOSTRAR_TOTAL_OCS = false;

const fmtDate = (date: string | null) =>
    date
        ? new Date(date).toLocaleDateString('es-MX', {
              day: '2-digit',
              month: '2-digit',
              year: 'numeric',
          })
        : '-';

function FirmarRequisicionModal({
    aprobacionId,
    tipo,
    onClose,
}: {
    aprobacionId: number;
    tipo: 'aprobar' | 'rechazar';
    onClose: () => void;
}) {
    const esAprobacion = tipo === 'aprobar';
    const minLen = esAprobacion ? 1 : 10;
    const { data, setData, post, processing, errors, reset } = useForm({
        observaciones: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/admin/costos/aprobaciones/${aprobacionId}/${tipo}`, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    const tooShort = data.observaciones.trim().length < minLen;

    return (
        <dialog className="modal-open modal">
            <div className="modal-box w-11/12 max-w-4xl">
                <h2 className="text-2xl font-bold">
                    {esAprobacion
                        ? 'Aprobar requisición'
                        : 'Rechazar requisición'}
                </h2>
                <p className="mt-1 text-sm text-base-content/60">
                    {esAprobacion
                        ? 'Agregue sus observaciones para firmar esta requisición.'
                        : 'El rechazo cancelará la requisición y deberá rehacerse. Mínimo 10 caracteres.'}
                </p>
                <form onSubmit={handleSubmit} className="mt-6">
                    <div className="form-control">
                        <label className="mb-2 text-sm font-medium">
                            Observaciones
                        </label>
                        <textarea
                            className={`textarea-bordered textarea w-full ${errors.observaciones ? 'textarea-error' : ''}`}
                            rows={6}
                            placeholder={
                                esAprobacion
                                    ? 'Escriba sus observaciones...'
                                    : 'Motivo del rechazo...'
                            }
                            value={data.observaciones}
                            onChange={(e) =>
                                setData('observaciones', e.target.value)
                            }
                            required
                            maxLength={500}
                        />
                        {errors.observaciones && (
                            <span className="mt-1 text-xs text-error">
                                {errors.observaciones}
                            </span>
                        )}
                    </div>
                    <div className="modal-action">
                        <button
                            type="button"
                            className="btn"
                            onClick={onClose}
                            disabled={processing}
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            className={`btn ${esAprobacion ? 'bg-green-600 text-white hover:bg-green-700' : 'btn-error'}`}
                            disabled={processing || tooShort}
                        >
                            {esAprobacion ? 'Aprobar' : 'Rechazar'}
                        </button>
                    </div>
                </form>
            </div>
            <div className="modal-backdrop" onClick={onClose} />
        </dialog>
    );
}

function EnviarAprobacionModal({
    requisicionId,
    tieneOc,
    cotizacionCompleta,
    empresasCotizando,
    minEmpresas,
    onClose,
}: {
    requisicionId: number;
    tieneOc: boolean;
    cotizacionCompleta: boolean;
    empresasCotizando: number;
    minEmpresas: number;
    onClose: () => void;
}) {
    const { post, processing } = useForm({});

    const handleEnviar = () => {
        post(`/admin/costos/requisiciones/${requisicionId}/enviar-aprobacion`, {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return (
        <dialog className="modal-open modal">
            <div className="modal-box">
                {!tieneOc ? (
                    <>
                        <h2 className="flex items-center gap-2 text-xl font-bold text-error">
                            <AlertTriangleIcon className="size-5" /> Falta
                            definir la orden de compra
                        </h2>
                        <p className="mt-3 text-sm text-base-content/70">
                            No hay ninguna orden de compra definida para esta
                            requisición. Ve a la pestaña{' '}
                            <strong>Definir OC</strong> y define al menos una OC
                            antes de enviarla a aprobación.
                        </p>
                        <div className="modal-action">
                            <button
                                type="button"
                                className="btn"
                                onClick={onClose}
                            >
                                Entendido
                            </button>
                        </div>
                    </>
                ) : !cotizacionCompleta ? (
                    <>
                        <h2 className="flex items-center gap-2 text-xl font-bold text-error">
                            <AlertTriangleIcon className="size-5" /> Cotización
                            incompleta
                        </h2>
                        <p className="mt-3 text-sm text-base-content/70">
                            La cotización debe comparar al menos{' '}
                            <strong>{minEmpresas} proveedores</strong> (tiene{' '}
                            <strong>{empresasCotizando}</strong>). Ve a la
                            pestaña <strong>Cotización</strong> y agrega más
                            proveedores.
                        </p>
                        <div className="modal-action">
                            <button
                                type="button"
                                className="btn"
                                onClick={onClose}
                            >
                                Entendido
                            </button>
                        </div>
                    </>
                ) : (
                    <>
                        <h2 className="text-xl font-bold">
                            Enviar a aprobación
                        </h2>
                        <p className="mt-3 text-sm text-base-content/70">
                            ¿Todo está correcto? Al aceptar, la requisición pasa
                            a <strong>Pendiente de aprobación interna</strong>{' '}
                            (bandeja del gerente) para su aprobación interna y se
                            bloquearán las ediciones.
                        </p>
                        <div className="modal-action">
                            <button
                                type="button"
                                className="btn"
                                onClick={onClose}
                                disabled={processing}
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                className="btn btn-primary"
                                onClick={handleEnviar}
                                disabled={processing}
                            >
                                {processing ? 'Enviando...' : 'Aceptar'}
                            </button>
                        </div>
                    </>
                )}
            </div>
            <div className="modal-backdrop" onClick={onClose} />
        </dialog>
    );
}

function PuntoControlModal({
    requisicionId,
    onClose,
}: {
    requisicionId: number;
    onClose: () => void;
}) {
    const { post, processing } = useForm({});

    const confirmar = () => {
        post(`/admin/costos/requisiciones/${requisicionId}/aprobar-interno`, {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return (
        <dialog className="modal-open modal">
            <div className="modal-box">
                <h2 className="text-xl font-bold">Aprobación interna</h2>
                <p className="mt-3 text-sm text-base-content/70">
                    ¿Estás seguro? Al dar tu aprobación interna confirmas que
                    la cotización fue revisada y habilitas al auxiliar a{' '}
                    <strong>mandar a aprobación</strong>.
                </p>
                <div className="modal-action">
                    <button
                        type="button"
                        className="btn"
                        onClick={onClose}
                        disabled={processing}
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        className="btn btn-primary"
                        onClick={confirmar}
                        disabled={processing}
                    >
                        {processing ? 'Guardando...' : 'Sí, marcar'}
                    </button>
                </div>
            </div>
            <div className="modal-backdrop" onClick={onClose} />
        </dialog>
    );
}

type Decision = {
    accion: 'activar' | 'rechazar';
    // requisicion_detalle_id -> cotizacion_precio_id seleccionada como reemplazo
    reemplazos: Record<number, number | ''>;
};

function ValidacionProveedoresModal({
    requisicionId,
    proveedores,
    onClose,
}: {
    requisicionId: number;
    proveedores: ProveedorPorValidar[];
    onClose: () => void;
}) {
    const [step, setStep] = useState<1 | 2>(1);
    const [observaciones, setObservaciones] = useState('');
    const [processing, setProcessing] = useState(false);
    const [decisiones, setDecisiones] = useState<Record<number, Decision>>(() =>
        Object.fromEntries(
            proveedores.map((p) => [
                p.id,
                { accion: 'activar', reemplazos: {} },
            ]),
        ),
    );

    const setAccion = (provId: number, accion: 'activar' | 'rechazar') =>
        setDecisiones((prev) => ({
            ...prev,
            [provId]: { ...prev[provId], accion },
        }));

    const setReemplazo = (
        provId: number,
        detalleId: number,
        cotId: number | '',
    ) =>
        setDecisiones((prev) => ({
            ...prev,
            [provId]: {
                ...prev[provId],
                reemplazos: { ...prev[provId].reemplazos, [detalleId]: cotId },
            },
        }));

    // Un rechazo sin reemplazo en TODAS sus partidas implica rechazar la requisición.
    const rechazaRequisicion = proveedores.some((p) => {
        const d = decisiones[p.id];
        if (d.accion !== 'rechazar') return false;
        return p.partidas.some(
            (part) => !d.reemplazos[part.requisicion_detalle_id],
        );
    });

    const submit = () => {
        const validaciones = proveedores.map((p) => {
            const d = decisiones[p.id];
            const reemplazos =
                d.accion === 'rechazar'
                    ? p.partidas
                          .filter(
                              (part) =>
                                  d.reemplazos[part.requisicion_detalle_id],
                          )
                          .map((part) => {
                              const cotId = d.reemplazos[
                                  part.requisicion_detalle_id
                              ] as number;
                              const alt = part.alternativas.find(
                                  (a) => a.cotizacion_precio_id === cotId,
                              )!;
                              return {
                                  requisicion_detalle_id:
                                      part.requisicion_detalle_id,
                                  nuevo_proveedor_id: alt.proveedor_id,
                                  cotizacion_precio_id: cotId,
                              };
                          })
                    : [];

            return { proveedor_id: p.id, accion: d.accion, reemplazos };
        });

        setProcessing(true);
        router.post(
            `/admin/costos/requisiciones/${requisicionId}/firmar-final`,
            { observaciones, validaciones },
            {
                preserveScroll: true,
                onSuccess: () => onClose(),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <dialog className="modal-open modal">
            <div className="modal-box w-11/12 max-w-5xl">
                <h2 className="text-2xl font-bold">
                    {step === 1
                        ? 'Validar documentación del proveedor'
                        : 'Confirmar firma'}
                </h2>
                <ul className="steps my-4 steps-horizontal w-full">
                    <li className={`step ${step >= 1 ? 'step-primary' : ''}`}>
                        Documentación
                    </li>
                    <li className={`step ${step >= 2 ? 'step-primary' : ''}`}>
                        Aprobar OC
                    </li>
                </ul>

                {step === 1 && (
                    <div className="space-y-6">
                        {proveedores.map((p) => {
                            const d = decisiones[p.id];
                            return (
                                <div
                                    key={p.id}
                                    className="rounded-lg border border-base-300 p-4"
                                >
                                    <div className="mb-3 flex items-center justify-between">
                                        <div>
                                            <div className="font-semibold">
                                                {p.razon_social}
                                            </div>
                                            <div className="text-xs text-base-content/60">
                                                {p.rfc} ·{' '}
                                                {p.tipo_persona === 'moral'
                                                    ? 'Persona Moral'
                                                    : 'Persona Física'}{' '}
                                                · {p.regimen ?? 'Sin régimen'}
                                            </div>
                                        </div>
                                        <div className="join">
                                            <button
                                                type="button"
                                                className={`btn join-item btn-sm ${d.accion === 'activar' ? 'btn-success' : 'btn-ghost'}`}
                                                onClick={() =>
                                                    setAccion(p.id, 'activar')
                                                }
                                            >
                                                Activar
                                            </button>
                                            <button
                                                type="button"
                                                className={`btn join-item btn-sm ${d.accion === 'rechazar' ? 'btn-error' : 'btn-ghost'}`}
                                                onClick={() =>
                                                    setAccion(p.id, 'rechazar')
                                                }
                                            >
                                                Rechazar
                                            </button>
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-2 gap-3 text-sm">
                                        <div>
                                            <span className="text-base-content/60">
                                                Banco:{' '}
                                            </span>
                                            {p.banco ?? '-'} · {p.moneda_cuenta}
                                        </div>
                                        <div>
                                            <span className="text-base-content/60">
                                                Titular:{' '}
                                            </span>
                                            {p.titular_cuenta ?? '-'}
                                        </div>
                                        <div>
                                            <span className="text-base-content/60">
                                                CLABE/Tarjeta/Cuenta:{' '}
                                            </span>
                                            {p.clabe ||
                                                p.tarjeta ||
                                                p.numero_cuenta ||
                                                '-'}
                                        </div>
                                        <div className="flex gap-3">
                                            {p.constancia_url && (
                                                <a
                                                    className="link link-primary"
                                                    href={p.constancia_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    Constancia
                                                </a>
                                            )}
                                            {p.caratula_url && (
                                                <a
                                                    className="link link-primary"
                                                    href={p.caratula_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    Carátula
                                                </a>
                                            )}
                                        </div>
                                    </div>

                                    {d.accion === 'rechazar' && (
                                        <div className="mt-4 space-y-2 rounded bg-base-200 p-3">
                                            <p className="text-sm font-medium">
                                                Reasignar partidas a otro
                                                proveedor que cotizó:
                                            </p>
                                            {p.partidas.map((part) => (
                                                <div
                                                    key={
                                                        part.requisicion_detalle_id
                                                    }
                                                    className="flex items-center justify-between gap-2"
                                                >
                                                    <span className="text-sm">
                                                        {part.descripcion}
                                                    </span>
                                                    <select
                                                        className="select-bordered select w-72 select-sm"
                                                        value={
                                                            d.reemplazos[
                                                                part
                                                                    .requisicion_detalle_id
                                                            ] ?? ''
                                                        }
                                                        onChange={(e) =>
                                                            setReemplazo(
                                                                p.id,
                                                                part.requisicion_detalle_id,
                                                                e.target.value
                                                                    ? Number(
                                                                          e
                                                                              .target
                                                                              .value,
                                                                      )
                                                                    : '',
                                                            )
                                                        }
                                                    >
                                                        <option value="">
                                                            — Rechazar (sin
                                                            reemplazo) —
                                                        </option>
                                                        {part.alternativas.map(
                                                            (a) => (
                                                                <option
                                                                    key={
                                                                        a.cotizacion_precio_id
                                                                    }
                                                                    value={
                                                                        a.cotizacion_precio_id
                                                                    }
                                                                >
                                                                    {
                                                                        a.proveedor
                                                                    }{' '}
                                                                    ·{' '}
                                                                    {fmtMonto(
                                                                        a.precio_unitario,
                                                                        a.moneda,
                                                                    )}
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}

                {step === 2 && (
                    <div className="space-y-4">
                        {rechazaRequisicion ? (
                            <div className="alert alert-error">
                                <AlertTriangleIcon className="size-5" />
                                <span>
                                    Hay un proveedor rechazado sin reemplazo: al
                                    confirmar se{' '}
                                    <strong>
                                        rechazará la requisición completa
                                    </strong>
                                    .
                                </span>
                            </div>
                        ) : (
                            <div className="alert alert-success">
                                <span>
                                    Al confirmar se activarán los proveedores
                                    aprobados y la requisición quedará{' '}
                                    <strong>aprobada</strong>.
                                </span>
                            </div>
                        )}
                        <div className="form-control">
                            <label className="mb-2 text-sm font-medium">
                                Observaciones
                            </label>
                            <textarea
                                className="textarea-bordered textarea w-full"
                                rows={4}
                                value={observaciones}
                                onChange={(e) =>
                                    setObservaciones(e.target.value)
                                }
                                maxLength={500}
                                placeholder="Observaciones de la firma..."
                            />
                        </div>
                    </div>
                )}

                <div className="modal-action">
                    <button
                        type="button"
                        className="btn"
                        onClick={onClose}
                        disabled={processing}
                    >
                        Cancelar
                    </button>
                    {step === 1 ? (
                        <button
                            type="button"
                            className="btn btn-primary"
                            onClick={() => setStep(2)}
                        >
                            Continuar
                        </button>
                    ) : (
                        <>
                            <button
                                type="button"
                                className="btn"
                                onClick={() => setStep(1)}
                                disabled={processing}
                            >
                                Atrás
                            </button>
                            <button
                                type="button"
                                className={`btn ${rechazaRequisicion ? 'btn-error' : 'bg-green-600 text-white hover:bg-green-700'}`}
                                onClick={submit}
                                disabled={
                                    processing ||
                                    observaciones.trim().length < 1
                                }
                            >
                                {rechazaRequisicion
                                    ? 'Rechazar requisición'
                                    : 'Firmar y aprobar'}
                            </button>
                        </>
                    )}
                </div>
            </div>
            <div className="modal-backdrop" onClick={onClose} />
        </dialog>
    );
}

/**
 * Lo que compras canceló después en la orden. No vive en la requisición: sale
 * de los renglones de OC que nacieron de esta partida
 * (`requisicion_detalle_id`), así que sólo aparece cuando ya hubo orden.
 */
function CanceladoEnOc({ detalle }: { detalle: { cantidad: number; unidad: string; orden_compra_detalles?: { cantidad_cancelada: number; orden_compra?: { folio: string } | null }[] } }) {
    const renglones = (detalle.orden_compra_detalles ?? []).filter((o) => Number(o.cantidad_cancelada) > 0);

    if (renglones.length === 0) {
        return null;
    }

    const cancelado = renglones.reduce((acc, o) => acc + Number(o.cantidad_cancelada), 0);
    const folios = renglones.map((o) => o.orden_compra?.folio).filter(Boolean).join(', ');

    return (
        <div className="text-error text-xs font-normal">
            −{cancelado.toLocaleString('es-MX', { maximumFractionDigits: 4 })} cancelado en OC{folios ? ` ${folios}` : ''}
            <div className="text-base-content/60">
                quedan {Math.max(0, Number(detalle.cantidad) - cancelado).toLocaleString('es-MX', { maximumFractionDigits: 4 })} {detalle.unidad}
            </div>
        </div>
    );
}

export default function RequisicionesShow({
    requisicion,
    proveedores,
    obraRubros,
    usosCfdi,
    preciosPrevios,
    aprobacionPendienteId,
    esUltimoNivel,
    proveedoresPorValidar,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/requisiciones' },
        { title: 'Requisiciones', href: '/admin/costos/requisiciones' },
        {
            title: requisicion.folio,
            href: `/admin/costos/requisiciones/${requisicion.id}`,
        },
    ];

    const { can } = useCan();
    const [tab, setTab] = useState<Tab>('datos');
    const [enviarAprobacion, setEnviarAprobacion] = useState(false);
    const [marcandoControl, setMarcandoControl] = useState(false);
    const [cancelando, setCancelando] = useState(false);
    const [liberando, setLiberando] = useState(false);
    const [firmando, setFirmando] = useState<'aprobar' | 'rechazar' | null>(
        null,
    );
    const [validando, setValidando] = useState(false);

    const requiereValidacion =
        esUltimoNivel && proveedoresPorValidar.length > 0;

    const editable = ['borrador', 'rechazada'].includes(requisicion.estatus);
    const cotizable = ['borrador', 'rechazada', 'aprobada'].includes(
        requisicion.estatus,
    );
    const tieneOcDefinida = (requisicion.ocs?.length ?? 0) > 0;
    // Nivel/usuario que tiene la firma pendiente en la etapa formal: se muestra
    // debajo del estatus para saber en manos de quién está la aprobación.
    const aprobacionPendiente = useMemo(() => {
        if (requisicion.estatus !== 'pendiente_aprobacion') {
            return null;
        }
        const pendientes = (requisicion.aprobaciones ?? []).filter(
            (a) => a.estatus === 'pendiente',
        );
        if (pendientes.length === 0) {
            return null;
        }
        const nivel = Math.min(...pendientes.map((a) => a.nivel));
        const nombres = [
            ...new Set(
                pendientes
                    .filter((a) => a.nivel === nivel)
                    .map((a) => a.aprobador?.name)
                    .filter(Boolean),
            ),
        ].join(' / ');
        return { nivel, nombres: nombres || 'Sin asignar' };
    }, [requisicion.estatus, requisicion.aprobaciones]);

    const MIN_EMPRESAS_COTIZACION = requisicion.modo_dedazo ? 1 : 3;
    // La cotización se compara a nivel requisición: basta con tener al menos
    // MIN_EMPRESAS_COTIZACION proveedores distintos en total (no por partida).
    const empresasCotizando = new Set(
        (requisicion.detalles ?? []).flatMap((d) =>
            (d.cotizaciones ?? []).map((c) => c.proveedor_id),
        ),
    ).size;
    const cotizacionCompleta = empresasCotizando >= MIN_EMPRESAS_COTIZACION;

    // Total neto a pagar cuando ya hay OC(s) definidas: agrupa las selecciones
    // por (proveedor, OC), calcula retenciones por grupo (espeja el OcBuilder) y
    // suma el neto de todas. Las partidas "solo cotización" no se adjudican a
    // ninguna OC, pero su precio de referencia sí entra al neto (espeja
    // ComparativoTotalesBuilder, que es el que arma el PDF).
    const resumenNeto = useMemo(() => {
        const provMap = new Map(proveedores.map((p) => [p.id, p]));
        const grupos = new Map<
            string,
            {
                proveedorId: number;
                moneda: string;
                lines: LineaFiscal[];
            }
        >();
        (requisicion.detalles ?? []).forEach((d) => {
            // Una partida solo cotización no se adjudica: si arrastra alguna
            // selección vieja se ignora, su importe entra por referencia abajo.
            if (d.solo_cotizacion) return;
            (d.selecciones ?? []).forEach((s) => {
                const moneda = (
                    s.cotizacion_precio?.moneda ?? 'mxn'
                ).toLowerCase();
                const key = `${s.proveedor_id}|${s.numero_oc ?? 1}|${moneda}`;
                const sub =
                    Number(s.cotizacion_precio?.precio_unitario ?? 0) *
                    Number(s.cantidad);
                const g = grupos.get(key) ?? {
                    proveedorId: s.proveedor_id,
                    moneda,
                    lines: [],
                };
                g.lines.push({
                    tipo_fiscal: d.tipo_fiscal,
                    subtotal: sub,
                    sin_impuestos: d.sin_impuestos,
                });
                grupos.set(key, g);
            });
        });
        if (grupos.size === 0) return null;
        const porMoneda = new Map<
            string,
            { subtotal: number; baseIva: number; ret: number; soloCot: number }
        >();
        grupos.forEach((g) => {
            const acc = porMoneda.get(g.moneda) ?? {
                subtotal: 0,
                baseIva: 0,
                ret: 0,
                soloCot: 0,
            };
            acc.subtotal += g.lines.reduce((a, l) => a + l.subtotal, 0);
            acc.baseIva += baseImpuestos(g.lines);
            acc.ret += calcularRetenciones(
                provMap.get(g.proveedorId),
                g.lines,
            ).reduce((a, r) => a + r.monto, 0);
            porMoneda.set(g.moneda, acc);
        });
        // Precio de referencia de las partidas "solo cotización": el del mejor
        // proveedor global si cotizó la partida, si no el menor cotizado (misma
        // regla best-case del comparativo).
        const mejorId = requisicion.mejor_proveedor?.id ?? null;
        (requisicion.detalles ?? []).forEach((d) => {
            if (!d.solo_cotizacion) return;
            const conPrecio = (d.cotizaciones ?? []).filter(
                (c) => Number(c.precio_unitario) > 0,
            );
            if (conPrecio.length === 0) return;
            const delMejor = mejorId
                ? conPrecio.filter((c) => c.proveedor_id === mejorId)
                : [];
            const cot = (delMejor.length > 0 ? delMejor : conPrecio).reduce(
                (a, b) =>
                    Number(a.precio_unitario) <= Number(b.precio_unitario)
                        ? a
                        : b,
            );
            const moneda = (cot.moneda ?? 'mxn').toLowerCase();
            const importe = Number(cot.precio_unitario) * Number(d.cantidad);
            const acc = porMoneda.get(moneda) ?? {
                subtotal: 0,
                baseIva: 0,
                ret: 0,
                soloCot: 0,
            };
            acc.subtotal += importe;
            if (!d.sin_impuestos) {
                acc.baseIva += importe;
            }
            acc.soloCot += importe + (d.sin_impuestos ? 0 : importe * IVA_RATE);
            porMoneda.set(moneda, acc);
        });
        // Un bloque de totales por divisa (divisas primero, MXN al final); el
        // combinado en MXN se arma en el render con el TC capturado.
        const bloques = Array.from(porMoneda.entries())
            .map(([moneda, { subtotal, baseIva, ret, soloCot }]) => {
                const iva = baseIva * IVA_RATE;
                return {
                    moneda,
                    subtotal,
                    iva,
                    ret,
                    soloCot,
                    total: subtotal + iva,
                    neto: subtotal + iva - ret,
                };
            })
            .sort((a, b) =>
                a.moneda === 'mxn'
                    ? 1
                    : b.moneda === 'mxn'
                      ? -1
                      : a.moneda.localeCompare(b.moneda),
            );
        const divisas = bloques.filter((b) => b.moneda !== 'mxn');
        return {
            bloques,
            divisa: divisas.length === 1 ? divisas[0].moneda : null,
            multiDivisa: divisas.length > 1,
        };
    }, [requisicion.detalles, requisicion.mejor_proveedor, proveedores]);

    // Divisa a convertir: la de lo ya seleccionado y, si todavía no hay
    // selección, la que traigan las cotizaciones. Con ella se pide el TC arriba
    // del comparativo, para leerlo ya convertido a pesos.
    const divisasCotizadas = useMemo(() => {
        const monedas = new Set<string>();
        (requisicion.detalles ?? []).forEach((d) =>
            (d.cotizaciones ?? []).forEach((c) => {
                const moneda = (c.moneda ?? 'mxn').toLowerCase();
                if (moneda !== 'mxn') {
                    monedas.add(moneda);
                }
            }),
        );

        return Array.from(monedas);
    }, [requisicion.detalles]);

    const divisaTc =
        resumenNeto?.divisa ??
        (divisasCotizadas.length === 1 ? divisasCotizadas[0] : null);
    const multiDivisaTc =
        (resumenNeto?.multiDivisa ?? false) || divisasCotizadas.length > 1;

    // Tipo de cambio de la requisición: se guarda a nivel documento y con él se
    // convierte a MXN el apartado/afectación cuando las cotizaciones son divisa.
    const [tcRequis, setTcRequis] = useState<string>(String(requisicion.tipo_cambio ?? '1'));
    const [tcGuardando, setTcGuardando] = useState(false);
    const [tcCargando, setTcCargando] = useState(false);

    // Neto total en MXN: los pesos tal cual + la divisa convertida con el TC
    // capturado (solo con una divisa; con varias no alcanza un solo TC).
    const netoMxnCombinado =
        resumenNeto?.divisa != null
            ? resumenNeto.bloques.reduce(
                  (a, b) =>
                      a +
                      (b.moneda === 'mxn'
                          ? b.neto
                          : b.neto * (Number(tcRequis) || 0)),
                  0,
              )
            : null;

    const guardarTc = () => {
        setTcGuardando(true);
        router.post(
            `/admin/costos/requisiciones/${requisicion.id}/tipo-cambio`,
            { tipo_cambio: tcRequis },
            { preserveScroll: true, onFinish: () => setTcGuardando(false) },
        );
    };

    const sugerirTc = async (moneda: string) => {
        setTcCargando(true);
        try {
            const res = await fetch(`/admin/costos/tipo-cambio/${moneda}`, {
                headers: { Accept: 'application/json' },
            });
            if (res.ok) {
                const json = await res.json();
                setTcRequis(String(json.tipo_cambio));
            }
        } catch {
            /* conserva el valor actual */
        } finally {
            setTcCargando(false);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={requisicion.folio} />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            {requisicion.folio}
                        </h1>
                        <div className="mt-1 flex flex-wrap items-center gap-2">
                            <span
                                className={`badge ${REQUISICION_ESTATUS_COLORS[requisicion.estatus]}`}
                            >
                                {
                                    REQUISICION_ESTATUS_LABELS[
                                        requisicion.estatus
                                    ]
                                }
                            </span>
                            {requisicion.sobre_obra_cerrada && (
                                <span
                                    className="badge gap-1 badge-warning"
                                    title="Carga sobre presupuesto cerrado"
                                >
                                    ⚠ Presupuesto cerrado
                                </span>
                            )}
                            {requisicion.sin_centro_costos && (
                                <span
                                    className="badge badge-outline"
                                    title="No afecta ningún centro de costos ni presupuesto"
                                >
                                    Sin obra
                                </span>
                            )}
                            <span className="text-sm text-base-content/60">
                                {requisicion.solicitante?.name} ·{' '}
                                {requisicion.departamento?.descripcion}
                                {requisicion.presupuesto &&
                                    ` · ${requisicion.presupuesto.nombre_mostrar ?? ''}${requisicion.presupuesto.op_mostrar ? ` (${requisicion.presupuesto.op_mostrar})` : ''}`}
                                {' · '}
                                {fmtDate(requisicion.created_at)}
                            </span>
                        </div>
                        {aprobacionPendiente && (
                            <div className="mt-1 text-xs text-base-content/70">
                                Aprobación pendiente:{' '}
                                <span className="font-medium">
                                    {aprobacionPendiente.nivel === 0
                                        ? 'Firma adicional'
                                        : `Nivel ${aprobacionPendiente.nivel}`}
                                </span>{' '}
                                · {aprobacionPendiente.nombres}
                            </div>
                        )}
                    </div>

                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <a
                                href={`/admin/costos/requisiciones/${requisicion.id}/pdf`}
                                target="_blank"
                                rel="noreferrer"
                            >
                                Generar Formato PDF
                            </a>
                        </Button>

                        {can('costos.requisiciones.cotizar') && (
                            <Button
                                variant="outline"
                                onClick={() => {
                                    if (
                                        confirm(
                                            '¿Duplicar esta requisición en una nueva (borrador) con folio nuevo? Se copian partidas y cotizaciones.',
                                        )
                                    ) {
                                        router.post(
                                            `/admin/costos/requisiciones/${requisicion.id}/duplicar`,
                                        );
                                    }
                                }}
                            >
                                Duplicar
                            </Button>
                        )}

                        {editable && can('costos.requisiciones.crear') && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={`/admin/costos/requisiciones/${requisicion.id}/edit`}
                                >
                                    Editar
                                </Link>
                            </Button>
                        )}

                        {/* B1 (auxiliar): borrador → pendiente de aprobación interna. */}
                        {requisicion.estatus === 'borrador' &&
                            can('costos.requisiciones.cotizar') && (
                                <Button
                                    onClick={() => setEnviarAprobacion(true)}
                                >
                                    Enviar a aprobación
                                </Button>
                            )}

                        {/* Etapa interna (gerente): aprobar → aprobada_interna, o
                            rechazar → borrador para re-cotizar. */}
                        {requisicion.estatus === 'pendiente_aprobacion_interno' &&
                            can('costos.requisiciones.control') && (
                                <>
                                    <Button
                                        onClick={() =>
                                            setMarcandoControl(true)
                                        }
                                    >
                                        Aprobación interna
                                    </Button>
                                    <Button
                                        variant="outline"
                                        className="text-error"
                                        onClick={() => {
                                            if (
                                                confirm(
                                                    '¿Rechazar la aprobación interna? La requisición regresa a borrador para re-cotizar.',
                                                )
                                            ) {
                                                router.post(
                                                    `/admin/costos/requisiciones/${requisicion.id}/rechazar-interno`,
                                                    {},
                                                    { preserveScroll: true },
                                                );
                                            }
                                        }}
                                    >
                                        Rechazar
                                    </Button>
                                </>
                            )}

                        {/* Con la aprobación interna dada: mandar a la aprobación
                            formal (cadena), o afectar+OC directo en modo dedazo. */}
                        {requisicion.estatus === 'aprobada_interna' &&
                            (requisicion.modo_dedazo
                                ? can('costos.requisiciones.liberar') && (
                                      <Button
                                          onClick={() => {
                                              if (
                                                  confirm(
                                                      '¿Convertir a orden de compra? Se afectará el presupuesto y se generará la OC directamente, sin cadena de aprobación.',
                                                  )
                                              ) {
                                                  router.post(
                                                      `/admin/costos/requisiciones/${requisicion.id}/convertir-oc`,
                                                      {},
                                                      {
                                                          preserveScroll: true,
                                                      },
                                                  );
                                              }
                                          }}
                                      >
                                          Afectar y generar OC
                                      </Button>
                                  )
                                : can('costos.requisiciones.cotizar') && (
                                      <Button
                                          onClick={() => {
                                              if (
                                                  confirm(
                                                      '¿Mandar a aprobación? Se apartará el presupuesto y se crearán las firmas pendientes de la cadena de aprobación.',
                                                  )
                                              ) {
                                                  router.post(
                                                      `/admin/costos/requisiciones/${requisicion.id}/iniciar-aprobacion`,
                                                      {},
                                                      {
                                                          preserveScroll: true,
                                                      },
                                                  );
                                              }
                                          }}
                                      >
                                          Mandar a aprobación
                                      </Button>
                                  ))}

                        {!['liberada', 'cancelada'].includes(
                            requisicion.estatus,
                        ) &&
                            can('costos.requisiciones.cancelar') && (
                                <Button
                                    variant="outline"
                                    className="text-error"
                                    onClick={() => setCancelando(true)}
                                >
                                    Cancelar
                                </Button>
                            )}
                    </div>
                </div>

                <CancelarModal
                    open={cancelando}
                    onClose={() => setCancelando(false)}
                    url={`/admin/costos/requisiciones/${requisicion.id}/cancelar`}
                    title="Cancelar requisición"
                    description="Esta acción detiene el flujo y no se puede revertir."
                />

                <LiberarRequisicionModal
                    requisicion={requisicion}
                    open={liberando}
                    onClose={() => setLiberando(false)}
                />

                {requisicion.motivo_rechazo && (
                    <div className="mb-4 alert alert-error">
                        <span>
                            <strong>Motivo de rechazo:</strong>{' '}
                            {requisicion.motivo_rechazo}
                        </span>
                    </div>
                )}

                <div role="tablist" className="tabs-bordered mb-4 tabs">
                    <button
                        role="tab"
                        className={`tab ${tab === 'datos' ? 'tab-active' : ''}`}
                        onClick={() => setTab('datos')}
                    >
                        Resumen
                    </button>
                    {can('costos.requisiciones.cotizar') && (
                        <button
                            role="tab"
                            className={`tab ${tab === 'cotizacion' ? 'tab-active' : ''}`}
                            onClick={() => setTab('cotizacion')}
                        >
                            Cotización
                        </button>
                    )}
                    {can('costos.requisiciones.cotizar') && (
                        <button
                            role="tab"
                            className={`tab ${tab === 'definir-oc' ? 'tab-active' : ''}`}
                            onClick={() => setTab('definir-oc')}
                        >
                            Definir OC
                        </button>
                    )}
                    <button
                        role="tab"
                        className={`tab ${tab === 'aprobacion' ? 'tab-active' : ''}`}
                        onClick={() => setTab('aprobacion')}
                    >
                        Aprobación
                    </button>
                    {(requisicion.ordenes_generadas?.length ?? 0) > 0 && (
                        <button
                            role="tab"
                            className={`tab ${tab === 'ocs' ? 'tab-active' : ''}`}
                            onClick={() => setTab('ocs')}
                        >
                            OCs ({requisicion.ordenes_generadas?.length})
                        </button>
                    )}
                </div>

                {tab === 'datos' && (
                    <div className="rounded-lg border border-base-300 p-4">
                        {requisicion.justificacion && (
                            <div className="mb-4">
                                <div className="text-xs text-base-content/60">
                                    Justificación
                                </div>
                                <p className="text-sm">
                                    {requisicion.justificacion}
                                </p>
                            </div>
                        )}

                        <h3 className="mb-2 font-medium">
                            Partidas solicitadas
                        </h3>
                        <div className="overflow-x-auto">
                            <table className="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Descripción</th>
                                        <th>Código producto</th>
                                        <th>Centro de Costos</th>
                                        <th>Uso CFDI</th>
                                        <th className="text-right">
                                            Disponible (MXN)
                                        </th>
                                        <th>Unidad</th>
                                        <th className="text-right">Cantidad</th>
                                        <th>Notas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {requisicion.detalles?.map((d) => {
                                        const presupuestado = Number(
                                            d.obra_rubro?.presupuestado ?? 0,
                                        );
                                        const acumulado = Number(
                                            d.obra_rubro?.acumulado ?? 0,
                                        );
                                        const disponible =
                                            presupuestado - acumulado;
                                        const sobregiro =
                                            !!d.obra_rubro && disponible < 0;
                                        const fmtMoney = (n: number) =>
                                            `$${n.toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;

                                        return (
                                            <tr
                                                key={d.id}
                                                className={
                                                    sobregiro
                                                        ? 'bg-error/5'
                                                        : ''
                                                }
                                            >
                                                <td>{d.descripcion}</td>
                                                <td className="text-xs">
                                                    {d.codigo_producto ?? '-'}
                                                </td>
                                                <td className="text-xs">
                                                    {d.obra_rubro ? (
                                                        <div className="space-y-0.5">
                                                            <div className="font-medium">
                                                                {d.obra_rubro
                                                                    .presupuesto
                                                                    ?.nombre_mostrar ??
                                                                    '-'}
                                                            </div>
                                                            <div className="text-base-content/60">
                                                                {
                                                                    d.obra_rubro
                                                                        .rubro
                                                                        ?.codigo
                                                                }{' '}
                                                                ·{' '}
                                                                {
                                                                    d.obra_rubro
                                                                        .rubro
                                                                        ?.descripcion
                                                                }
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <span className="text-warning">
                                                            Sin centro de costos
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="text-xs">
                                                    {d.uso_cfdi
                                                        ? `${d.uso_cfdi.clave}`
                                                        : '-'}
                                                </td>
                                                <td
                                                    className={`text-right text-xs font-medium ${sobregiro ? 'text-error' : ''}`}
                                                >
                                                    {d.obra_rubro ? (
                                                        <>
                                                            {fmtMoney(
                                                                disponible,
                                                            )}
                                                            {sobregiro && (
                                                                <span className="ml-1">
                                                                    ⚠
                                                                </span>
                                                            )}
                                                        </>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </td>
                                                <td>{d.unidad}</td>
                                                <td className="text-right">
                                                    {Number(
                                                        d.cantidad,
                                                    ).toLocaleString('es-MX')}
                                                </td>
                                                <td className="text-xs text-base-content/60">
                                                    {d.notas ?? '-'}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        {divisaTc != null && (
                            <div className="mt-6 rounded-md border border-warning/40 bg-warning/5 p-3">
                                <div className="mb-1 text-xs font-medium">
                                    Tipo de cambio ({divisaTc.toUpperCase()} →
                                    MXN)
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <input
                                        type="number"
                                        step="0.000001"
                                        min="0"
                                        className="input input-bordered input-sm w-40"
                                        value={tcRequis}
                                        onChange={(e) =>
                                            setTcRequis(e.target.value)
                                        }
                                    />
                                    <button
                                        type="button"
                                        className="btn btn-ghost btn-sm"
                                        disabled={tcCargando}
                                        onClick={() => sugerirTc(divisaTc)}
                                    >
                                        {/* El texto que cambia va envuelto:
                                            React reemplaza el <span>, no un
                                            nodo de texto suelto que un
                                            traductor pudo haber sustituido. */}
                                        <span>
                                            {tcCargando ? '...' : 'Sugerir'}
                                        </span>
                                    </button>
                                    <button
                                        type="button"
                                        className="btn btn-primary btn-sm"
                                        disabled={
                                            tcGuardando ||
                                            !(Number(tcRequis) > 0)
                                        }
                                        onClick={guardarTc}
                                    >
                                        <span>
                                            {tcGuardando
                                                ? 'Guardando...'
                                                : 'Guardar TC'}
                                        </span>
                                    </button>
                                </div>
                                <p className="mt-1 text-[11px] text-base-content/50">
                                    Con este TC se convierten los precios del
                                    comparativo y se aparta y ejerce el
                                    presupuesto.
                                </p>
                            </div>
                        )}
                        {multiDivisaTc && (
                            <div className="mt-6 rounded-md border border-error/40 bg-error/5 p-3 text-xs">
                                La requisición mezcla más de una divisa
                                extranjera; captura el tipo de cambio
                                manualmente.
                            </div>
                        )}

                        <ComparativoCotizaciones
                            requisicion={requisicion}
                            tc={Number(tcRequis) || 0}
                        />

                        {MOSTRAR_TOTAL_OCS && resumenNeto && (
                            <div className="mt-4 rounded-lg border border-base-300 bg-base-200/40 p-4">
                                <h3 className="mb-2 text-xs tracking-wider text-base-content/60 uppercase">
                                    Total de las órdenes de compra
                                </h3>

                                <div className="grid grid-cols-[1fr_auto] gap-x-6 gap-y-1 text-sm md:max-w-sm">
                                    {resumenNeto.bloques.map((b) => (
                                        <Fragment key={b.moneda}>
                                            {resumenNeto.bloques.length > 1 && (
                                                <div className="col-span-2 mt-2 border-b border-base-300 pb-0.5 text-[10px] font-medium tracking-wider text-base-content/50 uppercase first:mt-0">
                                                    {TIPO_MONEDA_LABELS[
                                                        b.moneda as CostosTipoMoneda
                                                    ] ?? b.moneda.toUpperCase()}
                                                </div>
                                            )}
                                            <div className="text-base-content/60">
                                                Subtotal
                                            </div>
                                            <div className="text-right">
                                                {fmtMonto(b.subtotal, b.moneda)}
                                            </div>
                                            <div className="text-base-content/60">
                                                IVA (16%)
                                            </div>
                                            <div className="text-right">
                                                +{fmtMonto(b.iva, b.moneda)}
                                            </div>
                                            {b.ret > 0 && (
                                                <>
                                                    <div className="text-error/80">
                                                        Retenciones
                                                    </div>
                                                    <div className="text-right text-error/80">
                                                        −{fmtMonto(b.ret, b.moneda)}
                                                    </div>
                                                </>
                                            )}
                                            <div
                                                className={
                                                    resumenNeto.bloques.length >
                                                    1
                                                        ? 'font-medium'
                                                        : 'text-base font-bold'
                                                }
                                            >
                                                Total neto
                                            </div>
                                            <div
                                                className={`text-right ${
                                                    resumenNeto.bloques.length >
                                                    1
                                                        ? 'font-medium'
                                                        : 'text-base font-bold text-primary'
                                                }`}
                                            >
                                                {fmtMonto(b.neto, b.moneda)}
                                            </div>
                                            {b.soloCot > 0 && (
                                                <div className="col-span-2 text-[10px] leading-tight text-base-content/50">
                                                    Incluye{' '}
                                                    {fmtMonto(b.soloCot, b.moneda)}{' '}
                                                    de partidas solo cotización
                                                    (referencia, no se surten en
                                                    OC).
                                                </div>
                                            )}
                                        </Fragment>
                                    ))}
                                    {netoMxnCombinado != null && (
                                        <>
                                            <div className="col-span-2 mt-1 border-t border-base-300"></div>
                                            <div className="text-base font-bold">
                                                Total neto a pagar (MXN)
                                                <span className="ml-1 text-xs font-normal text-base-content/50">
                                                    TC {Number(tcRequis) || 0}
                                                </span>
                                            </div>
                                            <div className="text-right text-base font-bold text-primary">
                                                {fmtMonto(netoMxnCombinado, 'mxn')}
                                            </div>
                                        </>
                                    )}
                                </div>
                            </div>
                        )}

                        <div className="mt-4">
                            <DocumentosCotizacion
                                requisicion={requisicion}
                                editable={
                                    cotizable &&
                                    can('costos.requisiciones.cotizar')
                                }
                            />
                        </div>
                    </div>
                )}

                {tab === 'cotizacion' &&
                    can('costos.requisiciones.cotizar') && (
                        <div className="space-y-3">
                            {cotizable && (
                                <label className="flex w-fit items-center gap-2 rounded-lg border border-base-300 px-3 py-2 text-sm">
                                    <input
                                        type="checkbox"
                                        className="checkbox checkbox-sm"
                                        checked={
                                            requisicion.modo_dedazo ?? false
                                        }
                                        onChange={(e) =>
                                            router.post(
                                                `/admin/costos/requisiciones/${requisicion.id}/dedazo`,
                                                {
                                                    modo_dedazo:
                                                        e.target.checked,
                                                },
                                                { preserveScroll: true },
                                            )
                                        }
                                    />
                                    <span className="font-medium">
                                        Modo materia prima
                                    </span>
                                    <span className="text-base-content/60">
                                        un solo proveedor · se convierte directo
                                        a OC (con aprobación gerencial, sin
                                        cadena de aprobación)
                                    </span>
                                </label>
                            )}
                            <CotizacionMatriz
                                requisicion={requisicion}
                                proveedores={proveedores}
                                obraRubros={obraRubros}
                                usosCfdi={usosCfdi}
                                preciosPrevios={preciosPrevios}
                                editable={cotizable}
                                puedeEditarPartidas={[
                                    'borrador',
                                    'rechazada',
                                ].includes(requisicion.estatus)}
                            />
                        </div>
                    )}

                {tab === 'definir-oc' &&
                    can('costos.requisiciones.cotizar') && (
                        <>
                            <OcBuilder
                                requisicion={requisicion}
                                proveedores={proveedores}
                                editable={cotizable}
                            />
                            {requisicion.estatus === 'aprobada' &&
                                can('costos.requisiciones.liberar') && (
                                    <div className="mt-4 flex justify-end">
                                        <Button
                                            onClick={() => setLiberando(true)}
                                        >
                                            Liberar y generar OCs
                                        </Button>
                                    </div>
                                )}
                        </>
                    )}

                {tab === 'aprobacion' && (
                    <div className="rounded-lg border border-base-300 p-4">
                        <div className="mb-3 flex items-center justify-between">
                            <h3 className="font-medium">Cadena de firmas</h3>
                            {aprobacionPendienteId && (
                                <div className="flex gap-2">
                                    <Button
                                        className="bg-green-600 hover:bg-green-700"
                                        onClick={() =>
                                            requiereValidacion
                                                ? setValidando(true)
                                                : setFirmando('aprobar')
                                        }
                                    >
                                        {requiereValidacion
                                            ? 'Firmar (validar proveedores)'
                                            : 'Firmar'}
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        onClick={() => setFirmando('rechazar')}
                                    >
                                        Rechazar
                                    </Button>
                                </div>
                            )}
                        </div>
                        {!requisicion.aprobaciones ||
                        requisicion.aprobaciones.length === 0 ? (
                            <p className="text-sm text-base-content/60">
                                Aún no se ha enviado a aprobación.
                            </p>
                        ) : (
                            <div className="space-y-2">
                                {/* Un nivel puede tener varios aprobadores (la primera firma cierra el
                                    nivel); se agrupa por nivel para no repetir filas. */}
                                {[
                                    ...new Set(
                                        requisicion.aprobaciones.map(
                                            (a) => a.nivel,
                                        ),
                                    ),
                                ]
                                    .sort((a, b) => a - b)
                                    .map((nivel) => {
                                        const delNivel =
                                            requisicion.aprobaciones!.filter(
                                                (a) => a.nivel === nivel,
                                            );
                                        const resuelta =
                                            delNivel.find(
                                                (a) => a.estatus === 'aprobada',
                                            ) ??
                                            delNivel.find(
                                                (a) =>
                                                    a.estatus === 'rechazada',
                                            );
                                        const estatus =
                                            resuelta?.estatus ??
                                            (delNivel.every(
                                                (a) =>
                                                    a.estatus === 'cancelada',
                                            )
                                                ? 'cancelada'
                                                : 'pendiente');
                                        const candidatos = [
                                            ...new Set(
                                                delNivel
                                                    .map(
                                                        (a) =>
                                                            a.aprobador?.name,
                                                    )
                                                    .filter(Boolean),
                                            ),
                                        ].join(' / ');
                                        const quien =
                                            resuelta?.aprobador?.name ??
                                            (candidatos || 'Sin asignar');

                                        return (
                                            <div
                                                key={nivel}
                                                className="flex items-center justify-between rounded border border-base-200 p-3"
                                            >
                                                <div>
                                                    <div className="text-sm font-medium">
                                                        {nivel === 0
                                                            ? 'Firma adicional'
                                                            : `Nivel ${nivel}`}
                                                        : {quien}
                                                    </div>
                                                    {resuelta?.observaciones && (
                                                        <div className="mt-1 text-xs text-base-content/60">
                                                            {
                                                                resuelta.observaciones
                                                            }
                                                        </div>
                                                    )}
                                                </div>
                                                <span
                                                    className={`badge badge-sm ${
                                                        estatus === 'aprobada'
                                                            ? 'badge-success'
                                                            : estatus ===
                                                                'rechazada'
                                                              ? 'badge-error'
                                                              : estatus ===
                                                                  'cancelada'
                                                                ? 'badge-neutral'
                                                                : 'badge-warning'
                                                    }`}
                                                >
                                                    {estatus}
                                                </span>
                                            </div>
                                        );
                                    })}
                            </div>
                        )}

                        {requisicion.activities &&
                            requisicion.activities.length > 0 && (
                                <div className="mt-6">
                                    <h3 className="mb-3 font-medium">
                                        Historial
                                    </h3>
                                    <ActivityTimeline
                                        activities={requisicion.activities}
                                    />
                                </div>
                            )}
                    </div>
                )}

                {firmando && aprobacionPendienteId && (
                    <FirmarRequisicionModal
                        aprobacionId={aprobacionPendienteId}
                        tipo={firmando}
                        onClose={() => setFirmando(null)}
                    />
                )}

                {validando && (
                    <ValidacionProveedoresModal
                        requisicionId={requisicion.id}
                        proveedores={proveedoresPorValidar}
                        onClose={() => setValidando(false)}
                    />
                )}

                {enviarAprobacion && (
                    <EnviarAprobacionModal
                        requisicionId={requisicion.id}
                        tieneOc={tieneOcDefinida}
                        cotizacionCompleta={cotizacionCompleta}
                        empresasCotizando={empresasCotizando}
                        minEmpresas={MIN_EMPRESAS_COTIZACION}
                        onClose={() => setEnviarAprobacion(false)}
                    />
                )}

                {marcandoControl && (
                    <PuntoControlModal
                        requisicionId={requisicion.id}
                        onClose={() => setMarcandoControl(false)}
                    />
                )}

                {tab === 'ocs' && (
                    <div className="rounded-lg border border-base-300 p-4">
                        <h3 className="mb-3 font-medium">
                            Órdenes de compra generadas
                        </h3>
                        <div className="space-y-3">
                            {requisicion.ordenes_generadas?.map((oc) => (
                                <div
                                    key={oc.id}
                                    className="rounded border border-base-200"
                                >
                                    <Link
                                        href={`/admin/costos/ordenes-compra/${oc.id}`}
                                        className="flex items-center justify-between p-3 hover:bg-base-100"
                                    >
                                        <div>
                                            <div className="font-mono text-sm">
                                                {oc.folio}
                                            </div>
                                            <div className="text-xs text-base-content/60">
                                                {oc.proveedor?.razon_social ??
                                                    '-'}
                                            </div>
                                        </div>
                                        <div className="text-right">
                                            <div className="font-medium">
                                                $
                                                {Number(
                                                    oc.total,
                                                ).toLocaleString('es-MX', {
                                                    minimumFractionDigits: 2,
                                                })}
                                            </div>
                                            <div className="text-xs text-base-content/60">
                                                {oc.estatus}
                                            </div>
                                        </div>
                                    </Link>

                                    {oc.solicitudes_pago &&
                                        oc.solicitudes_pago.length > 0 && (
                                            <div className="border-t border-base-200 bg-base-100/50 px-3 py-2">
                                                <div className="mb-1 text-[11px] font-medium tracking-wider text-base-content/50 uppercase">
                                                    Solicitudes de pago
                                                </div>
                                                <div className="space-y-1">
                                                    {oc.solicitudes_pago.map(
                                                        (sp) => (
                                                            <Link
                                                                key={sp.id}
                                                                href={`/admin/costos/solicitudes-pago/${sp.id}`}
                                                                className="flex items-center justify-between rounded px-2 py-1 text-sm hover:bg-base-200"
                                                            >
                                                                <span className="font-mono text-xs">
                                                                    {sp.folio}
                                                                </span>
                                                                <span className="flex items-center gap-3">
                                                                    <span className="font-medium">
                                                                        $
                                                                        {Number(
                                                                            sp.monto_total,
                                                                        ).toLocaleString(
                                                                            'es-MX',
                                                                            {
                                                                                minimumFractionDigits: 2,
                                                                            },
                                                                        )}
                                                                    </span>
                                                                    <span className="text-xs text-base-content/60">
                                                                        {
                                                                            sp.estatus
                                                                        }
                                                                    </span>
                                                                </span>
                                                            </Link>
                                                        ),
                                                    )}
                                                </div>
                                            </div>
                                        )}

                                    {oc.entregas && oc.entregas.length > 0 && (
                                        <div className="border-t border-base-200 bg-base-100/50 px-3 py-2">
                                            <div className="mb-1 text-[11px] font-medium tracking-wider text-base-content/50 uppercase">
                                                Recepciones
                                            </div>
                                            <div className="space-y-1">
                                                {oc.entregas.map((entrega) => (
                                                    <a
                                                        key={entrega.id}
                                                        href={`/admin/costos/entregas/${entrega.id}/pdf`}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="flex items-center justify-between rounded px-2 py-1 text-sm hover:bg-base-200"
                                                    >
                                                        <span className="font-mono text-xs">
                                                            {entrega.folio ?? `Entrega #${entrega.id}`}
                                                        </span>
                                                        <span className="text-xs text-base-content/60">Recepcion (PDF)</span>
                                                    </a>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

/**
 * Cuadro comparativo simplificado por partida:
 * Cantidad | Descripción | Precio × proveedor (N columnas) | Importe
 *
 * "Importe" usa el/los proveedor(es) seleccionado(s) de la partida una vez
 * definidos (∑ cantidad seleccionada × precio cotizado). Mientras no haya
 * selección, cae al comparativo best-case: precio del mejor proveedor global
 * si cotizó la partida, o el menor precio cotizado. El pie calcula subtotal,
 * IVA 16% y total por divisa, y el combinado en MXN con el TC del documento.
 */
function ComparativoCotizaciones({
    requisicion,
    tc,
}: {
    requisicion: CostosRequisicion;
    tc: number;
}) {
    // Las partidas "solo cotización" (ej. fletes variables) aparecen y suman al
    // total del comparativo con su precio de referencia; no se surten al definir
    // la OC, pero sí cuentan en el neto a pagar.
    const detalles = requisicion.detalles ?? [];

    // Columnas = opciones con al menos un precio, agrupadas por proveedor.
    const opcionConPrecio = new Set<number>();
    for (const d of detalles) {
        for (const c of d.cotizaciones ?? []) {
            if (c.opcion_id != null) opcionConPrecio.add(c.opcion_id);
        }
    }
    const opciones = (requisicion.cotizacion_opciones ?? []).filter((o) =>
        opcionConPrecio.has(o.id),
    );
    if (opciones.length === 0) return null;

    const etiquetaOpcion = (o: (typeof opciones)[number]) =>
        o.etiqueta || `Opción ${o.orden}`;

    const gruposMap = new Map<
        number,
        { proveedorId: number; nombre: string; opciones: typeof opciones }
    >();
    for (const o of opciones) {
        const nombre =
            o.proveedor?.nombre_comercial ||
            o.proveedor?.razon_social ||
            `#${o.proveedor_id}`;
        const g = gruposMap.get(o.proveedor_id) ?? {
            proveedorId: o.proveedor_id,
            nombre,
            opciones: [],
        };
        g.opciones.push(o);
        gruposMap.set(o.proveedor_id, g);
    }
    const grupos = Array.from(gruposMap.values())
        .map((g) => ({
            ...g,
            opciones: [...g.opciones].sort((a, b) => a.orden - b.orden),
        }))
        .sort((a, b) => a.nombre.localeCompare(b.nombre));
    const columnas = grupos.flatMap((g) => g.opciones);

    const mejorProveedorId = requisicion.mejor_proveedor?.id ?? null;
    const fmt = fmtMonto;

    /** Etiqueta de la divisa en la que está expresada la cifra principal. */
    const EtiquetaMxn = () => (
        <span className="text-[10px] font-normal text-base-content/50">
            {TIPO_MONEDA_LABELS.mxn}
        </span>
    );

    /**
     * Dinero del comparativo con el peso al frente: MXN es la cifra que se
     * compara y se autoriza, y la divisa original queda como referencia entre
     * paréntesis. Sin tipo de cambio no hay conversión posible, así que el
     * monto se muestra tal como se cotizó.
     */
    const MontoComparado = ({
        valor,
        moneda,
        className,
    }: {
        valor: number;
        moneda: string;
        className?: string;
    }) => {
        const cod = (moneda || 'mxn').toLowerCase();

        if (cod === 'mxn') {
            return (
                <span className={className}>
                    {fmt(valor, 'mxn')} <EtiquetaMxn />
                </span>
            );
        }

        // `fmt` ya rotula la divisa (`$1,000.00 USD`).
        if (tc <= 0) {
            return <span className={className}>{fmt(valor, cod)}</span>;
        }

        return (
            <span className={className}>
                {fmt(valor * tc, 'mxn')} <EtiquetaMxn />{' '}
                <span className="text-[10px] font-normal text-base-content/50">
                    ({fmt(valor, cod)})
                </span>
            </span>
        );
    };

    /**
     * Importe de la partida en pesos. Una partida puede traer selecciones en
     * varias monedas; con tipo de cambio se suman todas a MXN y el desglose en
     * divisa va entre paréntesis. Sin TC no se pueden sumar y se listan aparte.
     */
    const ImporteComparado = ({
        contribs,
    }: {
        contribs: { moneda: string; importe: number }[];
    }) => {
        const divisas = contribs.filter((c) => c.moneda !== 'mxn');

        if (divisas.length === 0) {
            const enMxn = contribs.reduce((acc, c) => acc + c.importe, 0);

            return (
                <>
                    {fmt(enMxn, 'mxn')} <EtiquetaMxn />
                </>
            );
        }

        if (tc <= 0) {
            return (
                <>
                    {contribs
                        .map((c) => fmt(c.importe, c.moneda))
                        .join(' + ')}
                </>
            );
        }

        const enMxn = contribs.reduce(
            (acc, c) => acc + (c.moneda === 'mxn' ? c.importe : c.importe * tc),
            0,
        );

        return (
            <>
                {fmt(enMxn, 'mxn')} <EtiquetaMxn />{' '}
                <span className="text-[10px] font-normal text-base-content/50">
                    ({divisas.map((c) => fmt(c.importe, c.moneda)).join(' + ')})
                </span>
            </>
        );
    };

    const cotizacionDe = (detalleId: number, opcionId: number) =>
        detalles
            .find((x) => x.id === detalleId)
            ?.cotizaciones?.find((c) => c.opcion_id === opcionId);

    const monedaDe = (c: { moneda?: string | null } | undefined) =>
        (c?.moneda ?? 'mxn').toLowerCase();

    const menorCotizacion = (
        cots: NonNullable<CostosRequisicionDetalle['cotizaciones']>,
    ) => {
        const conPrecio = cots.filter((c) => Number(c.precio_unitario) > 0);
        return conPrecio.length > 0
            ? conPrecio.reduce((a, b) =>
                  Number(a.precio_unitario) <= Number(b.precio_unitario)
                      ? a
                      : b,
              )
            : null;
    };

    // Precio elegido para el importe best-case, con su moneda: el del mejor
    // proveedor global si cotizó la partida; si no, el menor cotizado.
    const precioImporte = (
        d: CostosRequisicionDetalle,
    ): { precio: number; moneda: string } | null => {
        const cots = d.cotizaciones ?? [];
        const delMejor = mejorProveedorId
            ? menorCotizacion(
                  cots.filter((c) => c.proveedor_id === mejorProveedorId),
              )
            : null;
        const cot = delMejor ?? menorCotizacion(cots);
        return cot
            ? { precio: Number(cot.precio_unitario), moneda: monedaDe(cot) }
            : null;
    };

    // Importe de la partida desglosado por divisa: con proveedor(es)
    // seleccionado(s), suma (cantidad × precio) de cada selección en su moneda;
    // si aún no hay selección, usa el best-case sobre la cantidad solicitada.
    const importeDetalle = (
        d: CostosRequisicionDetalle,
    ): {
        contribs: { moneda: string; importe: number }[];
        tieneImporte: boolean;
    } => {
        // La partida solo cotización nunca se adjudica: siempre best-case.
        const selecciones = d.solo_cotizacion ? [] : (d.selecciones ?? []);
        if (selecciones.length > 0) {
            const porMoneda = new Map<string, number>();
            for (const s of selecciones) {
                const px = Number(s.cotizacion_precio?.precio_unitario ?? 0);
                if (px > 0) {
                    const m = monedaDe(s.cotizacion_precio);
                    porMoneda.set(
                        m,
                        (porMoneda.get(m) ?? 0) + px * Number(s.cantidad),
                    );
                }
            }
            return {
                contribs: Array.from(porMoneda, ([moneda, importe]) => ({
                    moneda,
                    importe,
                })),
                tieneImporte: porMoneda.size > 0,
            };
        }

        const elegido = precioImporte(d);
        return elegido
            ? {
                  contribs: [
                      {
                          moneda: elegido.moneda,
                          importe: elegido.precio * Number(d.cantidad),
                      },
                  ],
                  tieneImporte: true,
              }
            : { contribs: [], tieneImporte: false };
    };

    // Mejor (menor) precio por partida — para resaltar la celda ganadora.
    const mejorPrecioPartida = new Map<number, number>();
    for (const d of detalles) {
        const precios = (d.cotizaciones ?? [])
            .map((c) => Number(c.precio_unitario))
            .filter((n) => n > 0);
        if (precios.length > 0) {
            mejorPrecioPartida.set(d.id, Math.min(...precios));
        }
    }

    const filas = detalles.map((d) => ({
        d,
        esSoloCotizacion: !!d.solo_cotizacion,
        esSinImpuestos: !!d.sin_impuestos,
        ...importeDetalle(d),
    }));

    // Totales del pie por divisa (divisas primero, MXN al final) + combinado
    // en MXN con el TC del documento cuando hay exactamente una divisa.
    // Las partidas "sin impuestos" suman al subtotal pero no a la base del IVA.
    const porMoneda = new Map<string, { subtotal: number; baseIva: number }>();
    filas.forEach((f) =>
        f.contribs.forEach((c) => {
            const acc = porMoneda.get(c.moneda) ?? { subtotal: 0, baseIva: 0 };
            acc.subtotal += c.importe;
            if (!f.esSinImpuestos) {
                acc.baseIva += c.importe;
            }
            porMoneda.set(c.moneda, acc);
        }),
    );
    const bloquesTotales = Array.from(
        porMoneda,
        ([moneda, { subtotal, baseIva }]) => ({
            moneda,
            subtotal,
            iva: baseIva * IVA_RATE,
            total: subtotal + baseIva * IVA_RATE,
        }),
    ).sort((a, b) =>
        a.moneda === 'mxn'
            ? 1
            : b.moneda === 'mxn'
              ? -1
              : a.moneda.localeCompare(b.moneda),
    );
    const divisasComp = bloquesTotales.filter((b) => b.moneda !== 'mxn');
    const totalMxnCombinado =
        divisasComp.length === 1 && tc > 0
            ? bloquesTotales.reduce(
                  (a, b) => a + (b.moneda === 'mxn' ? b.total : b.total * tc),
                  0,
              )
            : null;
    const multiMoneda = bloquesTotales.length > 1;
    const etiquetaMoneda = (m: string) =>
        TIPO_MONEDA_LABELS[m as CostosTipoMoneda] ?? m.toUpperCase();

    return (
        <div className="mt-6">
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h3 className="font-medium">Comparativo de cotizaciones</h3>
                <div className="flex items-center gap-3 text-[10px] text-base-content/60">
                    <span className="inline-flex items-center gap-1">
                        <span className="inline-block size-2 rounded-full bg-primary"></span>{' '}
                        Seleccionado
                    </span>
                    <span className="inline-flex items-center gap-1">
                        <span className="inline-block size-2 rounded-full bg-success"></span>{' '}
                        Mejor precio
                    </span>
                </div>
            </div>
            <div className="overflow-x-auto rounded-lg border border-base-300">
                <table className="table table-sm">
                    <thead className="bg-base-200">
                        <tr>
                            <th rowSpan={2} className="text-right">
                                Cantidad
                            </th>
                            <th rowSpan={2}>Descripción</th>
                            {grupos.map((g) => (
                                <th
                                    key={g.proveedorId}
                                    colSpan={g.opciones.length}
                                    className={`border-l border-base-300 text-center ${g.proveedorId === mejorProveedorId ? 'text-success' : ''}`}
                                    title={g.nombre}
                                >
                                    {g.nombre}
                                    {g.proveedorId === mejorProveedorId && (
                                        <span className="ml-1 text-[10px]">
                                            ★
                                        </span>
                                    )}
                                </th>
                            ))}
                            <th rowSpan={2} className="text-right">
                                Importe
                            </th>
                        </tr>
                        <tr>
                            {columnas.map((op) => (
                                <th
                                    key={op.id}
                                    className="border-l border-base-300 text-right text-[11px] font-medium"
                                >
                                    {etiquetaOpcion(op)}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {filas.map(({
                            d,
                            tieneImporte,
                            contribs,
                            esSoloCotizacion,
                            esSinImpuestos,
                        }) => (
                            <tr key={d.id}>
                                <td className="text-right">
                                    {Number(d.cantidad).toLocaleString('es-MX', { maximumFractionDigits: 4 })}{' '}
                                    {d.unidad}
                                    <CanceladoEnOc detalle={d} />
                                </td>
                                <td>
                                    {d.descripcion}
                                    {esSoloCotizacion && (
                                        <span
                                            className="badge badge-ghost badge-xs ml-1 align-middle"
                                            title="Suma al total y al neto con su precio de referencia, pero no se surte en la OC (no se adjudica a ningún proveedor)"
                                        >
                                            solo cotización
                                        </span>
                                    )}
                                    {esSinImpuestos && (
                                        <span
                                            className="badge badge-ghost badge-xs ml-1 align-middle"
                                            title="Suma al subtotal pero no causa IVA ni entra a la base de retenciones"
                                        >
                                            sin impuestos
                                        </span>
                                    )}
                                </td>
                                {columnas.map((op) => {
                                    const cot = cotizacionDe(d.id, op.id);
                                    const px = cot
                                        ? Number(cot.precio_unitario)
                                        : null;
                                    const dias =
                                        cot?.tiempo_entrega_dias ?? null;
                                    const moneda = cot?.moneda ?? 'mxn';
                                    const esMejorPartida =
                                        px !== null &&
                                        px === mejorPrecioPartida.get(d.id);
                                    const seleccionado =
                                        cot != null &&
                                        (d.selecciones ?? []).some(
                                            (s) =>
                                                s.cotizacion_precio_id ===
                                                cot.id,
                                        );
                                    const classes = [
                                        'border-l border-base-300 text-right align-top',
                                        seleccionado
                                            ? 'bg-primary/15 font-semibold text-primary ring-1 ring-inset ring-primary/50'
                                            : esMejorPartida
                                              ? 'bg-success/15 font-semibold text-success'
                                              : '',
                                        op.proveedor_id === mejorProveedorId &&
                                        !esMejorPartida &&
                                        !seleccionado
                                            ? 'text-success'
                                            : '',
                                    ]
                                        .filter(Boolean)
                                        .join(' ');
                                    return (
                                        <td key={op.id} className={classes}>
                                            {px !== null ? (
                                                <>
                                                    <div className="text-sm">
                                                        {seleccionado && (
                                                            <span className="mr-1">
                                                                ✓
                                                            </span>
                                                        )}
                                                        <MontoComparado
                                                            valor={px}
                                                            moneda={moneda}
                                                        />
                                                    </div>
                                                    {cot?.descripcion && (
                                                        <div className="text-[10px] font-normal text-base-content/60">
                                                            {cot.descripcion}
                                                        </div>
                                                    )}
                                                    {dias !== null &&
                                                        dias > 0 && (
                                                            <div className="text-[10px] font-normal text-base-content/60">
                                                                {dias}{' '}
                                                                {dias === 1
                                                                    ? 'día'
                                                                    : 'días'}{' '}
                                                                entrega
                                                            </div>
                                                        )}
                                                </>
                                            ) : (
                                                <span className="text-base-content/30">
                                                    —
                                                </span>
                                            )}
                                        </td>
                                    );
                                })}
                                <td className="text-right text-sm font-semibold">
                                    {tieneImporte ? (
                                        <ImporteComparado contribs={contribs} />
                                    ) : (
                                        <span className="text-base-content/30">
                                            —
                                        </span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        {bloquesTotales.map((b) => (
                            <Fragment key={b.moneda}>
                                <tr>
                                    <td
                                        colSpan={2 + columnas.length}
                                        className="text-right text-sm text-base-content/60"
                                    >
                                        Subtotal
                                        {multiMoneda &&
                                            ` (${etiquetaMoneda(b.moneda)})`}
                                    </td>
                                    <td className="text-right font-semibold">
                                        {fmt(b.subtotal, b.moneda)}
                                    </td>
                                </tr>
                                <tr>
                                    <td
                                        colSpan={2 + columnas.length}
                                        className="text-right text-sm text-base-content/60"
                                    >
                                        IVA (16%)
                                    </td>
                                    <td className="text-right">
                                        {fmt(b.iva, b.moneda)}
                                    </td>
                                </tr>
                                <tr
                                    className={
                                        multiMoneda ? '' : 'bg-base-200'
                                    }
                                >
                                    <td
                                        colSpan={2 + columnas.length}
                                        className="text-right text-sm font-semibold"
                                    >
                                        Total
                                        {multiMoneda &&
                                            ` (${etiquetaMoneda(b.moneda)})`}
                                    </td>
                                    <td
                                        className={`text-right font-bold ${multiMoneda ? '' : 'text-lg text-primary'}`}
                                    >
                                        {fmt(b.total, b.moneda)}
                                    </td>
                                </tr>
                            </Fragment>
                        ))}
                        {totalMxnCombinado != null &&
                            (multiMoneda || tc !== 1) && (
                                <tr className="bg-base-200">
                                    <td
                                        colSpan={2 + columnas.length}
                                        className="text-right text-sm font-semibold"
                                    >
                                        Total en MXN
                                        <span className="ml-1 text-xs font-normal text-base-content/50">
                                            TC {tc}
                                        </span>
                                    </td>
                                    <td className="text-right text-lg font-bold text-primary">
                                        {fmt(totalMxnCombinado, 'mxn')}
                                    </td>
                                </tr>
                            )}
                    </tfoot>
                </table>
            </div>
        </div>
    );
}
