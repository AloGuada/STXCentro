import type { ProdLiquidacion, ProdLiquidacionDetalle, ProdLiquidacionEmpleado, ProdGrupoTrabajo, Usuario } from '@/types/models';

export type LiquidacionFull = ProdLiquidacion & {
    grupo_trabajo?: ProdGrupoTrabajo;
    generador?: Usuario;
    detalles?: ProdLiquidacionDetalle[];
    empleados?: ProdLiquidacionEmpleado[];
};

const num = (n: number, decimals = 2) =>
    Number(n).toLocaleString('es-MX', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });

export function LiquidacionCard({ liquidacion }: { liquidacion: LiquidacionFull }) {
    return (
        <div className="rounded-box border border-base-300 overflow-hidden">
            <div className="flex items-center justify-between border-b border-base-300 bg-base-200 px-4 py-2">
                <span className="font-semibold">{liquidacion.grupo_trabajo?.descripcion ?? 'Grupo'}</span>
                <span className="text-base-content/60 text-xs">
                    Generado {liquidacion.generado_en}
                    {liquidacion.generador?.name ? ` · ${liquidacion.generador.name}` : ''}
                </span>
            </div>

            <div className="space-y-4 p-4">
                <div>
                    <div className="mb-1 text-sm font-medium">Detalle de producción</div>
                    <div className="overflow-x-auto">
                        <table className="table table-sm">
                            <thead>
                                <tr>
                                    <th>Pieza</th>
                                    <th className="text-right">Cantidad</th>
                                    <th className="text-right">Kilos</th>
                                    <th className="text-right">$/kg</th>
                                    <th className="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(liquidacion.detalles ?? []).length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="text-base-content/50 py-4 text-center">
                                            Sin producción
                                        </td>
                                    </tr>
                                ) : (
                                    liquidacion.detalles?.map((d) => (
                                        <tr key={d.id} className="hover">
                                            <td>
                                                {d.concepto ? (
                                                    <>
                                                        <span className="font-medium">{d.concepto.marca}</span>{' '}
                                                        <span className="text-base-content/60">{d.concepto.descripcion}</span>
                                                    </>
                                                ) : (
                                                    <span className="text-base-content/60">Concepto #{d.concepto_id}</span>
                                                )}
                                            </td>
                                            <td className="text-right font-mono">{d.cantidad}</td>
                                            <td className="text-right font-mono">{num(d.kilos, 3)}</td>
                                            <td className="text-right font-mono">{num(d.precio_kilo_aplicado, 4)}</td>
                                            <td className="text-right font-mono">${num(d.total)}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-1 text-sm">
                        <div className="flex justify-between">
                            <span className="text-base-content/70">Kilos</span>
                            <span className="font-mono">{num(liquidacion.total_kilos, 3)}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-base-content/70">Producción</span>
                            <span className="font-mono">${num(liquidacion.total_produccion)}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-base-content/70">Extras</span>
                            <span className="font-mono">${num(liquidacion.total_extras)}</span>
                        </div>
                        <div className="flex justify-between border-t border-base-300 pt-1 font-semibold">
                            <span>Total final</span>
                            <span className="font-mono">${num(liquidacion.total_final)}</span>
                        </div>
                    </div>

                    <div>
                        <div className="mb-1 text-sm font-medium">Distribución a empleados</div>
                        <div className="space-y-1">
                            {(liquidacion.empleados ?? []).length === 0 ? (
                                <p className="text-base-content/50 text-sm">Sin empleados en el grupo</p>
                            ) : (
                                liquidacion.empleados?.map((emp: ProdLiquidacionEmpleado) => (
                                    <div key={emp.id} className="flex items-center justify-between text-sm">
                                        <span>
                                            {emp.nombre}
                                            <span className="text-base-content/50"> ({emp.no_empleado || 's/n'})</span>
                                        </span>
                                        <span className="font-mono">
                                            {Number(emp.porcentaje).toFixed(2)}% = ${num(emp.monto_asignado)}
                                        </span>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
