import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangleIcon } from 'lucide-react';
import { useState } from 'react';
import { ActivityTimeline } from '@/components/costos/activity-timeline';
import { CancelarModal } from '@/components/costos/cancelar-modal';
import { CotizacionMatriz } from '@/components/costos/cotizacion-matriz';
import { OcBuilder } from '@/components/costos/oc-builder';
import { LiberarRequisicionModal } from '@/components/costos/liberar-requisicion-modal';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { SharedData } from '@/types';
import type { CostosRequisicion, Proveedor } from '@/types/models';
import { REQUISICION_ESTATUS_COLORS, REQUISICION_ESTATUS_LABELS, TIPO_MONEDA_LABELS } from '@/types/models';

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
    moneda_cuenta: string | null;
    constancia_url: string | null;
    caratula_url: string | null;
    partidas: PartidaValidar[];
};

type Props = {
    requisicion: CostosRequisicion;
    proveedores: Pick<Proveedor, 'id' | 'razon_social' | 'nombre_comercial' | 'maneja_credito' | 'tipo_persona' | 'regimen_fiscal'>[];
    obraRubros: Array<{ id: number; label: string }>;
    aprobacionPendienteId: number | null;
    esUltimoNivel: boolean;
    proveedoresPorValidar: ProveedorPorValidar[];
};

type Tab = 'datos' | 'cotizacion' | 'definir-oc' | 'aprobacion' | 'ocs';

const fmtDate = (date: string | null) =>
    date ? new Date(date).toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '-';

