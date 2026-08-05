import { Head } from '@inertiajs/react';
import { Download, FileCheck2, FileText, FileUp, Receipt, ReceiptText, Upload } from 'lucide-react';
import { type ComponentType, type FormEvent, useState } from 'react';
import { formatMoney } from '@/components/costos/monto';
import { Button } from '@/components/ui/button';

/**
 * PROTOTIPO de portal simplificado: una sola tabla de órdenes de compra donde
 * el proveedor sube factura, comprobante de recepción y consulta su pago en el
 * mismo renglón. Todo el estado vive en el navegador; no hay backend.
 */

type ArchivoDemo = { nombre: string; fecha: string };

type FacturaDemo = {
    id: string;
    folio: string;
    fecha: string;
    total: number;
    xml: string | null;
    pdf: string | null;
    recepcion: ArchivoDemo | null;
    /** PDF del contrarecibo; hasta que existe, el pago sigue "por programar". */
    contrarecibo: string | null;
    comprobantePago: string | null;
};

/** Documento abierto en el visor. */
type DocVisor = { titulo: string; nombre: string };

type OcDemo = {
    id: string;
    folio: string;
    fecha: string;
    total: number;
    moneda: string;
    facturas: FacturaDemo[];
};

const hoy = () => new Date().toISOString().slice(0, 10);

const fmtFecha = (iso: string | null) =>
    iso ? new Date(`${iso}T12:00:00`).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: '2-digit' }) : '—';

const OC_DEMO: OcDemo[] = [
    {
        id: 'oc-1',
        folio: 'OC-2608-014',
        fecha: '2026-07-14',
        total: 348000,
        moneda: 'mxn',
        facturas: [
            {
                id: 'f-1',
                folio: 'A-1042',
                fecha: '2026-07-20',
                total: 174000,
                xml: 'cfdi-A-1042.xml',
                pdf: 'factura-A-1042.pdf',
                recepcion: { nombre: 'remision-almacen-1042.pdf', fecha: '2026-07-22' },
                contrarecibo: 'contrarecibo-A-1042.pdf',
                comprobantePago: 'transferencia-1042.pdf',
            },
            {
                id: 'f-2',
                folio: 'A-1067',
                fecha: '2026-07-31',
                total: 116000,
                xml: 'cfdi-A-1067.xml',
                pdf: 'factura-A-1067.pdf',
                recepcion: { nombre: 'remision-almacen-1067.pdf', fecha: '2026-08-01' },
                // Recibida en almacén pero sin contrarecibo todavía: por programar.
                contrarecibo: null,
                comprobantePago: null,
            },
        ],
    },
    {
        id: 'oc-2',
        folio: 'OC-2607-238',
        fecha: '2026-07-02',
        total: 92800,
        moneda: 'mxn',
        facturas: [
            {
                id: 'f-3',
                folio: 'A-0998',
                fecha: '2026-07-09',
                total: 92800,
                xml: 'cfdi-A-0998.xml',
                pdf: null,
                recepcion: null,
                contrarecibo: null,
                comprobantePago: null,
            },
        ],
    },
    {
        id: 'oc-3',
        folio: 'OC-2608-051',
        fecha: '2026-08-01',
        total: 57420,
        moneda: 'mxn',
        facturas: [],
    },
    // Facturada al 100% y pagada: vive en la pestaña de completadas.
    {
        id: 'oc-4',
        folio: 'OC-2605-117',
        fecha: '2026-05-19',
        total: 143500,
        moneda: 'mxn',
        facturas: [
            {
                id: 'f-4',
                folio: 'A-0871',
                fecha: '2026-05-26',
                total: 143500,
                xml: 'cfdi-A-0871.xml',
                pdf: 'factura-A-0871.pdf',
                recepcion: { nombre: 'remision-almacen-0871.pdf', fecha: '2026-05-28' },
                contrarecibo: 'contrarecibo-A-0871.pdf',
                comprobantePago: 'transferencia-0871.pdf',
            },
        ],
    },
];

