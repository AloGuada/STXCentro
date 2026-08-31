import { Input } from '@/components/ui/input';
import { SearchSelect } from '@/components/ui/search-select';
import type { AlmPartidaBorrador, AlmProductoOpcion } from '@/types/models';
import { PlusIcon, Trash2Icon, TriangleAlertIcon } from 'lucide-react';
import { useMemo } from 'react';

type Props = {
    partidas: AlmPartidaBorrador[];
    onChange: (partidas: AlmPartidaBorrador[]) => void;
    /** Lo que se puede capturar. Lo manda la pantalla, que sabe qué acota su documento. */
    productos: AlmProductoOpcion[];
    /** Las entradas capturan costo; salidas y transferencias no. */
    conCosto?: boolean;
    /** Existencia del producto en el almacén elegido, para avisar de faltantes. */
    disponibleDe?: (productoId: number) => number | null;
    /**
     * `cantidad` captura cuánto entra o sale. `conteo` es para el ajuste: se
     * captura lo que se contó y el sistema calcula la diferencia contra el
     * saldo, que es justo lo que va al kardex.
     */
    modo?: 'cantidad' | 'conteo';
    /**
     * Pedir de más sólo es un error cuando el material va a salir de verdad. La
     * requisición sí puede pedir más de lo que hay: el almacén decide si surte
     * parcial o si hay que comprar.
     */
    avisarFaltante?: boolean;
    /**
     * Sólo la entrada la pide: los productos marcados con "inspección de
     * mantenimiento" no se reciben sin revisar en qué estado llegan. Los demás
     * documentos no preguntan nada.
     */
    pedirVerificacionMantenimiento?: boolean;
};

/**
 * Cuántos productos se ofrecen a la vez. El catálogo crece rápido y una lista
 * larga no se lee: con tres, la forma de llegar es teclear el código o la
 * descripción, no recorrer la lista. El resto se anuncia, no se esconde.
 */
const PRODUCTOS_OFRECIDOS = 3;

export const PARTIDA_VACIA: AlmPartidaBorrador = {
    producto_id: '',
    cantidad: '',
    costo_unitario: '',
    observaciones: '',
    mantenimiento_verificado: false,
};

/** Renglones que piden verificación de mantenimiento y todavía no la tienen. */
export function partidasSinVerificar(
    partidas: AlmPartidaBorrador[],
    productos: AlmProductoOpcion[],
): AlmPartidaBorrador[] {
    return partidas.filter((partida) => {
        const producto = productos.find((p) => String(p.id) === partida.producto_id);

        return producto?.requiere_verificacion === true && !partida.mantenimiento_verificado;
    });
}

const moneda = (n: number) => n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });

/**
 * Tabla editable de renglones, compartida por entrada, salida y transferencia.
 * Los tres documentos capturan lo mismo —qué producto y cuánto— y sólo cambian
 * en si llevan costo y en si hay que cuidar la existencia.
 */
