import { FileCheck2, FileCode2, FileText, Plus, Receipt, ReceiptText } from 'lucide-react';
import { useState } from 'react';
import { formatMoney } from '@/components/costos/monto';
import { PortalRegistrarNotaCreditoModal } from '@/components/portal/registrar-nota-credito-modal';
import type { DocumentoVisor } from '@/components/portal/visor-documento-modal';
import { Button } from '@/components/ui/button';
import type { PortalTableroFactura } from '@/types/models';

const fmtFecha = (iso: string | null) =>
    iso ? new Date(`${iso}T12:00:00`).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: '2-digit' }) : '—';

/**
 * Detalle de una factura sin salir del tablero: datos fiscales, documentos,
 * notas de crédito (con su alta) y el desglose del pago cuando se partió en
 * parcialidades. Las entregas del almacén no se listan: al proveedor le importa
 * su comprobante de recepción, que ya vive en la tabla.
 */
export function DetalleFacturaModal({
    factura,
    onClose,
    onVerDocumento,
}: {
    factura: PortalTableroFactura;
    onClose: () => void;
    onVerDocumento: (doc: DocumentoVisor) => void;
}) {
    const [creandoNota, setCreandoNota] = useState(false);
    const moneda = factura.moneda ?? 'mxn';
    const parcialidades = factura.pago?.parcialidades ?? [];

    const documentos = [
        { titulo: 'PDF de la factura', url: factura.pdf_url, icon: FileText },
        { titulo: 'XML del CFDI', url: factura.xml_url, icon: FileCode2 },
        { titulo: 'Comprobante de recepción', url: factura.recepcion?.url ?? null, icon: FileCheck2 },
        { titulo: 'Contrarecibo', url: factura.contrarecibo_url, icon: ReceiptText },
    ].filter((d) => d.url);

    return (
        <>
            <dialog className="modal modal-open">
                <div className="modal-box w-11/12 max-w-3xl">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <h3 className="text-lg font-bold">Factura {factura.folio}</h3>
                            <p className="mt-0.5 text-sm text-base-content/60">{fmtFecha(factura.fecha)}</p>
                        </div>
                        {factura.cancelada && <span className="badge badge-error">Cancelada</span>}
                    </div>

                    <div className="mt-4 grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                        <Dato label="UUID fiscal" valor={factura.uuid_fiscal ?? '—'} mono />
                        <Dato label="Folio fiscal" valor={factura.folio_fiscal ?? '—'} />
                        <Dato label="Subtotal" valor={formatMoney(factura.subtotal, moneda)} />
                        <Dato label="Total" valor={formatMoney(factura.total, moneda)} />
                    </div>

                    {(factura.monto_anticipos > 0 || factura.monto_notas_credito > 0) && (
                        <div className="mt-3 rounded-lg bg-base-200 px-3 py-2 text-sm">
                            <div className="flex justify-between">
                                <span className="text-base-content/60">Anticipos aplicados</span>
                                <span>{formatMoney(factura.monto_anticipos, moneda)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-base-content/60">Notas de crédito vigentes</span>
                                <span>{formatMoney(factura.monto_notas_credito, moneda)}</span>
                            </div>
                            <div className="mt-1 flex justify-between border-t border-base-300 pt-1 font-semibold">
                                <span>Saldo facturado</span>
                                <span>{formatMoney(factura.saldo_facturado, moneda)}</span>
                            </div>
                        </div>
                    )}

                    {documentos.length > 0 && (
                        <section className="mt-5">
                            <h4 className="mb-2 text-sm font-semibold">Documentos</h4>
                            <div className="flex flex-wrap gap-2">
                                {documentos.map((d) => (
                                    <button
                                        key={d.titulo}
                                        type="button"
                                        className="btn btn-outline btn-sm gap-1"
                                        onClick={() => onVerDocumento({ titulo: d.titulo, url: d.url as string })}
                                    >
                                        <d.icon className="size-4" /> {d.titulo}
                                    </button>
                                ))}
                            </div>
                        </section>
                    )}

                    <section className="mt-5">
                        <div className="mb-2 flex items-center justify-between">
                            <h4 className="text-sm font-semibold">Notas de crédito</h4>
                            {!factura.cancelada && (
                                <Button size="xs" variant="outline" onClick={() => setCreandoNota(true)}>
                                    <Plus className="size-3" /> Registrar
                                </Button>
                            )}
                        </div>
                        {factura.notas_credito.length === 0 ? (
                            <p className="text-sm text-base-content/50">Sin notas de crédito.</p>
                        ) : (
                            <table className="table table-xs">
                                <thead>
                                    <tr>
                                        <th>Folio</th>
                                        <th>Fecha</th>
                                        <th className="text-right">Monto</th>
                                        <th>Estatus</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {factura.notas_credito.map((nc) => (
                                        <tr key={nc.id}>
                                            <td>{nc.folio ?? '—'}</td>
                                            <td>{fmtFecha(nc.fecha)}</td>
                                            <td className="text-right tabular-nums">{formatMoney(nc.monto, moneda)}</td>
                                            <td>{nc.estatus ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </section>

                    <section className="mt-5">
                        <h4 className="mb-2 text-sm font-semibold">Pago</h4>
                        {!factura.pago ? (
                            <p className="text-sm text-base-content/50">Todavía no hay pago programado.</p>
                        ) : (
                            <>
                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                    <Dato label="Monto" valor={formatMoney(factura.pago.monto, moneda)} />
                                    <Dato label="Estatus" valor={factura.pago.estatus ?? '—'} />
                                    <Dato label="Programado" valor={fmtFecha(factura.pago.fecha_programada)} />
                                    <Dato label="Pagado" valor={fmtFecha(factura.pago.fecha_realizada)} />
                                </div>

                                {parcialidades.length > 0 && (
                                    <table className="table table-xs mt-3">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th className="text-right">Monto</th>
                                                <th>Programado</th>
                                                <th>Pagado</th>
                                                <th>Comprobante</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {parcialidades.map((p) => (
                                                <tr key={p.id}>
                                                    <td>{p.numero ?? '—'}</td>
                                                    <td className="text-right tabular-nums">{formatMoney(p.monto, moneda)}</td>
                                                    <td>{fmtFecha(p.fecha_programada)}</td>
                                                    <td>{fmtFecha(p.fecha_realizada)}</td>
                                                    <td>
                                                        {p.comprobante_url ? (
                                                            <button
                                                                type="button"
                                                                className="btn btn-ghost btn-xs gap-1"
                                                                onClick={() =>
                                                                    onVerDocumento({
                                                                        titulo: `Comprobante de pago (parcialidad ${p.numero})`,
                                                                        url: p.comprobante_url as string,
                                                                    })
                                                                }
                                                            >
                                                                <Receipt className="size-3" /> Ver
                                                            </button>
                                                        ) : (
                                                            <span className="text-base-content/40">—</span>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                )}
                            </>
                        )}
                    </section>

                    <div className="modal-action">
                        <Button type="button" variant="ghost" onClick={onClose}>
                            Cerrar
                        </Button>
                    </div>
                </div>
                <div className="modal-backdrop" onClick={onClose}></div>
            </dialog>

            <PortalRegistrarNotaCreditoModal
                facturaId={factura.id}
                saldoFacturado={factura.saldo_facturado}
                open={creandoNota}
                onClose={() => setCreandoNota(false)}
            />
        </>
    );
}

function Dato({ label, valor, mono }: { label: string; valor: string; mono?: boolean }) {
    return (
        <div>
            <div className="text-xs text-base-content/60">{label}</div>
            <div className={mono ? 'font-mono text-sm break-all' : 'text-sm'}>{valor}</div>
        </div>
    );
}