/** Icono de documento: abre el visor si existe, se ve apagado si falta. */
function DocIcono({
    icon: Icon,
    titulo,
    nombre,
    onVer,
    className,
}: {
    icon: ComponentType<{ className?: string }>;
    titulo: string;
    nombre: string | null;
    onVer: (doc: DocVisor) => void;
    className?: string;
}) {
    if (!nombre) {
        return (
            <span className="tooltip" data-tip={`Sin ${titulo.toLowerCase()}`}>
                <Icon className="size-4 text-base-content/20" />
            </span>
        );
    }

    return (
        <span className="tooltip" data-tip={`${titulo}: ${nombre}`}>
            <button
                type="button"
                aria-label={`Ver ${titulo}`}
                className={`btn btn-ghost btn-xs btn-square ${className ?? 'text-base-content/60'}`}
                onClick={() => onVer({ titulo, nombre })}
            >
                <Icon className="size-4" />
            </button>
        </span>
    );
}

function VisorDocumentoModal({ doc, onClose }: { doc: DocVisor; onClose: () => void }) {
    return (
        <dialog className="modal modal-open">
            <div className="modal-box w-11/12 max-w-2xl">
                <h3 className="text-lg font-bold">{doc.titulo}</h3>
                <p className="mt-1 truncate text-sm text-base-content/60">{doc.nombre}</p>

                <div className="mt-4 flex h-64 flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-base-300 bg-base-200 text-base-content/50">
                    <FileText className="size-10" />
                    <span className="text-sm">Vista previa del documento</span>
                    <span className="text-xs">(el prototipo no carga archivos reales)</span>
                </div>

                <div className="modal-action">
                    <Button type="button" variant="ghost" onClick={onClose}>
                        Cerrar
                    </Button>
                    <Button type="button" disabled>
                        <Download className="size-4" />
                        Descargar
                    </Button>
                </div>
            </div>
            <div className="modal-backdrop" onClick={onClose}></div>
        </dialog>
    );
}

function SubirFacturaModal({ oc, onClose, onGuardar }: { oc: OcDemo; onClose: () => void; onGuardar: (f: FacturaDemo) => void }) {
    const [xml, setXml] = useState<File | null>(null);
    const [pdf, setPdf] = useState<File | null>(null);
    const [folio, setFolio] = useState('');
    const [total, setTotal] = useState('');

    const submit = (e: FormEvent) => {
        e.preventDefault();
        onGuardar({
            id: `f-${Math.round(performance.now())}`,
            folio: folio || xml?.name.replace(/\.xml$/i, '') || 'SIN FOLIO',
            fecha: hoy(),
            total: Number(total) || 0,
            xml: xml?.name ?? null,
            pdf: pdf?.name ?? null,
            recepcion: null,
            contrarecibo: null,
            comprobantePago: null,
        });
        onClose();
    };

    return (
        <dialog className="modal modal-open">
            <div className="modal-box w-11/12 max-w-lg">
                <h3 className="text-lg font-bold">Subir factura</h3>
                <p className="mt-1 text-sm text-base-content/60">{oc.folio}</p>

                <form onSubmit={submit} className="mt-4 space-y-4">
                    <div>
                        <label className="label text-sm font-medium" htmlFor="xml">
                            XML del CFDI
                        </label>
                        <input
                            id="xml"
                            type="file"
                            accept=".xml,application/xml,text/xml"
                            className="file-input file-input-bordered w-full"
                            onChange={(e) => setXml(e.target.files?.[0] ?? null)}
                        />
                    </div>

                    <div>
                        <label className="label text-sm font-medium" htmlFor="pdf">
                            PDF (opcional)
                        </label>
                        <input
                            id="pdf"
                            type="file"
                            accept=".pdf,application/pdf"
                            className="file-input file-input-bordered w-full"
                            onChange={(e) => setPdf(e.target.files?.[0] ?? null)}
                        />
                    </div>

                    <div className="grid grid-cols-2 gap-3 rounded-lg bg-base-200 p-3">
                        <div>
                            <label className="label text-xs" htmlFor="folio">
                                Folio
                            </label>
                            <input
                                id="folio"
                                className="input input-bordered input-sm w-full"
                                value={folio}
                                onChange={(e) => setFolio(e.target.value)}
                                placeholder="A-1099"
                            />
                        </div>
                        <div>
                            <label className="label text-xs" htmlFor="total">
                                Total
                            </label>
                            <input
                                id="total"
                                type="number"
                                step="0.01"
                                className="input input-bordered input-sm w-full"
                                value={total}
                                onChange={(e) => setTotal(e.target.value)}
                                placeholder="116000"
                            />
                        </div>
                        <p className="col-span-2 text-xs text-base-content/60">
                            En producción estos datos se leen del XML; aquí se capturan solo para la demo.
                        </p>
                    </div>

                    {pdf && <p className="text-xs text-base-content/60">PDF adjunto: {pdf.name}</p>}

                    <div className="modal-action">
                        <Button type="button" variant="ghost" onClick={onClose}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={!xml}>
                            Subir factura
                        </Button>
                    </div>
                </form>
            </div>
            <div className="modal-backdrop" onClick={onClose}></div>
        </dialog>
    );
}

