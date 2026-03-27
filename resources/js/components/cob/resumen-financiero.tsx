import type { Obra } from '@/types/models';
import type { ResumenFinanciero } from './calculos';
import { formatearMXN } from './money-display';

type Props = {
    obra: Obra;
    resumen: ResumenFinanciero;
};

function ProgressBar({ porcentaje, color }: { porcentaje: number; color: string }) {
    const clamped = Math.min(Math.max(porcentaje, 0), 100);

    return (
        <div className="w-full bg-base-300 rounded-full h-2">
            <div
                className={`h-2 rounded-full ${color}`}
                style={{ width: `${clamped}%` }}
            />
        </div>
    );
}

export function ResumenFinancieroCard({ obra, resumen }: Props) {
    const pctFacturado = resumen.presupuestoEjecutar > 0
        ? (resumen.totalFacturado / resumen.presupuestoEjecutar) * 100
        : 0;

    const pctCobrado = resumen.presupuestoEjecutar > 0
        ? (resumen.totalCobrado / resumen.presupuestoEjecutar) * 100
        : 0;

    const anticipoPct = Number(obra.anticipo ?? 0);
    const anticipoMonto = resumen.presupuestoEjecutar * (anticipoPct / 100);

    return (
        <div className="card bg-base-200 p-6">
            <div className="mb-4 border-b border-base-300 pb-4">
                <h3 className="text-lg font-bold">{obra.descripcion}</h3>
                <p className="text-sm opacity-70">OP: {obra.no}</p>
            </div>

            <div className="grid grid-cols-2 gap-x-8 gap-y-4 lg:grid-cols-3">
                <div>
                    <div className="text-sm opacity-70">Presupuesto Base</div>
                    <div className="text-lg font-bold">{formatearMXN(resumen.presupuestoEjecutar)}</div>
                    {resumen.tieneComparativoCualquiera && (() => {
                        const diferencia = resumen.presupuestoEjecutar - (resumen.presupuestoPartidas + resumen.partidasAdicionales);
                        const esReferencia = resumen.tipoContrato !== 'precio_unitario' || !resumen.tieneComparativos;
                        return (
                            <div className={`text-xs mt-1 ${diferencia >= 0 ? 'text-success' : 'text-error'}`}>
                                Ajuste{esReferencia ? ' (ref.)' : ''}: {diferencia >= 0 ? '+' : ''}{formatearMXN(diferencia)}
                            </div>
                        );
                    })()}
                </div>

                <div>
                    <div className="text-sm opacity-70">Facturado</div>
                    <div className="text-lg font-bold text-info">{formatearMXN(resumen.totalFacturado)}</div>
                    <ProgressBar porcentaje={pctFacturado} color="bg-info" />
                    <div className="text-xs opacity-50 mt-1">{pctFacturado.toFixed(1)}%</div>
                </div>

                <div>
                    <div className="text-sm opacity-70">Cobrado</div>
                    <div className="text-lg font-bold text-success">{formatearMXN(resumen.totalCobrado)}</div>
                    <ProgressBar porcentaje={pctCobrado} color="bg-success" />
                    <div className="text-xs opacity-50 mt-1">{pctCobrado.toFixed(1)}%</div>
                </div>

                <div>
                    <div className="text-sm opacity-70">Anticipo ({anticipoPct.toFixed(4)}%)</div>
                    <div className="text-lg font-bold">{formatearMXN(anticipoMonto)}</div>
                </div>
                <div>
                    <div className="text-sm opacity-70">Por Facturar</div>
                    <div className="text-lg font-bold">{formatearMXN(resumen.porFacturar)}</div>
                </div>


                <div>
                    <div className="text-sm opacity-70">Por Cobrar</div>
                    <div className="text-lg font-bold text-warning">{formatearMXN(resumen.porCobrar)}</div>
                    {resumen.totalDeducciones > 0 && (
                        <div className="text-xs text-error mt-1">
                            Deducciones: {formatearMXN(resumen.totalDeducciones)}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
