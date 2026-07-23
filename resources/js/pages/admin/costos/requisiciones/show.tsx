import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangleIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ActivityTimeline } from '@/components/costos/activity-timeline';
import { CancelarModal } from '@/components/costos/cancelar-modal';
import { CotizacionMatriz } from '@/components/costos/cotizacion-matriz';
import { formatMoney as fmtMonto, monedaAgregada } from '@/components/costos/monto';
import { LiberarRequisicionModal } from '@/components/costos/liberar-requisicion-modal';
import { OcBuilder } from '@/components/costos/oc-builder';
import { calcularRetenciones, IVA_RATE } from '@/components/costos/retenciones';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { SharedData } from '@/types';
import type {
    CostosRequisicion,
    CostosTipoFiscalPartida,
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

    const fmt = (n: number) =>
        `$${n.toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;

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
                                                                    {fmt(
                                                                        a.precio_unitario,
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
    const [pdfBloqueado, setPdfBloqueado] = useState(false);

    const requiereValidacion =
        esUltimoNivel && proveedoresPorValidar.length > 0;

    const editable = ['borrador', 'rechazada'].includes(requisicion.estatus);
    const cotizable = ['borrador', 'rechazada', 'aprobada'].includes(
        requisicion.estatus,
    );
    const tieneOcDefinida = (requisicion.ocs?.length ?? 0) > 0;
    // El comparativo (PDF) queda disponible una vez que la requisición entra a
    // la bandeja del gerente, para que él lo revise antes de aprobar.
    const comparativoDisponible = [
        'pendiente_aprobacion_interno',
        'aprobada',
        'liberada',
    ].includes(requisicion.estatus);
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
    // suma el neto de todas.
    const resumenNeto = useMemo(() => {
        const provMap = new Map(proveedores.map((p) => [p.id, p]));
        const grupos = new Map<
            string,
            {
                proveedorId: number;
                lines: {
                    tipo_fiscal: CostosTipoFiscalPartida;
                    subtotal: number;
                }[];
            }
        >();
        const monedas: Array<string | null | undefined> = [];
        (requisicion.detalles ?? []).forEach((d) => {
            (d.selecciones ?? []).forEach((s) => {
                const key = `${s.proveedor_id}|${s.numero_oc ?? 1}`;
                const sub =
                    Number(s.cotizacion_precio?.precio_unitario ?? 0) *
                    Number(s.cantidad);
                monedas.push(s.cotizacion_precio?.moneda);
                const g = grupos.get(key) ?? {
                    proveedorId: s.proveedor_id,
                    lines: [],
                };
                g.lines.push({ tipo_fiscal: d.tipo_fiscal, subtotal: sub });
                grupos.set(key, g);
            });
        });
        if (grupos.size === 0) return null;
        let subtotal = 0;
        let ret = 0;
        grupos.forEach((g) => {
            subtotal += g.lines.reduce((a, l) => a + l.subtotal, 0);
            ret += calcularRetenciones(
                provMap.get(g.proveedorId),
                g.lines,
            ).reduce((a, r) => a + r.monto, 0);
        });
        const iva = subtotal * IVA_RATE;
        return {
            subtotal,
            iva,
            ret,
            total: subtotal + iva,
            neto: subtotal + iva - ret,
            moneda: monedaAgregada(monedas),
        };
    }, [requisicion.detalles, proveedores]);

    const fmtMoney = (n: number) =>
        `$${n.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

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
                        {comparativoDisponible ? (
                            <Button variant="outline" asChild>
                                <a
                                    href={`/admin/costos/requisiciones/${requisicion.id}/pdf`}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    Generar Formato PDF
                                </a>
                            </Button>
                        ) : (
                            <Button
                                variant="outline"
                                onClick={() => setPdfBloqueado(true)}
                            >
                                Generar Formato PDF
                            </Button>
                        )}

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
                                            Disponible
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

                        <ComparativoCotizaciones requisicion={requisicion} />

                        {resumenNeto && (
                            <div className="mt-4 rounded-lg border border-base-300 bg-base-200/40 p-4">
                                <h3 className="mb-2 text-xs tracking-wider text-base-content/60 uppercase">
                                    Total de las órdenes de compra
                                </h3>
                                <div className="grid grid-cols-[1fr_auto] gap-x-6 gap-y-1 text-sm md:max-w-sm">
                                    <div className="text-base-content/60">
                                        Subtotal
                                    </div>
                                    <div className="text-right">
                                        {fmtMonto(resumenNeto.subtotal, resumenNeto.moneda)}
                                    </div>
                                    <div className="text-base-content/60">
                                        IVA (16%)
                                    </div>
                                    <div className="text-right">
                                        +{fmtMonto(resumenNeto.iva, resumenNeto.moneda)}
                                    </div>
                                    <div className="font-medium">Total</div>
                                    <div className="text-right font-medium">
                                        {fmtMonto(resumenNeto.total, resumenNeto.moneda)}
                                    </div>
                                    {resumenNeto.ret > 0 && (
                                        <>
                                            <div className="text-error/80">
                                                Retenciones
                                            </div>
                                            <div className="text-right text-error/80">
                                                −{fmtMonto(resumenNeto.ret, resumenNeto.moneda)}
                                            </div>
                                        </>
                                    )}
                                    <div className="text-base font-bold">
                                        Total neto a pagar
                                    </div>
                                    <div className="text-right text-base font-bold text-primary">
                                        {fmtMonto(resumenNeto.neto, resumenNeto.moneda)}
                                    </div>
                                </div>
                            </div>
                        )}
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

                {pdfBloqueado && (
                    <dialog className="modal-open modal">
                        <div className="modal-box">
                            <h2 className="text-xl font-bold">
                                Comparativo no disponible aún
                            </h2>
                            <p className="mt-3 text-sm text-base-content/70">
                                El comparativo solo puede generarse una vez que
                                la requisición se{' '}
                                <strong>envía a aprobación</strong>. Envíala
                                desde el botón de la cabecera y vuelve a
                                intentarlo.
                            </p>
                            <div className="modal-action">
                                <button
                                    type="button"
                                    className="btn btn-primary"
                                    onClick={() => setPdfBloqueado(false)}
                                >
                                    Entendido
                                </button>
                            </div>
                        </div>
                        <div
                            className="modal-backdrop"
                            onClick={() => setPdfBloqueado(false)}
                        />
                    </dialog>
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
 * si cotizó la partida, o el menor precio cotizado. Al final calcula subtotal,
 * IVA 16% y total.
 */
function ComparativoCotizaciones({
    requisicion,
}: {
    requisicion: CostosRequisicion;
}) {
    // Las partidas "solo cotización" (ej. fletes variables) son referencia
    // interna: no forman parte del comparativo formal ni del PDF.
    const detalles = (requisicion.detalles ?? []).filter((d) => !d.solo_cotizacion);

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
    const fmt = (n: number) =>
        `$${n.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

    const cotizacionDe = (detalleId: number, opcionId: number) =>
        detalles
            .find((x) => x.id === detalleId)
            ?.cotizaciones?.find((c) => c.opcion_id === opcionId);

    const precioPartidaProv = (
        detalleId: number,
        proveedorId: number,
    ): number | null => {
        const d = detalles.find((x) => x.id === detalleId);
        const precios = (d?.cotizaciones ?? [])
            .filter((c) => c.proveedor_id === proveedorId)
            .map((c) => Number(c.precio_unitario))
            .filter((n) => n > 0);
        return precios.length > 0 ? Math.min(...precios) : null;
    };

    const precioImporte = (
        d: CostosRequisicion['detalles'] extends (infer U)[] | undefined
            ? U
            : never,
    ): number | null => {
        // Si hay mejor proveedor global y cotizó esta partida → usar ese precio
        if (mejorProveedorId) {
            const p = precioPartidaProv(d.id, mejorProveedorId);
            if (p !== null) return p;
        }
        // Fallback: menor precio cotizado para esta partida
        const precios = (d.cotizaciones ?? [])
            .map((c) => Number(c.precio_unitario))
            .filter((n) => n > 0);
        return precios.length > 0 ? Math.min(...precios) : null;
    };

    // Importe de la partida: con proveedor(es) seleccionado(s), suma
    // (cantidad seleccionada × precio cotizado) de cada selección; si aún no
    // hay selección, usa el comparativo best-case sobre la cantidad solicitada.
    const importeDetalle = (
        d: CostosRequisicion['detalles'] extends (infer U)[] | undefined
            ? U
            : never,
    ): { importe: number; tieneImporte: boolean } => {
        const selecciones = d.selecciones ?? [];
        if (selecciones.length > 0) {
            let total = 0;
            let tieneImporte = false;
            for (const s of selecciones) {
                const px = Number(s.cotizacion_precio?.precio_unitario ?? 0);
                if (px > 0) {
                    total += px * Number(s.cantidad);
                    tieneImporte = true;
                }
            }
            return { importe: total, tieneImporte };
        }

        const precio = precioImporte(d);
        return {
            importe: precio !== null ? precio * Number(d.cantidad) : 0,
            tieneImporte: precio !== null,
        };
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

    let subtotal = 0;
    const filas = detalles.map((d) => {
        const { importe, tieneImporte } = importeDetalle(d);
        subtotal += importe;
        return { d, tieneImporte, importe };
    });
    const iva = subtotal * 0.16;
    const total = subtotal + iva;

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
                        {filas.map(({ d, tieneImporte, importe }) => (
                            <tr key={d.id}>
                                <td className="text-right">
                                    {Number(d.cantidad).toLocaleString('es-MX')}{' '}
                                    {d.unidad}
                                </td>
                                <td>{d.descripcion}</td>
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
                                                    <div>
                                                        {seleccionado && (
                                                            <span className="mr-1">
                                                                ✓
                                                            </span>
                                                        )}
                                                        {fmt(px)}{' '}
                                                        <span className="text-[10px] font-normal text-base-content/50">
                                                            {
                                                                TIPO_MONEDA_LABELS[
                                                                    moneda
                                                                ]
                                                            }
                                                        </span>
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
                                <td className="text-right font-semibold">
                                    {tieneImporte ? (
                                        fmt(importe)
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
                        <tr>
                            <td
                                colSpan={2 + columnas.length}
                                className="text-right text-sm text-base-content/60"
                            >
                                Subtotal
                            </td>
                            <td className="text-right font-semibold">
                                {fmt(subtotal)}
                            </td>
                        </tr>
                        <tr>
                            <td
                                colSpan={2 + columnas.length}
                                className="text-right text-sm text-base-content/60"
                            >
                                IVA (16%)
                            </td>
                            <td className="text-right">{fmt(iva)}</td>
                        </tr>
                        <tr className="bg-base-200">
                            <td
                                colSpan={2 + columnas.length}
                                className="text-right text-sm font-semibold"
                            >
                                Total
                            </td>
                            <td className="text-right text-lg font-bold text-primary">
                                {fmt(total)}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    );
}