function SubirComprobanteModal({
    factura,
    onClose,
    onGuardar,
}: {
    factura: FacturaDemo;
    onClose: () => void;
    onGuardar: (a: ArchivoDemo) => void;
}) {
    const [archivo, setArchivo] = useState<File | null>(null);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (!archivo) {
            return;
        }
        onGuardar({ nombre: archivo.name, fecha: hoy() });
        onClose();
    };

    return (
        <dialog className="modal modal-open">
            <div className="modal-box w-11/12 max-w-lg">
                <h3 className="text-lg font-bold">Comprobante de recepción</h3>
                <p className="mt-1 text-sm text-base-content/60">Factura {factura.folio}</p>

                <form onSubmit={submit} className="mt-4 space-y-4">
                    <input
                        type="file"
                        accept=".pdf,image/*"
                        className="file-input file-input-bordered w-full"
                        onChange={(e) => setArchivo(e.target.files?.[0] ?? null)}
                    />
                    <p className="text-xs text-base-content/60">
                        Adjunta la remisión o acuse sellado por almacén (PDF o foto). Con el comprobante, la factura entra a revisión y se
                        le programa fecha de pago.
                    </p>

                    <div className="modal-action">
                        <Button type="button" variant="ghost" onClick={onClose}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={!archivo}>
                            Subir comprobante
                        </Button>
                    </div>
                </form>
            </div>
            <div className="modal-backdrop" onClick={onClose}></div>
        </dialog>
    );
}

/**
 * Celda de la orden: el folio manda y el total va debajo, en su propio renglón.
 * Antes competía con el folio pegado al borde derecho de la columna y se leía
 * como si fuera de otra fila.
 */
function CeldaOrden({ oc, facturado }: { oc: OcDemo; facturado: number }) {
    const pendiente = oc.total - facturado;

    return (
        <>
            <div className="font-bold text-primary">{oc.folio}</div>
            <div className="mt-0.5 text-base font-semibold tabular-nums">{formatMoney(oc.total, oc.moneda)}</div>
            <div className="mt-0.5 text-xs text-base-content/60">
                {facturado === 0 ? (
                    'Sin facturar'
                ) : (
                    <>
                        Facturado {formatMoney(facturado, oc.moneda)}
                        {pendiente > 0 && ` · pendiente ${formatMoney(pendiente, oc.moneda)}`}
                    </>
                )}
            </div>
        </>
    );
}

