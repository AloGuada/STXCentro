import { AlertTriangleIcon, PlusIcon, Trash2Icon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { SearchSelect } from '@/components/ui/search-select';
import type { CostosObraRubro, PresupuestoOption } from '@/types/models';

export type CentroCostoRow = {
    presupuesto_id: string;
    obra_rubro_id: string;
    monto: string;
    /** Se preserva del detalle original; la grilla no lo edita. */
    concepto?: string;
};

export const blankCentroCostoRow = (): CentroCostoRow => ({
    presupuesto_id: '',
    obra_rubro_id: '',
    monto: '',
});

export const filaCentroCostoTieneDatos = (d: CentroCostoRow): boolean =>
    Boolean(d.presupuesto_id || d.obra_rubro_id || d.monto);

const fmtMoney = (n: number) => n.toLocaleString('es-MX', { minimumFractionDigits: 2 });

type Props = {
    presupuestos: PresupuestoOption[];
    obraRubros: CostosObraRubro[];
    detalles: CentroCostoRow[];
    onChange: (detalles: CentroCostoRow[]) => void;
    disabled?: boolean;
};

/**
 * Grilla controlada presupuesto → centro de costos → monto, con hint de disponible y
 * auto-append de una fila vacía al final. Reutilizable en formularios de captura
 * y en el modal de reasignación.
 */
export function DetallesCentroCostoGrid({ presupuestos, obraRubros, detalles, onChange, disabled = false }: Props) {
    // Por presupuesto, no por obra: los centros de costos de un proyecto o
    // de una partida no tienen obra_id y por obra nunca aparecerían.
    const rubrosDe = (presupuestoId: string) =>
        presupuestoId ? obraRubros.filter((or) => or.presupuesto_id === Number(presupuestoId)) : [];

    const getDisponible = (obraRubroId: string) => {
        const or = obraRubros.find((r) => r.id === Number(obraRubroId));
        if (!or) {
            return null;
        }
        return Number(or.presupuestado) - Number(or.acumulado);
    };

    const addDetalle = () => onChange([...detalles, blankCentroCostoRow()]);

    const removeDetalle = (index: number) => onChange(detalles.filter((_, i) => i !== index));

    const updateDetalle = (index: number, field: keyof CentroCostoRow, value: string) => {
        const updated = [...detalles];
        updated[index] = { ...updated[index], [field]: value };
        // Cambiar el presupuesto invalida el centro de costos elegido.
        if (field === 'presupuesto_id') {
            updated[index].obra_rubro_id = '';
        }
        // Al usar la última fila, deja una vacía debajo para seguir capturando.
        if (index === updated.length - 1 && filaCentroCostoTieneDatos(updated[index])) {
            updated.push(blankCentroCostoRow());
        }
        onChange(updated);
    };

    const detallesLlenos = detalles.filter(filaCentroCostoTieneDatos);
    const total = detallesLlenos.reduce((sum, d) => sum + (parseFloat(d.monto) || 0), 0);

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <span className="text-sm font-medium">Centros de costos</span>
                <Button type="button" variant="outline" size="sm" onClick={addDetalle} disabled={disabled}>
                    <PlusIcon className="size-4" />
                    Agregar
                </Button>
            </div>

            <div className="overflow-x-auto rounded-lg border border-base-300">
                <table className="table table-sm">
                    <thead>
                        <tr>
                            <th className="w-8 text-center">#</th>
                            <th className="min-w-[160px]">Presupuesto</th>
                            <th className="min-w-[220px]">Centro de Costos</th>
                            <th className="w-40 text-right">Monto</th>
                            <th className="w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {detalles.map((det, index) => {
                            const monto = parseFloat(det.monto) || 0;
                            const disponible = getDisponible(det.obra_rubro_id);
                            const excede = disponible !== null && monto > disponible;

                            return (
                                <tr key={index} className="align-top">
                                    <td className="text-center text-base-content/50">{index + 1}</td>
                                    <td>
                                        <SearchSelect
                                            value={det.presupuesto_id}
                                            onValueChange={(v) => updateDetalle(index, 'presupuesto_id', v)}
                                            placeholder="Buscar presupuesto..."
                                            disabled={disabled}
                                            options={presupuestos.map((p) => ({
                                                value: String(p.id),
                                                label: `${p.label}${p.cerrado ? ' (Cerrado)' : ''}`,
                                            }))}
                                        />
                                    </td>
                                    <td>
                                        <SearchSelect
                                            value={det.obra_rubro_id}
                                            onValueChange={(v) => updateDetalle(index, 'obra_rubro_id', v)}
                                            placeholder={det.presupuesto_id ? 'Buscar centro de costos...' : 'Seleccione presupuesto primero'}
                                            disabled={disabled || !det.presupuesto_id}
                                            options={rubrosDe(det.presupuesto_id).map((or) => ({
                                                value: String(or.id),
                                                label: `${or.rubro?.codigo ?? ''} - ${or.rubro?.descripcion ?? ''}`,
                                                danger: Number(or.presupuestado) - Number(or.acumulado) <= 0,
                                            }))}
                                        />
                                    </td>
                                    <td>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            disabled={disabled}
                                            className={`input-bordered input input-sm w-full text-right ${excede ? 'input-error' : ''}`}
                                            value={det.monto}
                                            onChange={(e) => updateDetalle(index, 'monto', e.target.value)}
                                        />
                                        {disponible !== null && (
                                            <p className={`mt-1 text-xs ${excede ? 'text-error' : 'text-base-content/60'}`}>
                                                Disp: ${fmtMoney(disponible)}
                                                {excede && (
                                                    <span className="ml-1 inline-flex items-center gap-1">
                                                        <AlertTriangleIcon className="size-3" /> Excede
                                                    </span>
                                                )}
                                            </p>
                                        )}
                                    </td>
                                    <td>
                                        <button
                                            type="button"
                                            className="btn text-error btn-ghost btn-xs"
                                            disabled={disabled}
                                            onClick={() => removeDetalle(index)}
                                        >
                                            <Trash2Icon className="size-4" />
                                        </button>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colSpan={3} className="text-right text-base font-semibold">
                                Total
                            </td>
                            <td className="text-right text-base font-semibold whitespace-nowrap">${fmtMoney(total)}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    );
}