export function CapturadorPartidas({
    partidas,
    onChange,
    productos,
    conCosto = false,
    disponibleDe,
    modo = 'cantidad',
    avisarFaltante = true,
    pedirVerificacionMantenimiento = false,
}: Props) {
    const esConteo = modo === 'conteo';
    const editar = (indice: number, cambio: Partial<AlmPartidaBorrador>) =>
        onChange(partidas.map((p, i) => (i === indice ? { ...p, ...cambio } : p)));

    const agregar = () => onChange([...partidas, { ...PARTIDA_VACIA }]);

    const quitar = (indice: number) => onChange(partidas.filter((_, i) => i !== indice));

    const productoDe = (id: string) => productos.find((p) => String(p.id) === id);

    /**
     * El catálogo entero es una lista larga que nadie recorre de memoria: se
     * teclea el código o un pedazo de la descripción y se elige de lo que queda.
     * Los dos van en la etiqueta porque el buscador filtra sobre ella.
     */
    const opciones = useMemo(
        () =>
            productos.map((p) => ({
                value: String(p.id),
                label: [p.codigo, p.descripcion].filter(Boolean).join(' — '),
            })),
        [productos],
    );

    const importeDe = (p: AlmPartidaBorrador) => Number(p.cantidad || 0) * Number(p.costo_unitario || 0);

    const total = partidas.reduce((suma, p) => suma + importeDe(p), 0);

    const columnas = 5 + (conCosto ? 2 : 0) + (esConteo ? 2 : 0) + (pedirVerificacionMantenimiento ? 1 : 0);

    const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

    return (
        <div className="space-y-3">
            <div className="rounded-box border-base-300 overflow-x-auto border">
                <table className="table table-sm">
                    <thead className="bg-base-200">
                        <tr>
                            <th className="w-[45%]">Producto</th>
                            <th>Unidad</th>
                            {esConteo && <th className="text-right">Sistema</th>}
                            <th className="text-right">{esConteo ? 'Contado' : 'Cantidad'}</th>
                            {esConteo && <th className="text-right">Diferencia</th>}
                            {conCosto && <th className="text-right">Costo unitario</th>}
                            {conCosto && <th className="text-right">Importe</th>}
                            {pedirVerificacionMantenimiento && <th className="text-center">Mtto. verificado</th>}
                            <th>Observaciones</th>
                            <th className="w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {partidas.length === 0 ? (
                            <tr>
                                <td colSpan={columnas} className="text-base-content/50 py-6 text-center">
                                    Sin renglones. Agrega el primero para capturar el documento.
                                </td>
                            </tr>
                        ) : (
                            partidas.map((partida, i) => {
                                const producto = productoDe(partida.producto_id);
                                const disponible = producto && disponibleDe ? disponibleDe(producto.id) : null;
                                // Contar menos de lo que dice el sistema no es un
                                // error: es justo el faltante que el ajuste corrige.
                                const falta =
                                    !esConteo &&
                                    avisarFaltante &&
                                    disponible !== null &&
                                    disponible !== undefined &&
                                    Number(partida.cantidad || 0) > disponible;
                                const diferencia =
                                    disponible === null || disponible === undefined || partida.cantidad === ''
                                        ? null
                                        : Number(partida.cantidad) - disponible;
                                // Lo que pide verificación no se recibe a ojo: sin
                                // el palomeo el renglón queda detenido.
                                const faltaVerificar =
                                    pedirVerificacionMantenimiento &&
                                    producto?.requiere_verificacion === true &&
                                    !partida.mantenimiento_verificado;

                                return (
                                    <tr key={i} className={faltaVerificar ? 'bg-warning/10' : 'hover'}>
                                        <td>
                                            <SearchSelect
                                                options={opciones}
                                                value={partida.producto_id}
                                                onValueChange={(v) => editar(i, { producto_id: v })}
                                                placeholder="Teclea código o descripción..."
                                                inputClassName="input-sm"
                                                maxOptions={PRODUCTOS_OFRECIDOS}
                                            />
                                            {!esConteo && disponible !== null && disponible !== undefined && (
                                                <p
                                                    className={`mt-1 text-xs ${falta ? 'text-error' : 'text-base-content/60'}`}
                                                >
                                                    {falta && <TriangleAlertIcon className="mr-1 inline size-3" />}
                                                    Disponible: {disponible.toLocaleString('es-MX')} {producto?.unidad}
                                                </p>
                                            )}
                                        </td>
                                        <td className="text-base-content/60 font-mono text-xs">
                                            {producto?.unidad ?? '—'}
                                        </td>
                                        {esConteo && (
                                            <td className="text-base-content/60 text-right font-mono">
                                                {disponible === null || disponible === undefined
                                                    ? '—'
                                                    : numero(disponible)}
                                            </td>
                                        )}
                                        <td>
                                            <Input
                                                type="number"
                                                min="0"
                                                step="0.001"
                                                className="input-sm text-right"
                                                value={partida.cantidad}
                                                onChange={(e) => editar(i, { cantidad: e.target.value })}
                                                error={falta}
                                                placeholder="0"
                                            />
                                        </td>
                                        {esConteo && (
                                            <td className="text-right font-mono">
                                                {diferencia === null ? (
                                                    <span className="text-base-content/40">—</span>
                                                ) : (
                                                    <span
                                                        className={
                                                            diferencia < 0
                                                                ? 'text-error font-semibold'
                                                                : diferencia > 0
                                                                  ? 'text-success font-semibold'
                                                                  : 'text-base-content/40'
                                                        }
                                                    >
                                                        {diferencia > 0 ? '+' : ''}
                                                        {numero(diferencia)}
                                                    </span>
                                                )}
                                            </td>
                                        )}
                                        {conCosto && (
                                            <td>
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    step="0.0001"
                                                    className="input-sm text-right"
                                                    value={partida.costo_unitario}
                                                    onChange={(e) => editar(i, { costo_unitario: e.target.value })}
                                                    placeholder="0.00"
                                                />
                                            </td>
                                        )}
                                        {conCosto && (
                                            <td className="text-right font-mono">{moneda(importeDe(partida))}</td>
                                        )}
                                        {pedirVerificacionMantenimiento && (
                                            <td className="text-center">
                                                {producto?.requiere_verificacion ? (
                                                    <input
                                                        type="checkbox"
                                                        className={`checkbox checkbox-sm ${faltaVerificar ? 'checkbox-warning' : ''}`}
                                                        checked={partida.mantenimiento_verificado}
                                                        onChange={(e) =>
                                                            editar(i, { mantenimiento_verificado: e.target.checked })
                                                        }
                                                        aria-label={`Mantenimiento verificado de ${producto.codigo}`}
                                                    />
                                                ) : (
                                                    <span className="text-base-content/30">—</span>
                                                )}
                                            </td>
                                        )}
                                        <td>
                                            <Input
                                                className="input-sm"
                                                value={partida.observaciones}
                                                onChange={(e) => editar(i, { observaciones: e.target.value })}
                                                placeholder="Opcional"
                                            />
                                        </td>
                                        <td>
                                            <button
                                                type="button"
                                                className="btn btn-ghost btn-xs text-error"
                                                onClick={() => quitar(i)}
                                                aria-label="Quitar renglón"
                                            >
                                                <Trash2Icon className="size-4" />
                                            </button>
                                        </td>
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                    {conCosto && partidas.length > 0 && (
                        <tfoot className="bg-base-200">
                            <tr>
                                <td colSpan={4} className="text-right font-medium">
                                    Total
                                </td>
                                <td className="text-right font-mono font-semibold">{moneda(total)}</td>
                                <td colSpan={pedirVerificacionMantenimiento ? 3 : 2}></td>
                            </tr>
                        </tfoot>
                    )}
                </table>
            </div>

            <button type="button" className="btn btn-sm btn-outline" onClick={agregar}>
                <PlusIcon className="size-4" />
                Agregar renglón
            </button>
        </div>
    );
}