export default function PortalPruebaTablero() {
    const [ordenes, setOrdenes] = useState<OcDemo[]>(OC_DEMO);
    const [facturandoOc, setFacturandoOc] = useState<OcDemo | null>(null);
    const [comprobandoFactura, setComprobandoFactura] = useState<{ ocId: string; factura: FacturaDemo } | null>(null);
    const [docVisor, setDocVisor] = useState<DocVisor | null>(null);
    const [tab, setTab] = useState<'activas' | 'completadas'>('activas');

    const agregarFactura = (ocId: string, factura: FacturaDemo) => {
        setOrdenes((prev) => prev.map((oc) => (oc.id === ocId ? { ...oc, facturas: [...oc.facturas, factura] } : oc)));
    };

    const guardarComprobante = (ocId: string, facturaId: string, archivo: ArchivoDemo) => {
        setOrdenes((prev) =>
            prev.map((oc) =>
                oc.id !== ocId
                    ? oc
                    : {
                          ...oc,
                          facturas: oc.facturas.map((f) => (f.id === facturaId ? { ...f, recepcion: archivo } : f)),
                      },
            ),
        );
    };

    const facturado = (oc: OcDemo) => oc.facturas.reduce((acc, f) => acc + f.total, 0);

    /** Completada: ya se facturó todo y no queda factura sin su comprobante de pago. */
    const completada = (oc: OcDemo) =>
        oc.facturas.length > 0 && facturado(oc) >= oc.total && oc.facturas.every((f) => f.comprobantePago);

    const activas = ordenes.filter((oc) => !completada(oc));
    const completadas = ordenes.filter(completada);
    const visibles = tab === 'activas' ? activas : completadas;

    const facturas = ordenes.flatMap((oc) => oc.facturas);
    const pagadas = facturas.filter((f) => f.comprobantePago);
    const resumen = {
        facturado: facturas.reduce((acc, f) => acc + f.total, 0),
        pagado: pagadas.reduce((acc, f) => acc + f.total, 0),
        get pendiente() {
            return this.facturado - this.pagado;
        },
    };

    return (
        <div className="min-h-screen bg-base-200">
            <Head title="Prototipo · Portal simplificado" />

            <header className="border-b border-base-300 bg-base-100">
                <div className="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                    <div>
                        <h1 className="text-xl font-semibold">Mis órdenes de compra</h1>
                        <p className="text-sm text-base-content/60">Sube tu factura, adjunta la recepción y consulta tu pago.</p>
                    </div>
                    <span className="badge badge-warning badge-outline">Prototipo · sin backend</span>
                </div>
            </header>

            <main className="mx-auto max-w-7xl p-6">
                <div className="mb-4 grid gap-3 sm:grid-cols-3">
                    <div className="rounded-xl border border-base-300 bg-base-100 px-4 py-3">
                        <div className="text-xs uppercase tracking-wide text-base-content/60">Facturado</div>
                        <div className="mt-0.5 text-xl font-bold">{formatMoney(resumen.facturado)}</div>
                        <div className="text-xs text-base-content/50">
                            {facturas.length} {facturas.length === 1 ? 'factura' : 'facturas'}
                        </div>
                    </div>
                    <div className="rounded-xl border border-success/30 bg-success/10 px-4 py-3">
                        <div className="text-xs uppercase tracking-wide text-base-content/60">Pagado</div>
                        <div className="mt-0.5 text-xl font-bold text-success">{formatMoney(resumen.pagado)}</div>
                        <div className="text-xs text-base-content/50">
                            {pagadas.length} {pagadas.length === 1 ? 'factura pagada' : 'facturas pagadas'}
                        </div>
                    </div>
                    <div className="rounded-xl border border-warning/30 bg-warning/10 px-4 py-3">
                        <div className="text-xs uppercase tracking-wide text-base-content/60">Pendiente de pago</div>
                        <div className="mt-0.5 text-xl font-bold text-warning">{formatMoney(resumen.pendiente)}</div>
                        <div className="text-xs text-base-content/50">
                            {facturas.length - pagadas.length} por cobrar
                        </div>
                    </div>
                </div>

                <div role="tablist" className="tabs tabs-boxed mb-3 w-fit bg-base-100">
                    {(
                        [
                            ['activas', 'Activas', activas.length],
                            ['completadas', 'Completadas', completadas.length],
                        ] as const
                    ).map(([valor, etiqueta, cuantas]) => (
                        <button
                            key={valor}
                            type="button"
                            role="tab"
                            aria-selected={tab === valor}
                            className={`tab gap-2 ${tab === valor ? 'tab-active' : ''}`}
                            onClick={() => setTab(valor)}
                        >
                            {etiqueta}
                            <span className="badge badge-sm">{cuantas}</span>
                        </button>
                    ))}
                </div>

                <div className="overflow-x-auto rounded-2xl border border-base-300 bg-base-100 shadow-sm">
                    <table className="w-full min-w-[1000px] text-sm">
                        <thead className="bg-base-200 text-base-content/70">
                            <tr className="text-left text-xs uppercase tracking-wide">
                                <th className="px-4 py-2.5 font-semibold">Orden de compra</th>
                                <th className="px-4 py-2.5 font-semibold">Factura</th>
                                <th className="px-4 py-2.5 font-semibold">Recepción</th>
                                <th className="px-4 py-2.5 font-semibold">Contrarecibo</th>
                                <th className="px-4 py-2.5 font-semibold">Comprobante de pago</th>
                            </tr>
                        </thead>

                        {visibles.length === 0 && (
                            <tbody>
                                <tr>
                                    <td colSpan={5} className="px-4 py-12 text-center text-base-content/50">
                                        {tab === 'activas'
                                            ? 'No tienes órdenes de compra activas.'
                                            : 'Todavía no tienes órdenes completadas: aquí verás las que ya se facturaron y pagaron por completo.'}
                                    </td>
                                </tr>
                            </tbody>
                        )}

                        {visibles.map((oc) => {
                            return (
                                <tbody key={oc.id} className="border-t-4 border-base-200">
                                    {oc.facturas.map((f, i) => (
                                        <tr key={f.id} className="border-t border-base-300">
                                            {i === 0 && (
                                                <td className="w-[260px] px-4 py-2.5 align-top" rowSpan={oc.facturas.length + 1}>
                                                    <CeldaOrden oc={oc} facturado={facturado(oc)} />
                                                </td>
                                            )}

                                            <td className="px-4 py-2.5">
                                                <div className="flex items-center gap-2 whitespace-nowrap">
                                                    <span className="font-medium">{f.folio}</span>
                                                    <span className="text-xs text-base-content/50">{fmtFecha(f.fecha)}</span>
                                                    <span className="ml-auto flex items-center">
                                                        <DocIcono icon={FileText} titulo="PDF de la factura" nombre={f.pdf} onVer={setDocVisor} />
                                                    </span>
                                                </div>
                                            </td>

                                            <td className="px-4 py-2.5">
                                                {f.recepcion ? (
                                                    <div className="flex items-center gap-1 whitespace-nowrap">
                                                        <DocIcono
                                                            icon={FileCheck2}
                                                            titulo="Comprobante de recepción"
                                                            nombre={f.recepcion.nombre}
                                                            onVer={setDocVisor}
                                                            className="text-success"
                                                        />
                                                        <span className="text-xs text-base-content/60">{fmtFecha(f.recepcion.fecha)}</span>
                                                    </div>
                                                ) : (
                                                    <Button
                                                        size="xs"
                                                        variant="outline"
                                                        onClick={() => setComprobandoFactura({ ocId: oc.id, factura: f })}
                                                    >
                                                        <Upload className="size-3" />
                                                        Subir
                                                    </Button>
                                                )}
                                            </td>

                                            {/* Sin contrarecibo generado no hay pago programado que mostrar. */}
                                            <td className="px-4 py-2.5 whitespace-nowrap">
                                                {f.contrarecibo ? (
                                                    <DocIcono
                                                        icon={ReceiptText}
                                                        titulo="Contrarecibo"
                                                        nombre={f.contrarecibo}
                                                        onVer={setDocVisor}
                                                    />
                                                ) : (
                                                    <span className="text-base-content/40">Por programar</span>
                                                )}
                                            </td>

                                            <td className="px-4 py-2.5">
                                                <DocIcono
                                                    icon={Receipt}
                                                    titulo="Comprobante de pago"
                                                    nombre={f.comprobantePago}
                                                    onVer={setDocVisor}
                                                    className="text-primary"
                                                />
                                            </td>
                                        </tr>
                                    ))}

                                    <tr className="border-t border-base-300">
                                        {oc.facturas.length === 0 && (
                                            <td className="w-[260px] px-4 py-2.5 align-top">
                                                <CeldaOrden oc={oc} facturado={0} />
                                            </td>
                                        )}
                                        <td className="px-4 py-2" colSpan={4}>
                                            {/* Una orden cerrada ya no admite más facturas. */}
                                            {completada(oc) ? (
                                                <span className="text-xs text-success">Orden facturada y pagada por completo.</span>
                                            ) : (
                                                <>
                                                    <Button
                                                        size="xs"
                                                        variant="ghost"
                                                        className="text-primary"
                                                        onClick={() => setFacturandoOc(oc)}
                                                    >
                                                        <FileUp className="size-3" />
                                                        Subir factura
                                                    </Button>
                                                    {oc.facturas.length === 0 && (
                                                        <span className="ml-2 text-xs text-base-content/50">
                                                            Aún no has facturado esta orden.
                                                        </span>
                                                    )}
                                                </>
                                            )}
                                        </td>
                                    </tr>
                                </tbody>
                            );
                        })}
                    </table>
                </div>

                <p className="mt-4 text-xs text-base-content/50">
                    Los archivos no se guardan: el prototipo solo refleja los cambios en pantalla mientras no recargues.
                </p>
            </main>

            {facturandoOc && (
                <SubirFacturaModal
                    oc={facturandoOc}
                    onClose={() => setFacturandoOc(null)}
                    onGuardar={(f) => agregarFactura(facturandoOc.id, f)}
                />
            )}

            {docVisor && <VisorDocumentoModal doc={docVisor} onClose={() => setDocVisor(null)} />}

            {comprobandoFactura && (
                <SubirComprobanteModal
                    factura={comprobandoFactura.factura}
                    onClose={() => setComprobandoFactura(null)}
                    onGuardar={(a) => guardarComprobante(comprobandoFactura.ocId, comprobandoFactura.factura.id, a)}
                />
            )}
        </div>
    );
}
