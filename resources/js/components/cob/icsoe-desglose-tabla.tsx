import { calcularTotales, moRealDelMes, nombreDelPeriodo } from '@/components/cob/icsoe-calculos';
import { formatearMXN } from '@/components/cob/money-display';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { CobIcsoeMes, CobIcsoeSeguimiento } from '@/types/models';
import { router } from '@inertiajs/react';
import { AlertTriangleIcon, SaveIcon } from 'lucide-react';
import { useEffect, useState } from 'react';

type Props = {
    seguimiento: CobIcsoeSeguimiento;
    meses: CobIcsoeMes[];
    puedeEditar: boolean;
};

/**
 * Captura de días cotizados por mes. Se guarda por lote: son 12–36 renglones
 * que se llenan de corrido y un PUT por celda recalcularía los totales N veces.
 */
export default function IcsoeDesgloseTabla({ seguimiento, meses, puedeEditar }: Props) {
    const [filas, setFilas] = useState<CobIcsoeMes[]>(meses);
    const [sucio, setSucio] = useState(false);
    const [guardando, setGuardando] = useState(false);

    useEffect(() => {
        setFilas(meses);
        setSucio(false);
    }, [meses]);

    const actualizar = (id: number, campo: 'dias_cotizados' | 'sbc_aplicado', valor: string) => {
        setFilas((previas) => previas.map((fila) => (fila.id === id ? { ...fila, [campo]: valor } : fila)));
        setSucio(true);
    };

    const guardar = () => {
        setGuardando(true);
        router.put(
            `/admin/cob/icsoe/${seguimiento.id}/meses`,
            {
                meses: filas.map((fila) => ({
                    id: fila.id,
                    dias_cotizados: fila.dias_cotizados === '' ? 0 : fila.dias_cotizados,
                    sbc_aplicado: fila.sbc_aplicado === '' ? 0 : fila.sbc_aplicado,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => setSucio(false),
                onFinish: () => setGuardando(false),
            },
        );
    };

    const totales = calcularTotales(seguimiento, filas);

    return (
        <div className="space-y-4">
            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div className="rounded-box border border-base-300 border-l-4 border-l-info p-4">
                    <p className="text-xs font-medium uppercase tracking-wider text-base-content/60">Meta estimada IMSS</p>
                    <p className="mt-1 text-2xl font-bold">{formatearMXN(totales.moEstimadaTotal)}</p>
                    <p className="text-sm text-base-content/60">Mano de obra total a comprobar</p>
                </div>
                <div className="rounded-box border border-base-300 border-l-4 border-l-success p-4">
                    <p className="text-xs font-medium uppercase tracking-wider text-base-content/60">M.O. real acumulada</p>
                    <p className="mt-1 text-2xl font-bold">{formatearMXN(totales.moRealTotal)}</p>
                    <p className="text-sm text-base-content/60">Días cotizados × SBC</p>
                </div>
                <div
                    className={`rounded-box border border-base-300 border-l-4 p-4 ${
                        totales.diferencia > 0 ? 'border-l-error' : 'border-l-base-300'
                    }`}
                >
                    <p className="text-xs font-medium uppercase tracking-wider text-base-content/60">Riesgo en cuotas</p>
                    <p className={`mt-1 text-2xl font-bold ${totales.diferencia > 0 ? 'text-error' : ''}`}>
                        {formatearMXN(totales.montoRiesgo)}
                    </p>
                    <p className="text-sm text-base-content/60">
                        Sobre la diferencia, al {(26 + Number(seguimiento.prima_riesgo)).toFixed(5)}%
                    </p>
                </div>
            </div>

            {puedeEditar && (
                <div className="flex items-center justify-end gap-3">
                    {sucio && <span className="text-sm text-warning">Hay cambios sin guardar</span>}
                    <Button type="button" onClick={guardar} disabled={!sucio || guardando}>
                        <SaveIcon className="size-4" />
                        Guardar días cotizados
                    </Button>
                </div>
            )}

            <div className="overflow-x-auto rounded-box border border-base-300">
                <table className="table table-sm">
                    <thead>
                        <tr>
                            <th>Periodo</th>
                            <th className="text-center">Días del mes</th>
                            <th className="text-right">M.O. esperada</th>
                            <th className="text-center">SBC catálogo</th>
                            <th className="w-32 text-center">SBC aplicado</th>
                            <th className="w-32 text-center">Días cotizados</th>
                            <th className="text-right">M.O. real</th>
                            <th className="text-right">Diferencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filas.length === 0 ? (
                            <tr>
                                <td colSpan={8} className="py-8 text-center text-base-content/60">
                                    El periodo no genera meses. Revisa las fechas de inicio y término.
                                </td>
                            </tr>
                        ) : (
                            filas.map((mes) => {
                                const real = moRealDelMes(mes);
                                const diferencia = Number(mes.mo_estimada) - real;
                                const sbcDistinto = Number(mes.sbc_aplicado) !== Number(mes.sbc);

                                return (
                                    <tr key={mes.id} className={mes.fuera_de_rango ? 'opacity-60' : 'hover'}>
                                        <td className="font-medium">
                                            {nombreDelPeriodo(mes)}
                                            {mes.fuera_de_rango && (
                                                <span className="ml-2 badge badge-ghost badge-sm">fuera del periodo</span>
                                            )}
                                        </td>
                                        <td className="text-center">{mes.dias_proyecto}</td>
                                        <td className="text-right">{formatearMXN(Number(mes.mo_estimada))}</td>
                                        <td className="text-center font-mono text-sm text-base-content/60">
                                            {Number(mes.sbc).toFixed(2)}
                                        </td>
                                        <td>
                                            <div className="flex items-center gap-1">
                                                <Input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    className="input-sm text-center"
                                                    value={mes.sbc_aplicado}
                                                    disabled={!puedeEditar}
                                                    onChange={(e) => actualizar(mes.id, 'sbc_aplicado', e.target.value)}
                                                />
                                                {sbcDistinto && (
                                                    <span
                                                        className="tooltip text-warning"
                                                        data-tip="No coincide con el SBC del catálogo"
                                                    >
                                                        <AlertTriangleIcon className="size-4" />
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td>
                                            <Input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                className="input-sm text-center"
                                                value={mes.dias_cotizados}
                                                disabled={!puedeEditar}
                                                onChange={(e) => actualizar(mes.id, 'dias_cotizados', e.target.value)}
                                            />
                                        </td>
                                        <td className="text-right font-medium text-success">{formatearMXN(real)}</td>
                                        <td className={`text-right font-medium ${diferencia > 0 ? 'text-error' : 'text-base-content/50'}`}>
                                            {formatearMXN(diferencia)}
                                        </td>
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