function FirmarRequisicionModal({ aprobacionId, tipo, onClose }: { aprobacionId: number; tipo: 'aprobar' | 'rechazar'; onClose: () => void }) {
    const esAprobacion = tipo === 'aprobar';
    const minLen = esAprobacion ? 1 : 10;
    const { data, setData, post, processing, errors, reset } = useForm({ observaciones: '' });

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
        <dialog className="modal modal-open">
            <div className="modal-box w-11/12 max-w-4xl">
                <h2 className="text-2xl font-bold">{esAprobacion ? 'Aprobar requisición' : 'Rechazar requisición'}</h2>
                <p className="mt-1 text-sm text-base-content/60">
                    {esAprobacion
                        ? 'Agregue sus observaciones para firmar esta requisición.'
                        : 'El rechazo cancelará la requisición y deberá rehacerse. Mínimo 10 caracteres.'}
                </p>
                <form onSubmit={handleSubmit} className="mt-6">
                    <div className="form-control">
                        <label className="mb-2 text-sm font-medium">Observaciones</label>
                        <textarea
                            className={`textarea textarea-bordered w-full ${errors.observaciones ? 'textarea-error' : ''}`}
                            rows={6}
                            placeholder={esAprobacion ? 'Escriba sus observaciones...' : 'Motivo del rechazo...'}
                            value={data.observaciones}
                            onChange={(e) => setData('observaciones', e.target.value)}
                            required
                            maxLength={500}
                        />
                        {errors.observaciones && (
                            <span className="mt-1 text-xs text-error">{errors.observaciones}</span>
                        )}
                    </div>
                    <div className="modal-action">
                        <button type="button" className="btn" onClick={onClose} disabled={processing}>
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            className={`btn ${esAprobacion ? 'bg-green-600 hover:bg-green-700 text-white' : 'btn-error'}`}
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
        Object.fromEntries(proveedores.map((p) => [p.id, { accion: 'activar', reemplazos: {} }])),
    );

    const setAccion = (provId: number, accion: 'activar' | 'rechazar') =>
        setDecisiones((prev) => ({ ...prev, [provId]: { ...prev[provId], accion } }));

    const setReemplazo = (provId: number, detalleId: number, cotId: number | '') =>
        setDecisiones((prev) => ({
            ...prev,
            [provId]: { ...prev[provId], reemplazos: { ...prev[provId].reemplazos, [detalleId]: cotId } },
        }));

    const fmt = (n: number) => `$${n.toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;

    // Un rechazo sin reemplazo en TODAS sus partidas implica rechazar la requisición.
    const rechazaRequisicion = proveedores.some((p) => {
        const d = decisiones[p.id];
        if (d.accion !== 'rechazar') return false;
        return p.partidas.some((part) => !d.reemplazos[part.requisicion_detalle_id]);
    });

    const submit = () => {
        const validaciones = proveedores.map((p) => {
            const d = decisiones[p.id];
            const reemplazos = d.accion === 'rechazar'
                ? p.partidas
                      .filter((part) => d.reemplazos[part.requisicion_detalle_id])
                      .map((part) => {
                          const cotId = d.reemplazos[part.requisicion_detalle_id] as number;
                          const alt = part.alternativas.find((a) => a.cotizacion_precio_id === cotId)!;
                          return {
                              requisicion_detalle_id: part.requisicion_detalle_id,
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
        <dialog className="modal modal-open">
            <div className="modal-box w-11/12 max-w-5xl">
                <h2 className="text-2xl font-bold">
                    {step === 1 ? 'Validar documentación del proveedor' : 'Confirmar firma'}
                </h2>
                <ul className="steps steps-horizontal w-full my-4">
                    <li className={`step ${step >= 1 ? 'step-primary' : ''}`}>Documentación</li>
                    <li className={`step ${step >= 2 ? 'step-primary' : ''}`}>Aprobar OC</li>
                </ul>

                {step === 1 && (
                    <div className="space-y-6">
                        {proveedores.map((p) => {
                            const d = decisiones[p.id];
                            return (
                                <div key={p.id} className="rounded-lg border border-base-300 p-4">
                                    <div className="mb-3 flex items-center justify-between">
                                        <div>
                                            <div className="font-semibold">{p.razon_social}</div>
                                            <div className="text-xs text-base-content/60">
                                                {p.rfc} · {p.tipo_persona === 'moral' ? 'Persona Moral' : 'Persona Física'} · {p.regimen ?? 'Sin régimen'}
                                            </div>
                                        </div>
                                        <div className="join">
                                            <button
                                                type="button"
                                                className={`btn btn-sm join-item ${d.accion === 'activar' ? 'btn-success' : 'btn-ghost'}`}
                                                onClick={() => setAccion(p.id, 'activar')}
                                            >
                                                Activar
                                            </button>
                                            <button
                                                type="button"
                                                className={`btn btn-sm join-item ${d.accion === 'rechazar' ? 'btn-error' : 'btn-ghost'}`}
                                                onClick={() => setAccion(p.id, 'rechazar')}
                                            >
                                                Rechazar
                                            </button>
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-2 gap-3 text-sm">
                                        <div>
                                            <span className="text-base-content/60">Banco: </span>
                                            {p.banco ?? '-'} · {p.moneda_cuenta}
                                        </div>
                                        <div>
                                            <span className="text-base-content/60">Titular: </span>
                                            {p.titular_cuenta ?? '-'}
                                        </div>
                                        <div>
                                            <span className="text-base-content/60">CLABE/Cuenta: </span>
                                            {p.clabe || p.numero_cuenta || '-'}
                                        </div>
                                        <div className="flex gap-3">
                                            {p.constancia_url && (
                                                <a className="link link-primary" href={p.constancia_url} target="_blank" rel="noreferrer">Constancia</a>
                                            )}
                                            {p.caratula_url && (
                                                <a className="link link-primary" href={p.caratula_url} target="_blank" rel="noreferrer">Carátula</a>
                                            )}
                                        </div>
                                    </div>

                                    {d.accion === 'rechazar' && (
                                        <div className="mt-4 space-y-2 rounded bg-base-200 p-3">
                                            <p className="text-sm font-medium">Reasignar partidas a otro proveedor que cotizó:</p>
                                            {p.partidas.map((part) => (
                                                <div key={part.requisicion_detalle_id} className="flex items-center justify-between gap-2">
                                                    <span className="text-sm">{part.descripcion}</span>
                                                    <select
                                                        className="select select-bordered select-sm w-72"
                                                        value={d.reemplazos[part.requisicion_detalle_id] ?? ''}
                                                        onChange={(e) =>
                                                            setReemplazo(p.id, part.requisicion_detalle_id, e.target.value ? Number(e.target.value) : '')
                                                        }
                                                    >
                                                        <option value="">— Rechazar (sin reemplazo) —</option>
                                                        {part.alternativas.map((a) => (
                                                            <option key={a.cotizacion_precio_id} value={a.cotizacion_precio_id}>
                                                                {a.proveedor} · {fmt(a.precio_unitario)}
                                                            </option>
                                                        ))}
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
                                <span>Hay un proveedor rechazado sin reemplazo: al confirmar se <strong>rechazará la requisición completa</strong>.</span>
                            </div>
                        ) : (
                            <div className="alert alert-success">
                                <span>Al confirmar se activarán los proveedores aprobados y la requisición quedará <strong>aprobada</strong>.</span>
                            </div>
                        )}
                        <div className="form-control">
                            <label className="mb-2 text-sm font-medium">Observaciones</label>
                            <textarea
                                className="textarea textarea-bordered w-full"
                                rows={4}
                                value={observaciones}
                                onChange={(e) => setObservaciones(e.target.value)}
                                maxLength={500}
                                placeholder="Observaciones de la firma..."
                            />
                        </div>
                    </div>
                )}

                <div className="modal-action">
                    <button type="button" className="btn" onClick={onClose} disabled={processing}>
                        Cancelar
                    </button>
                    {step === 1 ? (
                        <button type="button" className="btn btn-primary" onClick={() => setStep(2)}>
                            Continuar
                        </button>
                    ) : (
                        <>
                            <button type="button" className="btn" onClick={() => setStep(1)} disabled={processing}>
                                Atrás
                            </button>
                            <button
                                type="button"
                                className={`btn ${rechazaRequisicion ? 'btn-error' : 'bg-green-600 hover:bg-green-700 text-white'}`}
                                onClick={submit}
                                disabled={processing || observaciones.trim().length < 1}
                            >
                                {rechazaRequisicion ? 'Rechazar requisición' : 'Firmar y aprobar'}
                            </button>
                        </>
                    )}
                </div>
            </div>
            <div className="modal-backdrop" onClick={onClose} />
        </dialog>
    );
}

export default function RequisicionesShow({ requisicion, proveedores, aprobacionPendienteId, esUltimoNivel, proveedoresPorValidar }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/requisiciones' },
        { title: 'Requisiciones', href: '/admin/costos/requisiciones' },
        { title: requisicion.folio, href: `/admin/costos/requisiciones/${requisicion.id}` },
    ];

    const { can } = useCan();
    const [tab, setTab] = useState<Tab>('datos');
    const [enviando, setEnviando] = useState(false);
    const [cancelando, setCancelando] = useState(false);
    const [liberando, setLiberando] = useState(false);
    const [firmando, setFirmando] = useState<'aprobar' | 'rechazar' | null>(null);
    const [validando, setValidando] = useState(false);

    const requiereValidacion = esUltimoNivel && proveedoresPorValidar.length > 0;

    const editable = ['borrador', 'rechazada'].includes(requisicion.estatus);
    const cotizable = ['borrador', 'cotizada', 'rechazada', 'aprobada'].includes(requisicion.estatus);

    const handleEnviarAprobacion = () => {
        if (!confirm('¿Enviar la requisición a aprobación? Esta acción crea las firmas pendientes y bloquea ediciones.')) {
            return;
        }
        setEnviando(true);
        router.post(`/admin/costos/requisiciones/${requisicion.id}/enviar-aprobacion`, {}, {
            preserveScroll: true,
            onFinish: () => setEnviando(false),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={requisicion.folio} />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">{requisicion.folio}</h1>
                        <div className="mt-1 flex flex-wrap items-center gap-2">
                            <span className={`badge ${REQUISICION_ESTATUS_COLORS[requisicion.estatus]}`}>
                                {REQUISICION_ESTATUS_LABELS[requisicion.estatus]}
                            </span>
                            {requisicion.sobre_obra_cerrada && (
                                <span className="badge badge-warning gap-1" title="Carga sobre obra/adicional cerrado">
                                    ⚠ Obra cerrada
                                </span>
                            )}
                            <span className="text-sm text-base-content/60">
                                {requisicion.solicitante?.name} · {requisicion.departamento?.descripcion}
                                {requisicion.obra && ` · ${requisicion.obra.no ? `OP-${requisicion.obra.no} ` : ''}${requisicion.obra.descripcion}`}
                                {' · '}{fmtDate(requisicion.created_at)}
                            </span>
                        </div>
                    </div>

                    <div className="flex gap-2">
                        {can('costos.requisiciones.cotizar') && (
                            <Button
                                variant="outline"
                                onClick={() => {
                                    if (confirm('¿Duplicar esta requisición en una nueva (borrador) con folio nuevo? Se copian partidas y cotizaciones.')) {
                                        router.post(`/admin/costos/requisiciones/${requisicion.id}/duplicar`);
                                    }
                                }}
                            >
                                Duplicar
                            </Button>
                        )}

                        {editable && can('costos.requisiciones.crear') && (
                            <Button variant="outline" asChild>
                                <Link href={`/admin/costos/requisiciones/${requisicion.id}/edit`}>Editar</Link>
                            </Button>
                        )}

                        {requisicion.estatus === 'cotizada' && can('costos.requisiciones.cotizar') && (
                            <Button onClick={handleEnviarAprobacion} disabled={enviando}>
                                {enviando ? 'Enviando...' : 'Enviar a aprobación'}
                            </Button>
                        )}

                        {!['liberada', 'cancelada'].includes(requisicion.estatus) && can('costos.requisiciones.cancelar') && (
                            <Button variant="outline" className="text-error" onClick={() => setCancelando(true)}>
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
                    <div className="alert alert-error mb-4">
                        <span><strong>Motivo de rechazo:</strong> {requisicion.motivo_rechazo}</span>
                    </div>
                )}

                <div role="tablist" className="tabs tabs-bordered mb-4">
                    <button role="tab" className={`tab ${tab === 'datos' ? 'tab-active' : ''}`} onClick={() => setTab('datos')}>
                        Datos
                    </button>
                    {can('costos.requisiciones.cotizar') && (
                        <button role="tab" className={`tab ${tab === 'cotizacion' ? 'tab-active' : ''}`} onClick={() => setTab('cotizacion')}>
                            Cotización
                        </button>
                    )}
                    {can('costos.requisiciones.cotizar') && (
                        <button role="tab" className={`tab ${tab === 'definir-oc' ? 'tab-active' : ''}`} onClick={() => setTab('definir-oc')}>
                            Definir OC
                        </button>
                    )}
                    <button role="tab" className={`tab ${tab === 'aprobacion' ? 'tab-active' : ''}`} onClick={() => setTab('aprobacion')}>
                        Aprobación
                    </button>
                    {(requisicion.ordenes_generadas?.length ?? 0) > 0 && (
                        <button role="tab" className={`tab ${tab === 'ocs' ? 'tab-active' : ''}`} onClick={() => setTab('ocs')}>
                            OCs ({requisicion.ordenes_generadas?.length})
                        </button>
                    )}
                </div>

                {tab === 'datos' && (
                    <div className="rounded-lg border border-base-300 p-4">
                        {requisicion.justificacion && (
                            <div className="mb-4">
                                <div className="text-xs text-base-content/60">Justificación</div>
                                <p className="text-sm">{requisicion.justificacion}</p>
                            </div>
                        )}

                        <h3 className="mb-2 font-medium">Partidas solicitadas</h3>
                        <div className="overflow-x-auto">
                            <table className="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Descripción</th>
                                        <th>Código producto</th>
                                        <th>Centro de Costos</th>
                                        <th>Uso CFDI</th>
                                        <th className="text-right">Disponible</th>
                                        <th>Unidad</th>
                                        <th className="text-right">Cantidad</th>
                                        <th>Notas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {requisicion.detalles?.map((d) => {
                                        const presupuestado = Number(d.obra_rubro?.presupuestado ?? 0);
                                        const acumulado = Number(d.obra_rubro?.acumulado ?? 0);
                                        const disponible = presupuestado - acumulado;
                                        const sobregiro = !!d.obra_rubro && disponible < 0;
                                        const fmtMoney = (n: number) => `$${n.toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;

                                        return (
                                            <tr key={d.id} className={sobregiro ? 'bg-error/5' : ''}>
                                                <td>{d.descripcion}</td>
                                                <td className="text-xs">{d.codigo_producto ?? '-'}</td>
                                                <td className="text-xs">
                                                    {d.obra_rubro ? (
                                                        <div className="space-y-0.5">
                                                            <div className="font-medium">
                                                                {d.obra_rubro.obra?.no && (
                                                                    <span className="badge badge-ghost badge-xs mr-1 font-mono">OP-{d.obra_rubro.obra.no}</span>
                                                                )}
                                                                {d.obra_rubro.obra?.descripcion ?? '-'}
                                                            </div>
                                                            <div className="text-base-content/60">
                                                                {d.obra_rubro.rubro?.codigo} · {d.obra_rubro.rubro?.descripcion}
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <span className="text-warning">Sin centro de costos</span>
                                                    )}
                                                </td>
                                                <td className="text-xs">{d.uso_cfdi ? `${d.uso_cfdi.clave}` : '-'}</td>
                                                <td className={`text-right text-xs font-medium ${sobregiro ? 'text-error' : ''}`}>
                                                    {d.obra_rubro ? (
                                                        <>
                                                            {fmtMoney(disponible)}
                                                            {sobregiro && <span className="ml-1">⚠</span>}
                                                        </>
                                                    ) : '—'}
                                                </td>
                                                <td>{d.unidad}</td>
                                                <td className="text-right">{Number(d.cantidad).toLocaleString('es-MX')}</td>
                                                <td className="text-xs text-base-content/60">{d.notas ?? '-'}</td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        <ComparativoCotizaciones requisicion={requisicion} />
                    </div>
                )}

                {tab === 'cotizacion' && can('costos.requisiciones.cotizar') && (
                    <CotizacionMatriz
                        requisicion={requisicion}
                        proveedores={proveedores}
                        editable={cotizable}
                    />
                )}

                {tab === 'definir-oc' && can('costos.requisiciones.cotizar') && (
                    <>
                        <OcBuilder
                            requisicion={requisicion}
                            proveedores={proveedores}
                            editable={cotizable}
                        />
                        {requisicion.estatus === 'aprobada' && can('costos.requisiciones.liberar') && (
                            <div className="mt-4 flex justify-end">
                                <Button onClick={() => setLiberando(true)}>
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
                                        onClick={() => (requiereValidacion ? setValidando(true) : setFirmando('aprobar'))}
                                    >
                                        {requiereValidacion ? 'Firmar (validar proveedores)' : 'Firmar'}
                                    </Button>
                                    <Button variant="destructive" onClick={() => setFirmando('rechazar')}>
                                        Rechazar
                                    </Button>
                                </div>
                            )}
                        </div>
                        {!requisicion.aprobaciones || requisicion.aprobaciones.length === 0 ? (
                            <p className="text-sm text-base-content/60">Aún no se ha enviado a aprobación.</p>
                        ) : (
                            <div className="space-y-2">
                                {requisicion.aprobaciones
                                    .slice()
                                    .sort((a, b) => a.nivel - b.nivel)
                                    .map((a) => (
                                        <div key={a.id} className="flex items-center justify-between rounded border border-base-200 p-3">
                                            <div>
                                                <div className="text-sm font-medium">Nivel {a.nivel}: {a.aprobador?.name ?? '-'}</div>
                                                {a.observaciones && (
                                                    <div className="text-xs text-base-content/60 mt-1">{a.observaciones}</div>
                                                )}
                                            </div>
                                            <span className={`badge badge-sm ${
                                                a.estatus === 'aprobada' ? 'badge-success' :
                                                a.estatus === 'rechazada' ? 'badge-error' :
                                                a.estatus === 'cancelada' ? 'badge-neutral' :
                                                'badge-warning'
                                            }`}>
                                                {a.estatus}
                                            </span>
                                        </div>
                                    ))}
                            </div>
                        )}

                        {requisicion.activities && requisicion.activities.length > 0 && (
                            <div className="mt-6">
                                <h3 className="mb-3 font-medium">Historial</h3>
                                <ActivityTimeline activities={requisicion.activities} />
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

                {tab === 'ocs' && (
                    <div className="rounded-lg border border-base-300 p-4">
                        <h3 className="mb-3 font-medium">Órdenes de compra generadas</h3>
                        <div className="space-y-2">
                            {requisicion.ordenes_generadas?.map((oc) => (
                                <Link
                                    key={oc.id}
                                    href={`/admin/costos/ordenes-compra/${oc.id}`}
                                    className="flex items-center justify-between rounded border border-base-200 p-3 hover:bg-base-100"
                                >
                                    <div>
                                        <div className="font-mono text-sm">{oc.folio}</div>
                                        <div className="text-xs text-base-content/60">{oc.proveedor?.razon_social ?? '-'}</div>
                                    </div>
                                    <div className="text-right">
                                        <div className="font-medium">${Number(oc.total).toLocaleString('es-MX', { minimumFractionDigits: 2 })}</div>
                                        <div className="text-xs text-base-content/60">{oc.estatus}</div>
                                    </div>
                                </Link>
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
 * Cantidad | Descripción | Precio × proveedor (N columnas) | Importe (mejor)
 *
 * "Importe" usa el precio del mejor proveedor global cuando éste existe; si
 * no hay cotización completa, usa el menor precio cotizado por cada partida
 * (best-case mix). Al final calcula subtotal, IVA 16% y total.
 */
function ComparativoCotizaciones({ requisicion }: { requisicion: CostosRequisicion }) {
    const detalles = requisicion.detalles ?? [];
    const proveedores = new Map<number, { id: number; nombre: string }>();
    for (const d of detalles) {
        for (const c of d.cotizaciones ?? []) {
            if (!c.proveedor) continue;
            proveedores.set(c.proveedor.id, {
                id: c.proveedor.id,
                nombre: c.proveedor.nombre_comercial || c.proveedor.razon_social,
            });
        }
    }
    if (proveedores.size === 0) return null;

    const provList = Array.from(proveedores.values());
    const mejorProveedorId = requisicion.mejor_proveedor?.id ?? null;
    const fmt = (n: number) => `$${n.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

    const precioPartidaProv = (detalleId: number, proveedorId: number): number | null => {
        const d = detalles.find((x) => x.id === detalleId);
        const cot = d?.cotizaciones?.find((c) => c.proveedor_id === proveedorId);
        return cot ? Number(cot.precio_unitario) : null;
    };

    const precioImporte = (d: CostosRequisicion['detalles'] extends (infer U)[] | undefined ? U : never): number | null => {
        // Si hay mejor proveedor global y cotizó esta partida → usar ese precio
        if (mejorProveedorId) {
            const p = precioPartidaProv(d.id, mejorProveedorId);
            if (p !== null) return p;
        }
        // Fallback: menor precio cotizado para esta partida
        const precios = (d.cotizaciones ?? []).map((c) => Number(c.precio_unitario)).filter((n) => n > 0);
        return precios.length > 0 ? Math.min(...precios) : null;
    };

    // Mejor (menor) precio por partida — para resaltar la celda ganadora.
    const mejorPrecioPartida = new Map<number, number>();
    for (const d of detalles) {
        const precios = (d.cotizaciones ?? []).map((c) => Number(c.precio_unitario)).filter((n) => n > 0);
        if (precios.length > 0) {
            mejorPrecioPartida.set(d.id, Math.min(...precios));
        }
    }

    let subtotal = 0;
    const filas = detalles.map((d) => {
        const precio = precioImporte(d);
        const importe = precio !== null ? precio * Number(d.cantidad) : 0;
        subtotal += importe;
        return { d, precio, importe };
    });
    const iva = subtotal * 0.16;
    const total = subtotal + iva;

    return (
        <div className="mt-6">
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h3 className="font-medium">Comparativo de cotizaciones</h3>
                <div className="flex items-center gap-3 text-[10px] text-base-content/60">
                    <span className="inline-flex items-center gap-1">
                        <span className="inline-block size-2 rounded-full bg-primary"></span> Seleccionado
                    </span>
                    <span className="inline-flex items-center gap-1">
                        <span className="inline-block size-2 rounded-full bg-success"></span> Mejor precio
                    </span>
                </div>
            </div>
            <div className="overflow-x-auto rounded-lg border border-base-300">
                <table className="table table-sm">
                    <thead className="bg-base-200">
                        <tr>
                            <th className="text-right">Cantidad</th>
                            <th>Descripción</th>
                            {provList.map((p) => (
                                <th
                                    key={p.id}
                                    className={`text-right ${p.id === mejorProveedorId ? 'text-success' : ''}`}
                                    title={p.nombre}
                                >
                                    {p.nombre}
                                    {p.id === mejorProveedorId && <span className="ml-1 text-[10px]">★</span>}
                                </th>
                            ))}
                            <th className="text-right">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filas.map(({ d, precio, importe }) => (
                            <tr key={d.id}>
                                <td className="text-right">{Number(d.cantidad).toLocaleString('es-MX')} {d.unidad}</td>
                                <td>{d.descripcion}</td>
                                {provList.map((p) => {
                                    const cot = d.cotizaciones?.find((c) => c.proveedor_id === p.id);
                                    const px = cot ? Number(cot.precio_unitario) : null;
                                    const dias = cot?.tiempo_entrega_dias ?? null;
                                    const moneda = cot?.moneda ?? 'mxn';
                                    const esMejorPartida = px !== null && px === mejorPrecioPartida.get(d.id);
                                    const seleccionado = (d.selecciones ?? []).some((s) => s.proveedor_id === p.id);
                                    const classes = [
                                        'text-right align-top',
                                        seleccionado
                                            ? 'bg-primary/15 font-semibold text-primary ring-1 ring-inset ring-primary/50'
                                            : esMejorPartida ? 'bg-success/15 font-semibold text-success' : '',
                                        p.id === mejorProveedorId && !esMejorPartida && !seleccionado ? 'text-success' : '',
                                    ].filter(Boolean).join(' ');
                                    return (
                                        <td key={p.id} className={classes}>
                                            {px !== null ? (
                                                <>
                                                    <div>{seleccionado && <span className="mr-1">✓</span>}{fmt(px)} <span className="text-[10px] font-normal text-base-content/50">{TIPO_MONEDA_LABELS[moneda]}</span></div>
                                                    {dias !== null && dias > 0 && (
                                                        <div className="text-[10px] font-normal text-base-content/60">
                                                            {dias} {dias === 1 ? 'día' : 'días'} entrega
                                                        </div>
                                                    )}
                                                </>
                                            ) : (
                                                <span className="text-base-content/30">—</span>
                                            )}
                                        </td>
                                    );
                                })}
                                <td className="text-right font-semibold">
                                    {precio !== null ? fmt(importe) : <span className="text-base-content/30">—</span>}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colSpan={2 + provList.length} className="text-right text-sm text-base-content/60">Subtotal</td>
                            <td className="text-right font-semibold">{fmt(subtotal)}</td>
                        </tr>
                        <tr>
                            <td colSpan={2 + provList.length} className="text-right text-sm text-base-content/60">IVA (16%)</td>
                            <td className="text-right">{fmt(iva)}</td>
                        </tr>
                        <tr className="bg-base-200">
                            <td colSpan={2 + provList.length} className="text-right text-sm font-semibold">Total</td>
                            <td className="text-right text-lg font-bold text-primary">{fmt(total)}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    );
}
