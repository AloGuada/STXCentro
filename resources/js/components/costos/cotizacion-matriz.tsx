import { router } from '@inertiajs/react';
import { FileTextIcon, PlusIcon, Trash2Icon, XIcon } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { RubroSelector } from '@/components/costos/rubro-selector';
import { Button } from '@/components/ui/button';
import type {
    CostosRequisicion,
    CostosRequisicionCotizacionOpcion,
    CostosRequisicionCotizacionPrecio,
    CostosRequisicionDetalle,
    CostosTipoFiscalPartida,
    CostosTipoMoneda,
    CostosUsoCfdi,
    ObraRubroOption,
    Proveedor,
} from '@/types/models';
import { TIPO_MONEDA_LABELS } from '@/types/models';

type UsoCfdiMin = Pick<CostosUsoCfdi, 'id' | 'clave' | 'descripcion'>;

type ProveedorMin = Pick<Proveedor, 'id' | 'razon_social' | 'nombre_comercial'>;

const fmt = (n: number) =>
    `$${Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const etiquetaOpcion = (o: CostosRequisicionCotizacionOpcion) =>
    o.etiqueta || `Opción ${o.orden}`;

// Columnas fijas (no scrollean en horizontal). Los `left-*` son acumulativos
// según el ancho de las columnas previas (48=12rem, 24=6rem, 16=4rem → 22rem).
// Requieren fondo opaco (bg-base-100 / bg-base-200 en la fila total) para que
// el contenido scrolleado no se transparente debajo.
const COL_FIJA = {
    partida: 'sticky left-0 w-48 min-w-48',
    codigo: 'sticky left-48 w-24 min-w-24',
    req: 'sticky left-72 w-16 min-w-16',
    tipo: 'sticky left-[22rem] w-36 min-w-36 border-r border-base-300',
};

/**
 * Matriz de cotización: filas = partidas, columnas = OPCIONES de proveedor. Un
 * proveedor puede tener varias columnas-opción (ej. distintas marcas). Cada
 * celda captura descripción (opcional) + P. unitario + moneda. El encabezado
 * agrupa las opciones bajo su proveedor (col-span). Permite agregar/quitar
 * proveedores y opciones, editar la etiqueta de cada opción, y clasificar
 * fiscalmente cada partida.
 */
export function CotizacionMatriz({
    requisicion,
    proveedores,
    obraRubros,
    usosCfdi,
    editable,
    puedeEditarPartidas,
}: {
    requisicion: CostosRequisicion;
    proveedores: ProveedorMin[];
    obraRubros: ObraRubroOption[];
    usosCfdi: UsoCfdiMin[];
    editable: boolean;
    puedeEditarPartidas: boolean;
}) {
    const detalles = useMemo(
        () => requisicion.detalles ?? [],
        [requisicion.detalles],
    );

    const proveedoresMap = useMemo(() => {
        const m = new Map<number, ProveedorMin>();
        proveedores.forEach((p) => m.set(p.id, p));
        return m;
    }, [proveedores]);

    const opciones = useMemo(
        () => requisicion.cotizacion_opciones ?? [],
        [requisicion.cotizacion_opciones],
    );

    // Agrupa las opciones por proveedor (ordenado por nombre; opciones por orden).
    const grupos = useMemo(() => {
        const byProv = new Map<number, CostosRequisicionCotizacionOpcion[]>();
        opciones.forEach((o) => {
            const arr = byProv.get(o.proveedor_id) ?? [];
            arr.push(o);
            byProv.set(o.proveedor_id, arr);
        });
        const list = Array.from(byProv.entries()).map(([pid, ops]) => ({
            proveedorId: pid,
            nombre:
                proveedoresMap.get(pid)?.razon_social ??
                ops[0].proveedor?.razon_social ??
                `#${pid}`,
            opciones: [...ops].sort((a, b) => a.orden - b.orden),
        }));
        list.sort((a, b) => a.nombre.localeCompare(b.nombre));
        return list;
    }, [opciones, proveedoresMap]);

    // Columnas aplanadas en el mismo orden que el encabezado; cada una recuerda
    // cuántas opciones tiene su grupo (para permitir/impedir quitar la última).
    const columnas = useMemo(
        () =>
            grupos.flatMap((g) =>
                g.opciones.map((op) => ({ op, groupSize: g.opciones.length })),
            ),
        [grupos],
    );

    const [agregando, setAgregando] = useState(false);

    const disponiblesParaAgregar = proveedores.filter(
        (p) => !grupos.some((g) => g.proveedorId === p.id),
    );

    const agregarProveedor = (proveedorId: number) => {
        router.post(
            `/admin/costos/requisiciones/${requisicion.id}/opciones`,
            { proveedor_id: proveedorId },
            { preserveScroll: true },
        );
        setAgregando(false);
    };

    const agregarOpcion = (proveedorId: number) => {
        router.post(
            `/admin/costos/requisiciones/${requisicion.id}/opciones`,
            { proveedor_id: proveedorId },
            { preserveScroll: true },
        );
    };

    const quitarOpcion = (opcionId: number) => {
        if (
            !confirm(
                '¿Quitar esta opción? Se borrarán sus precios y selecciones.',
            )
        ) {
            return;
        }
        router.delete(`/admin/costos/requisiciones/opciones/${opcionId}`, {
            preserveScroll: true,
        });
    };

    const quitarPartida = (detalleId: number, descripcion: string) => {
        if (
            !confirm(
                `¿Quitar la partida "${descripcion}"? Se borrarán sus cotizaciones y selecciones.`,
            )
        ) {
            return;
        }
        router.delete(`/admin/costos/requisiciones/detalles/${detalleId}`, {
            preserveScroll: true,
        });
    };

    const quitarProveedor = (proveedorId: number, nombre: string) => {
        if (
            !confirm(
                `¿Quitar a ${nombre} de la cotización? Se borrarán todas sus opciones, precios y selecciones.`,
            )
        ) {
            return;
        }
        router.delete(
            `/admin/costos/requisiciones/${requisicion.id}/proveedores/${proveedorId}`,
            { preserveScroll: true },
        );
    };

    const cotizacionDe = (
        d: CostosRequisicionDetalle,
        opcionId: number,
    ): CostosRequisicionCotizacionPrecio | undefined =>
        d.cotizaciones?.find((c) => c.opcion_id === opcionId);

    // Total por opción (PU × cantidad) para el renglón comparativo final.
    const totalesPorOpcion: Record<number, number> = {};
    columnas.forEach(({ op }) => (totalesPorOpcion[op.id] = 0));
    detalles.forEach((d) => {
        columnas.forEach(({ op }) => {
            const c = cotizacionDe(d, op.id);
            if (c)
                totalesPorOpcion[op.id] +=
                    Number(c.precio_unitario) * Number(d.cantidad);
        });
    });
    const totalMin = Math.min(
        ...Object.values(totalesPorOpcion).filter((v) => v > 0),
    );

    // Días de envío por opción: se toma el máximo de sus celdas.
    const diasPorOpcion = new Map<number, number | null>();
    columnas.forEach(({ op }) => {
        const valores = detalles
            .map((d) => cotizacionDe(d, op.id)?.tiempo_entrega_dias)
            .filter((v): v is number => v != null);
        diasPorOpcion.set(op.id, valores.length > 0 ? Math.max(...valores) : null);
    });

    return (
        <div className="space-y-3">
            <div className="rounded-lg border border-base-300 p-3">
                <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h3 className="text-xs tracking-wider text-base-content/60 uppercase">
                        Cotización · partidas × opciones
                    </h3>
                    {editable &&
                        (agregando ? (
                            <div className="flex items-center gap-2">
                                <select
                                    className="select-bordered select w-56 select-xs"
                                    defaultValue=""
                                    onChange={(e) => {
                                        const id = Number(e.target.value);
                                        if (id) {
                                            agregarProveedor(id);
                                        }
                                    }}
                                >
                                    <option value="">
                                        Selecciona proveedor...
                                    </option>
                                    {disponiblesParaAgregar.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.razon_social}
                                        </option>
                                    ))}
                                </select>
                                <Button
                                    variant="outline"
                                    onClick={() => setAgregando(false)}
                                >
                                    Cancelar
                                </Button>
                            </div>
                        ) : (
                            <Button
                                variant="outline"
                                onClick={() => setAgregando(true)}
                                disabled={disponiblesParaAgregar.length === 0}
                            >
                                <PlusIcon className="size-3" /> Agregar
                                proveedor
                            </Button>
                        ))}
                </div>

                <div className="overflow-x-auto">
                    <table className="table table-xs [&_td]:align-top">
                        <thead>
                            <tr>
                                <th rowSpan={2} className={`${COL_FIJA.partida} z-20 bg-base-100`}>Partida</th>
                                <th rowSpan={2} className={`${COL_FIJA.codigo} z-20 bg-base-100`}>Código</th>
                                <th rowSpan={2} className={`${COL_FIJA.req} z-20 bg-base-100 text-right`}>Req.</th>
                                <th rowSpan={2} className={`${COL_FIJA.tipo} z-20 bg-base-100`}>Tipo fiscal</th>
                                {grupos.map((g) => (
                                    <th
                                        key={g.proveedorId}
                                        colSpan={g.opciones.length}
                                        className="border-l border-base-300 text-center"
                                    >
                                        <div className="flex items-center justify-center gap-1">
                                            <span className="truncate" title={g.nombre}>
                                                {g.nombre}
                                            </span>
                                            {editable && (
                                                <>
                                                    <button
                                                        type="button"
                                                        className="btn px-1 btn-ghost btn-xs"
                                                        title="Agregar opción"
                                                        onClick={() => agregarOpcion(g.proveedorId)}
                                                    >
                                                        <PlusIcon className="size-3" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="btn px-1 text-error btn-ghost btn-xs"
                                                        title="Quitar proveedor"
                                                        onClick={() => quitarProveedor(g.proveedorId, g.nombre)}
                                                    >
                                                        <XIcon className="size-3" />
                                                    </button>
                                                </>
                                            )}
                                        </div>
                                    </th>
                                ))}
                                {grupos.length === 0 && (
                                    <th rowSpan={2} className="text-base-content/40">
                                        Agrega un proveedor para cotizar
                                    </th>
                                )}
                            </tr>
                            <tr>
                                {columnas.map(({ op, groupSize }) => (
                                    <th key={op.id} className="border-l border-base-300 text-right">
                                        <EtiquetaOpcion
                                            opcion={op}
                                            puedeQuitar={editable && groupSize > 1}
                                            editable={editable}
                                            onQuitar={() => quitarOpcion(op.id)}
                                        />
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {detalles.map((d) => {
                                const precios = columnas
                                    .map(({ op }) =>
                                        Number(cotizacionDe(d, op.id)?.precio_unitario ?? 0),
                                    )
                                    .filter((n) => n > 0);
                                const min = precios.length > 0 ? Math.min(...precios) : 0;
                                return (
                                    <tr key={d.id}>
                                        <ProductoCelda
                                            key={`${d.id}-${d.descripcion}-${d.codigo_producto ?? ''}`}
                                            detalle={d}
                                            editable={editable}
                                            puedeQuitar={puedeEditarPartidas}
                                            onQuitar={() => quitarPartida(d.id, d.descripcion)}
                                        />
                                        <td className={`${COL_FIJA.req} z-10 bg-base-100 text-right`}>
                                            {Number(d.cantidad).toLocaleString('es-MX')} {d.unidad}
                                        </td>
                                        <td className={`${COL_FIJA.tipo} z-10 bg-base-100`}>
                                            <TipoFiscalSelect detalle={d} editable={editable} />
                                        </td>
                                        {columnas.map(({ op }) => {
                                            const cot = cotizacionDe(d, op.id);
                                            return (
                                                <td key={op.id} className="border-l border-base-300 text-right">
                                                    <CeldaCotizacion
                                                        key={`${cot?.id ?? 'new'}-${cot?.precio_unitario ?? ''}-${cot?.moneda ?? ''}-${cot?.descripcion ?? ''}`}
                                                        requisicionDetalleId={d.id}
                                                        opcionId={op.id}
                                                        cotizacion={cot}
                                                        esMejor={
                                                            Number(cot?.precio_unitario ?? 0) > 0 &&
                                                            Number(cot?.precio_unitario) === min
                                                        }
                                                        editable={editable}
                                                    />
                                                </td>
                                            );
                                        })}
                                        {columnas.length === 0 && <td />}
                                    </tr>
                                );
                            })}
                            {columnas.length > 0 && (
                                <tr className="bg-base-200/50">
                                    <td className={`${COL_FIJA.partida} z-10 bg-base-200 font-semibold`}>Total si todo a uno</td>
                                    <td className={`${COL_FIJA.codigo} z-10 bg-base-200`} />
                                    <td className={`${COL_FIJA.req} z-10 bg-base-200`} />
                                    <td className={`${COL_FIJA.tipo} z-10 bg-base-200`} />
                                    {columnas.map(({ op }) => {
                                        const t = totalesPorOpcion[op.id];
                                        const isMin = t > 0 && t === totalMin;
                                        return (
                                            <td
                                                key={op.id}
                                                className={`border-l border-base-300 text-right font-semibold ${isMin ? 'bg-success/10 text-success' : ''}`}
                                            >
                                                {fmt(t)}
                                            </td>
                                        );
                                    })}
                                </tr>
                            )}
                        </tbody>
                        {columnas.length > 0 && (
                            <tfoot>
                                <tr>
                                    <td colSpan={4} className="sticky left-0 z-10 bg-base-100 text-xs font-medium text-base-content/60">
                                        Días de envío
                                    </td>
                                    {columnas.map(({ op }) => (
                                        <td key={op.id} className="border-l border-base-300 text-right">
                                            <DiasEntregaCelda
                                                key={`${op.id}-${diasPorOpcion.get(op.id) ?? ''}`}
                                                requisicionId={requisicion.id}
                                                opcionId={op.id}
                                                dias={diasPorOpcion.get(op.id) ?? null}
                                                editable={editable}
                                            />
                                        </td>
                                    ))}
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>
            </div>

            {puedeEditarPartidas && (
                <AgregarPartida
                    requisicionId={requisicion.id}
                    presupuestoId={requisicion.presupuesto_id}
                    obraRubros={obraRubros}
                    usosCfdi={usosCfdi}
                />
            )}

            <DocumentosCotizacion
                requisicion={requisicion}
                editable={editable}
            />
        </div>
    );
}

/**
 * Encabezado de una columna-opción: etiqueta editable (onBlur) + quitar opción.
 */
function EtiquetaOpcion({
    opcion,
    puedeQuitar,
    editable,
    onQuitar,
}: {
    opcion: CostosRequisicionCotizacionOpcion;
    puedeQuitar: boolean;
    editable: boolean;
    onQuitar: () => void;
}) {
    const [valor, setValor] = useState(opcion.etiqueta ?? '');

    if (!editable) {
        return (
            <span className="text-xs font-medium">{etiquetaOpcion(opcion)}</span>
        );
    }

    const guardar = () => {
        if ((valor.trim() || null) === (opcion.etiqueta ?? null)) {
            return;
        }
        router.patch(
            `/admin/costos/requisiciones/opciones/${opcion.id}`,
            { etiqueta: valor.trim() || null },
            { preserveScroll: true },
        );
    };

    return (
        <div className="flex w-full items-center gap-1">
            <input
                type="text"
                className="input-bordered input input-xs w-full"
                value={valor}
                placeholder={`Opción ${opcion.orden}`}
                onChange={(e) => setValor(e.target.value)}
                onBlur={guardar}
                title="Etiqueta de la opción (ej. marca)"
            />
            {puedeQuitar && (
                <button
                    type="button"
                    className="btn px-1 text-error btn-ghost btn-xs"
                    title="Quitar opción"
                    onClick={onQuitar}
                >
                    <XIcon className="size-3" />
                </button>
            )}
        </div>
    );
}

function DocumentosCotizacion({
    requisicion,
    editable,
}: {
    requisicion: CostosRequisicion;
    editable: boolean;
}) {
    const [archivo, setArchivo] = useState<File | null>(null);
    const [titulo, setTitulo] = useState('');
    const [subiendo, setSubiendo] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);
    const documentos = requisicion.media ?? [];

    const subir = () => {
        if (!archivo) {
            return;
        }
        setSubiendo(true);
        router.post(
            `/admin/costos/requisiciones/${requisicion.id}/documentos`,
            { documento: archivo, titulo },
            {
                preserveScroll: true,
                forceFormData: true,
                onSuccess: () => {
                    setArchivo(null);
                    setTitulo('');
                    if (inputRef.current) {
                        inputRef.current.value = '';
                    }
                },
                onFinish: () => setSubiendo(false),
            },
        );
    };

    const eliminar = (id: number) => {
        if (confirm('¿Eliminar este documento?')) {
            router.delete(
                `/admin/costos/requisiciones/${requisicion.id}/documentos/${id}`,
                { preserveScroll: true },
            );
        }
    };

    return (
        <div className="rounded-lg border border-base-300 p-3">
            <h3 className="mb-3 text-xs tracking-wider text-base-content/60 uppercase">
                Documentos de cotización (PDF) · información extra
            </h3>

            {documentos.length === 0 ? (
                <p className="mb-3 text-sm text-base-content/50">
                    Aún no hay documentos adjuntos.
                </p>
            ) : (
                <div className="mb-3 space-y-2">
                    {documentos.map((doc) => (
                        <div
                            key={doc.id}
                            className="flex items-center gap-2 rounded border border-base-300 bg-base-200 p-2 text-sm"
                        >
                            <FileTextIcon className="size-4 text-base-content/60" />
                            <a
                                href={`/storage/${doc.path}`}
                                target="_blank"
                                rel="noreferrer"
                                className="font-medium hover:underline"
                            >
                                {doc.descripcion || doc.nombre_original}
                            </a>
                            {editable && (
                                <button
                                    type="button"
                                    className="btn ml-auto text-error btn-ghost btn-xs"
                                    title="Eliminar"
                                    onClick={() => eliminar(doc.id)}
                                >
                                    <XIcon className="size-3" />
                                </button>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {editable && (
                <div className="flex flex-wrap items-end gap-2">
                    <input
                        type="text"
                        className="input-bordered input input-sm w-56"
                        placeholder="Título (opcional)"
                        value={titulo}
                        onChange={(e) => setTitulo(e.target.value)}
                    />
                    <input
                        ref={inputRef}
                        type="file"
                        accept="application/pdf"
                        className="file-input-bordered file-input w-64 file-input-sm"
                        onChange={(e) =>
                            setArchivo(e.target.files?.[0] ?? null)
                        }
                    />
                    <Button onClick={subir} disabled={!archivo || subiendo}>
                        <PlusIcon className="size-3" /> Agregar documento
                    </Button>
                </div>
            )}
        </div>
    );
}

function ProductoCelda({
    detalle,
    editable,
    puedeQuitar,
    onQuitar,
}: {
    detalle: CostosRequisicionDetalle;
    editable: boolean;
    puedeQuitar: boolean;
    onQuitar: () => void;
}) {
    const [descripcion, setDescripcion] = useState(detalle.descripcion);
    const [codigo, setCodigo] = useState(detalle.codigo_producto ?? '');

    const botonQuitar = puedeQuitar ? (
        <button
            type="button"
            className="btn px-1 text-error btn-ghost btn-xs"
            title="Quitar partida"
            onClick={onQuitar}
        >
            <Trash2Icon className="size-3" />
        </button>
    ) : null;

    // Sin producto del catálogo (partidas históricas) o no editable: solo lectura.
    if (!detalle.producto_id || !editable) {
        return (
            <>
                <td className={`${COL_FIJA.partida} z-10 bg-base-100`}>
                    <div className="flex items-start justify-between gap-1">
                        <div className="font-medium">{detalle.descripcion}</div>
                        {botonQuitar}
                    </div>
                </td>
                <td className={`${COL_FIJA.codigo} z-10 bg-base-100 text-[10px] text-base-content/50`}>
                    {detalle.codigo_producto ?? '—'}
                </td>
            </>
        );
    }

    const guardar = () => {
        if (descripcion.trim() === '') return;
        if (
            descripcion === detalle.descripcion &&
            (codigo.trim() || null) === (detalle.codigo_producto ?? null)
        )
            return;
        router.patch(
            `/admin/costos/requisiciones/detalles/${detalle.id}/producto`,
            { descripcion: descripcion.trim(), codigo: codigo.trim() || null },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <td className={`${COL_FIJA.partida} z-10 bg-base-100`}>
                <div className="flex items-start gap-1">
                    <input
                        type="text"
                        className="input-bordered input input-xs w-full font-medium"
                        value={descripcion}
                        onChange={(e) => setDescripcion(e.target.value)}
                        onBlur={guardar}
                        title="Descripción del producto (catálogo)"
                    />
                    {botonQuitar}
                </div>
            </td>
            <td className={`${COL_FIJA.codigo} z-10 bg-base-100`}>
                <input
                    type="text"
                    className="input-bordered input input-xs w-full"
                    value={codigo}
                    placeholder="Código"
                    onChange={(e) => setCodigo(e.target.value)}
                    onBlur={guardar}
                />
            </td>
        </>
    );
}

function DiasEntregaCelda({
    requisicionId,
    opcionId,
    dias,
    editable,
}: {
    requisicionId: number;
    opcionId: number;
    dias: number | null;
    editable: boolean;
}) {
    const [valor, setValor] = useState(dias != null ? String(dias) : '');

    if (!editable) {
        return (
            <span className="text-xs text-base-content/70">
                {dias != null ? `${dias} días` : '—'}
            </span>
        );
    }

    const guardar = () => {
        const n = valor.trim() === '' ? null : Number(valor);
        if (n === dias) return;
        router.post(
            `/admin/costos/requisiciones/${requisicionId}/cotizaciones/tiempo-entrega`,
            { opcion_id: opcionId, tiempo_entrega_dias: n },
            { preserveScroll: true },
        );
    };

    return (
        <div className="flex items-center justify-end gap-1">
            <input
                type="number"
                min={0}
                className="input-bordered input input-xs w-14 text-right"
                value={valor}
                placeholder="—"
                onChange={(e) => setValor(e.target.value)}
                onBlur={guardar}
            />
            <span className="text-[10px] text-base-content/50">días</span>
        </div>
    );
}

function TipoFiscalSelect({
    detalle,
    editable,
}: {
    detalle: CostosRequisicionDetalle;
    editable: boolean;
}) {
    const [tipoFiscal, setTipoFiscal] = useState<CostosTipoFiscalPartida>(
        detalle.tipo_fiscal ?? 'mercancia',
    );

    return (
        <select
            className="select-bordered select w-full select-xs"
            value={tipoFiscal}
            disabled={!editable}
            onChange={(e) => {
                const v = e.target.value as CostosTipoFiscalPartida;
                setTipoFiscal(v);
                router.post(
                    `/admin/costos/requisiciones/detalles/${detalle.id}/clasificacion`,
                    { tipo_fiscal: v },
                    { preserveScroll: true },
                );
            }}
        >
            <option value="mercancia">Mercancía</option>
            <option value="flete">Flete</option>
            <option value="servicio_profesional">Servicio profesional</option>
            <option value="renta">Renta</option>
        </select>
    );
}

function CeldaCotizacion({
    requisicionDetalleId,
    opcionId,
    cotizacion,
    esMejor,
    editable,
}: {
    requisicionDetalleId: number;
    opcionId: number;
    cotizacion?: CostosRequisicionCotizacionPrecio;
    esMejor: boolean;
    editable: boolean;
}) {
    // El estado inicial viene de las props; el componente se remonta (via `key`
    // en el padre) cuando el server devuelve el registro persistido.
    const [precio, setPrecio] = useState(
        cotizacion ? String(cotizacion.precio_unitario) : '',
    );
    const [descripcion, setDescripcion] = useState(cotizacion?.descripcion ?? '');
    const [moneda, setMoneda] = useState<CostosTipoMoneda>(
        cotizacion?.moneda ?? 'mxn',
    );

    const guardar = (monedaOverride?: CostosTipoMoneda) => {
        const p = Number(precio);
        const m = monedaOverride ?? moneda;
        const desc = descripcion.trim() || null;

        // Precio vacío o 0: si existía cotización, eliminarla.
        if (!Number.isFinite(p) || p <= 0) {
            if (cotizacion) {
                router.delete(
                    `/admin/costos/requisiciones/cotizaciones/${cotizacion.id}`,
                    { preserveScroll: true },
                );
            }
            return;
        }

        // Sin cambios respecto a lo persistido.
        if (
            cotizacion &&
            p === Number(cotizacion.precio_unitario) &&
            m === (cotizacion.moneda ?? 'mxn') &&
            desc === (cotizacion.descripcion ?? null)
        ) {
            return;
        }

        router.post(
            '/admin/costos/requisiciones/cotizaciones',
            {
                requisicion_detalle_id: requisicionDetalleId,
                opcion_id: opcionId,
                precio_unitario: p,
                descripcion: desc,
                moneda: m,
            },
            { preserveScroll: true },
        );
    };

    return (
        <div className="flex items-center justify-end gap-1">
            <input
                type="text"
                className="input-bordered input input-xs w-32"
                value={descripcion}
                disabled={!editable}
                placeholder="Descripción (opcional)"
                onChange={(e) => setDescripcion(e.target.value)}
                onBlur={() => guardar()}
                title="Ej. marca / modelo cotizado"
            />
            <input
                type="number"
                step="0.01"
                min={0}
                className={`input-bordered input input-xs w-20 text-right font-semibold ${esMejor ? 'border-success text-success' : ''}`}
                value={precio}
                disabled={!editable}
                placeholder="—"
                onChange={(e) => setPrecio(e.target.value)}
                onBlur={() => guardar()}
            />
            <select
                className="select-bordered select w-16 select-xs"
                value={moneda}
                disabled={!editable}
                onChange={(e) => {
                    const m = e.target.value as CostosTipoMoneda;
                    setMoneda(m);
                    guardar(m);
                }}
                title={TIPO_MONEDA_LABELS[moneda]}
            >
                <option value="mxn">MXN</option>
                <option value="usd">USD</option>
                <option value="eur">EUR</option>
            </select>
        </div>
    );
}

/**
 * Alta rápida de una partida (renglón) desde el tab de cotización. Captura los
 * campos mínimos para poder liberar después: descripción, unidad, cantidad,
 * centro de costos y uso de CFDI.
 */
function AgregarPartida({
    requisicionId,
    presupuestoId,
    obraRubros,
    usosCfdi,
}: {
    requisicionId: number;
    presupuestoId: number | null;
    obraRubros: ObraRubroOption[];
    usosCfdi: UsoCfdiMin[];
}) {
    const defaultUsoId = usosCfdi.find((u) => u.clave === 'G01')?.id ?? '';
    const rubrosDisponibles = presupuestoId
        ? obraRubros.filter((r) => r.presupuesto_id === presupuestoId)
        : obraRubros;

    const [abierto, setAbierto] = useState(false);
    const [descripcion, setDescripcion] = useState('');
    const [unidad, setUnidad] = useState('pza');
    const [cantidad, setCantidad] = useState('1');
    const [obraRubroId, setObraRubroId] = useState<number | ''>('');
    const [usoCfdiId, setUsoCfdiId] = useState<number | ''>(defaultUsoId);
    const [guardando, setGuardando] = useState(false);

    const valido = descripcion.trim() !== '' && Number(cantidad) > 0 && obraRubroId !== '' && usoCfdiId !== '';

    const reset = () => {
        setDescripcion('');
        setUnidad('pza');
        setCantidad('1');
        setObraRubroId('');
        setUsoCfdiId(defaultUsoId);
    };

    const guardar = () => {
        if (!valido) return;
        setGuardando(true);
        router.post(
            `/admin/costos/requisiciones/${requisicionId}/detalles`,
            {
                descripcion: descripcion.trim(),
                unidad: unidad.trim() || 'pza',
                cantidad: Number(cantidad),
                obra_rubro_id: obraRubroId,
                uso_cfdi_id: usoCfdiId,
            },
            {
                preserveScroll: true,
                onSuccess: () => reset(),
                onFinish: () => setGuardando(false),
            },
        );
    };

    if (!abierto) {
        return (
            <div>
                <Button variant="outline" onClick={() => setAbierto(true)}>
                    <PlusIcon className="size-3" /> Agregar partida
                </Button>
            </div>
        );
    }

    return (
        <div className="rounded-lg border border-base-300 p-3">
            <div className="mb-3 flex items-center justify-between">
                <h3 className="text-xs tracking-wider text-base-content/60 uppercase">
                    Nueva partida
                </h3>
                <button type="button" className="btn btn-ghost btn-xs" onClick={() => { setAbierto(false); reset(); }}>
                    Cerrar
                </button>
            </div>
            <div className="grid gap-2 md:grid-cols-[1fr_80px_80px]">
                <div>
                    <label className="text-[10px] uppercase tracking-wider text-base-content/60">Descripción</label>
                    <input
                        type="text"
                        className="input-bordered input input-sm w-full"
                        value={descripcion}
                        onChange={(e) => setDescripcion(e.target.value)}
                        placeholder="Ej. Cemento gris 50kg"
                    />
                </div>
                <div>
                    <label className="text-[10px] uppercase tracking-wider text-base-content/60">Unidad</label>
                    <input
                        type="text"
                        className="input-bordered input input-sm w-full"
                        value={unidad}
                        onChange={(e) => setUnidad(e.target.value)}
                    />
                </div>
                <div>
                    <label className="text-[10px] uppercase tracking-wider text-base-content/60">Cantidad</label>
                    <input
                        type="number"
                        step="0.01"
                        min={0}
                        className="input-bordered input input-sm w-full text-right"
                        value={cantidad}
                        onChange={(e) => setCantidad(e.target.value)}
                    />
                </div>
            </div>
            <div className="mt-2 grid gap-2 md:grid-cols-2">
                <div>
                    <label className="text-[10px] uppercase tracking-wider text-base-content/60">Centro de costos</label>
                    <RubroSelector value={obraRubroId} options={rubrosDisponibles} onChange={setObraRubroId} />
                </div>
                <div>
                    <label className="text-[10px] uppercase tracking-wider text-base-content/60">Uso de CFDI</label>
                    <select
                        className="select-bordered select w-full select-sm"
                        value={usoCfdiId}
                        onChange={(e) => setUsoCfdiId(e.target.value ? Number(e.target.value) : '')}
                    >
                        <option value="">Selecciona...</option>
                        {usosCfdi.map((u) => (
                            <option key={u.id} value={u.id}>{u.clave} - {u.descripcion}</option>
                        ))}
                    </select>
                </div>
            </div>
            <div className="mt-3 flex justify-end">
                <Button onClick={guardar} disabled={!valido || guardando}>
                    <PlusIcon className="size-3" /> Agregar partida
                </Button>
            </div>
        </div>
    );
}
