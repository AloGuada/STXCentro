import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import { PRODUCTOS_DEMO } from '@/lib/alm/demo';
import type { AlmPartidaBorrador } from '@/types/models';
import { PlusIcon, Trash2Icon, TriangleAlertIcon } from 'lucide-react';

type Props = {
    partidas: AlmPartidaBorrador[];
    onChange: (partidas: AlmPartidaBorrador[]) => void;
    /** Las entradas capturan costo; salidas y transferencias no. */
    conCosto?: boolean;
    /** Existencia del producto en el almacén elegido, para avisar de faltantes. */
    disponibleDe?: (codigoProducto: string) => number | null;
};

export const PARTIDA_VACIA: AlmPartidaBorrador = {
    producto_id: '',
    cantidad: '',
    costo_unitario: '',
    observaciones: '',
};

const moneda = (n: number) => n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });

/**
 * Tabla editable de renglones, compartida por entrada, salida y transferencia.
 * Los tres documentos capturan lo mismo —qué producto y cuánto— y sólo cambian
 * en si llevan costo y en si hay que cuidar la existencia.
 */
export function CapturadorPartidas({ partidas, onChange, conCosto = false, disponibleDe }: Props) {
    const editar = (indice: number, cambio: Partial<AlmPartidaBorrador>) =>
        onChange(partidas.map((p, i) => (i === indice ? { ...p, ...cambio } : p)));

    const agregar = () => onChange([...partidas, { ...PARTIDA_VACIA }]);

    const quitar = (indice: number) => onChange(partidas.filter((_, i) => i !== indice));

    const productoDe = (id: string) => PRODUCTOS_DEMO.find((p) => String(p.id) === id);

    const importeDe = (p: AlmPartidaBorrador) => Number(p.cantidad || 0) * Number(p.costo_unitario || 0);

    const total = partidas.reduce((suma, p) => suma + importeDe(p), 0);

    return (
        <div className="space-y-3">
            <div className="rounded-box border-base-300 overflow-x-auto border">
                <table className="table table-sm">
                    <thead className="bg-base-200">
                        <tr>
                            <th className="w-[45%]">Producto</th>
                            <th>Unidad</th>
                            <th className="text-right">Cantidad</th>
                            {conCosto && <th className="text-right">Costo unitario</th>}
                            {conCosto && <th className="text-right">Importe</th>}
                            <th>Observaciones</th>
                            <th className="w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {partidas.length === 0 ? (
                            <tr>
                                <td colSpan={conCosto ? 7 : 5} className="text-base-content/50 py-6 text-center">
                                    Sin renglones. Agrega el primero para capturar el documento.
                                </td>
                            </tr>
                        ) : (
                            partidas.map((partida, i) => {
                                const producto = productoDe(partida.producto_id);
                                const disponible = producto && disponibleDe ? disponibleDe(producto.codigo) : null;
                                const falta =
                                    disponible !== null &&
                                    disponible !== undefined &&
                                    Number(partida.cantidad || 0) > disponible;

                                return (
                                    <tr key={i} className="hover">
                                        <td>
                                            <Select
                                                value={partida.producto_id}
                                                onValueChange={(v) => editar(i, { producto_id: v })}
                                                placeholder="Selecciona producto"
                                                className="select-sm"
                                            >
                                                {PRODUCTOS_DEMO.map((p) => (
                                                    <SelectItem key={p.id} value={String(p.id)}>
                                                        {p.codigo} — {p.descripcion}
                                                    </SelectItem>
                                                ))}
                                            </Select>
                                            {disponible !== null && disponible !== undefined && (
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
                                <td colSpan={2}></td>
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
