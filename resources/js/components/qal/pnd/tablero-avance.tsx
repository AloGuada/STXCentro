import type { QalPndAvance } from '@/types/models';

/**
 * Comprometidas contra ensayadas, método por método.
 *
 * Los cinco métodos se pintan siempre, incluidos los que no entran en el
 * contrato. Esconderlos dejaría la pantalla diciendo lo mismo tanto si un
 * método no se pactó como si se pactó y nadie lo ha capturado, que es
 * justamente la confusión que este tablero existe para deshacer.
 *
 * El denominador es el **spot**, no la junta: cada renglón del informe es un
 * punto examinado. Contar juntas subestima el volumen ensayado.
 */
export function TableroAvance({ plan }: { plan: QalPndAvance[] }) {
    return (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
            {plan.map((metodo) => (
                <TarjetaMetodo key={metodo.metodo} metodo={metodo} />
            ))}
        </div>
    );
}

function TarjetaMetodo({ metodo }: { metodo: QalPndAvance }) {
    const enContrato = metodo.comprometidas !== null;
    const comprometidas = metodo.comprometidas ?? 0;

    // Con cero comprometidas no hay porcentaje que calcular, pero sí hay algo
    // que decir: si se ensayó, se ensayó de más.
    const porcentaje = comprometidas > 0 ? Math.min(Math.round((metodo.spots / comprometidas) * 100), 100) : 0;
    const rechazo = metodo.spots > 0 ? (metodo.rechazados / metodo.spots) * 100 : 0;

    return (
        <div className={`card border ${enContrato ? 'border-base-300 bg-base-100' : 'border-base-300/50 bg-base-200/40'}`}>
            <div className="card-body gap-2 p-4">
                <div className="flex items-baseline justify-between">
                    <span className={`font-mono text-lg font-semibold ${enContrato ? '' : 'text-base-content/40'}`}>
                        {metodo.metodo}
                    </span>
                    <span className="text-base-content/50 text-xs">{metodo.reportes} informes</span>
                </div>

                <div className={`text-xs ${enContrato ? 'text-base-content/70' : 'text-base-content/40'}`}>
                    {metodo.nombre}
                </div>

                {!enContrato ? (
                    <>
                        <p className="text-base-content/50 mt-1 text-xs">No entra en este contrato.</p>
                        {metodo.spots > 0 && (
                            <p className="text-warning text-xs">
                                Aun así hay {metodo.spots} puntos capturados de este método.
                            </p>
                        )}
                    </>
                ) : (
                    <>
                        <div className="mt-1 flex items-baseline gap-1">
                            <span className="font-mono text-2xl font-semibold">{metodo.spots}</span>
                            <span className="text-base-content/50 font-mono text-sm">/ {comprometidas}</span>
                        </div>

                        <progress
                            className={`progress ${metodo.spots >= comprometidas ? 'progress-success' : 'progress-primary'}`}
                            value={porcentaje}
                            max={100}
                        />

                        <div className="flex items-center justify-between text-xs">
                            <span className="text-base-content/60">
                                {comprometidas > 0 ? `${porcentaje}% del plan` : 'Se pactaron cero'}
                            </span>
                            {metodo.rechazados > 0 && (
                                <span className="badge badge-sm badge-error">
                                    {metodo.rechazados} rech. · {rechazo.toFixed(1)}%
                                </span>
                            )}
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}
