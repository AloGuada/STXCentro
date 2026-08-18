import { FileTextIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { EXISTENCIAS_CON_ACTIVOS_DEMO, existenciasDe, valorDeExistencias } from '@/lib/alm/demo';
import type { AlmExistenciaDemo } from '@/types/models';

type Props = {
    /**
     * El almacén del reporte. Sin él sale el consolidado de la empresa, que es
     * el que se pide para valuar el inventario completo.
     */
    almacen?: { clave: string; nombre: string } | null;
    etiqueta?: string;
    /** Botón discreto para el renglón de una tabla; suelto va normal. */
    compacto?: boolean;
};

const moneda = (n: number) => n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
const cantidad = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

/**
 * El inventario valuado: qué hay, a cuánto sale cada cosa y cuánto vale todo.
 *
 * Material medido y piezas con serie van en la misma lista —la pulidora
 * prestada sigue siendo del almacén— porque lo que responde el reporte es
 * cuánto vale lo que está a cargo de esa bodega, no cuánto se puede entregar
 * hoy.
 *
 * Mientras el módulo sea maqueta el botón no descarga nada: enseña el reporte
 * que va a imprimirse, con los datos de ejemplo. El PDF necesita el controlador
 * y la plantilla de `resources/views/pdf/alm/`, que todavía no existen.
 */
export function BotonReporteExistencias({ almacen = null, etiqueta = 'PDF', compacto = false }: Props) {
    const [abierto, setAbierto] = useState(false);

    const filas = useMemo<AlmExistenciaDemo[]>(() => {
        const lista = almacen ? existenciasDe(almacen.clave) : [...EXISTENCIAS_CON_ACTIVOS_DEMO];

        // Ordenado como se lee el papel: por bodega y luego por código.
        return lista.sort((a, b) => a.almacen.localeCompare(b.almacen) || a.producto.localeCompare(b.producto));
    }, [almacen]);

    const valorTotal = valorDeExistencias(filas);
    const titulo = almacen
        ? `Existencias de ${almacen.clave} — ${almacen.nombre}`
        : 'Existencias de todos los almacenes';

    return (
        <>
            <button
                type="button"
                className={compacto ? 'btn btn-ghost btn-xs' : 'btn btn-outline btn-sm'}
                onClick={(e) => {
                    // Vive dentro del enlace del renglón: sin esto la tabla
                    // navega a la edición del almacén en lugar de abrir.
                    e.stopPropagation();
                    e.preventDefault();
                    setAbierto(true);
                }}
                title={almacen ? `Inventario valuado de ${almacen.clave}` : 'Inventario valuado de todos los almacenes'}
                aria-label={titulo}
            >
                <FileTextIcon className="size-3.5" />
                {etiqueta}
            </button>

            {abierto && (
                <dialog className="modal modal-open">
                    <div className="modal-box max-w-4xl">
                        <h3 className="text-lg font-bold">{titulo}</h3>

                        <p className="text-base-content/60 mt-1 text-sm">
                            Inventario valuado a costo promedio. Incluye las piezas con serie: la pulidora prestada
                            sigue siendo del almacén.
                        </p>

                        <div className="rounded-box border-base-300 mt-4 max-h-[26rem] overflow-auto border">
                            <table className="table table-sm table-pin-rows">
                                <thead className="bg-base-200">
                                    <tr>
                                        {!almacen && <th>Almacén</th>}
                                        <th>Código</th>
                                        <th>Descripción</th>
                                        <th className="text-right">Existencia</th>
                                        <th className="text-right">Costo prom.</th>
                                        <th className="text-right">Valor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filas.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={almacen ? 5 : 6}
                                                className="text-base-content/50 py-6 text-center"
                                            >
                                                Este almacén todavía no tiene existencias
                                            </td>
                                        </tr>
                                    ) : (
                                        filas.map((e) => (
                                            <tr key={`${e.almacen}-${e.producto}-${e.ubicacion_id ?? 'sin'}`}>
                                                {!almacen && (
                                                    <td>
                                                        <span className="badge badge-sm badge-ghost font-mono">
                                                            {e.almacen}
                                                        </span>
                                                    </td>
                                                )}
                                                <td className="font-mono text-xs">{e.producto}</td>
                                                <td>{e.descripcion}</td>
                                                <td className="text-right font-mono">
                                                    {cantidad(e.cantidad)}
                                                    <span className="text-base-content/40"> {e.unidad}</span>
                                                </td>
                                                <td className="text-right font-mono">{moneda(e.costo_promedio)}</td>
                                                <td className="text-right font-mono">
                                                    {moneda(e.cantidad * e.costo_promedio)}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                                {filas.length > 0 && (
                                    <tfoot className="bg-base-200">
                                        <tr>
                                            <th colSpan={almacen ? 4 : 5} className="text-right">
                                                {filas.length} renglón(es) · Valor del inventario
                                            </th>
                                            <th className="text-right font-mono">{moneda(valorTotal)}</th>
                                        </tr>
                                    </tfoot>
                                )}
                            </table>
                        </div>

                        <div className="alert alert-warning mt-4">
                            <span>Datos de ejemplo: la maqueta todavía no genera el {etiqueta}.</span>
                        </div>

                        <div className="modal-action">
                            <Button type="button" variant="outline" onClick={() => setAbierto(false)}>
                                Cerrar
                            </Button>
                            <Button type="button" disabled title="Falta el controlador y la plantilla del PDF">
                                Descargar {etiqueta}
                            </Button>
                        </div>
                    </div>
                    <div className="modal-backdrop" onClick={() => setAbierto(false)}></div>
                </dialog>
            )}
        </>
    );
}
