/**
 * El aviso de que Formularios está limitado al avance de producción.
 *
 * Con el interruptor encendido en Configuración de Calidad, sólo se escanean y
 * registran las piezas que Producción programó (el plan cerrado de la semana,
 * lo atrasado y lo que ya tiene inspección en esa fase). Cada fase se mide
 * contra su propio plan, y sin él cerrado no entra nada de esa fase: el aviso
 * dice cuál está abierta y cuál no, para que el inspector no lo descubra
 * escaneando.
 */

export type FiltroAvance = {
    obra: {
        semana: string;
        /** Por fase ('2ª', '3ª'): si el plan de esta semana está cerrado. */
        fases: Record<string, boolean>;
    } | null;
} | null;

const FASES: [string, string][] = [
    ['2ª', 'Armado y soldado (2ª)'],
    ['3ª', 'Pintura (3ª)'],
];

export function AvisoAvance({ filtro }: { filtro: FiltroAvance }) {
    if (!filtro) {
        return null;
    }

    const estado = filtro.obra;
    const algunaCerrada = estado !== null && FASES.some(([fase]) => estado.fases[fase]);

    return (
        <div
            className={`mb-[14px] rounded-xl border px-4 py-3 text-sm ${
                estado === null || algunaCerrada ? 'border-info/40 bg-info/10' : 'border-warning/50 bg-warning/10'
            }`}
        >
            <div className="font-semibold">
                Sólo piezas del avance de producción{estado && <> · semana {estado.semana}</>}
            </div>

            {estado === null ? (
                <div className="mt-1 text-base-content/70">Elige la obra para ver qué fases tienen su plan cerrado.</div>
            ) : (
                <ul className="mt-1 space-y-0.5">
                    {FASES.map(([fase, nombre]) => (
                        <li key={fase} className="flex flex-wrap items-center gap-2">
                            <span className={`badge badge-sm ${estado.fases[fase] ? 'badge-success' : 'badge-warning'}`}>
                                {estado.fases[fase] ? 'Plan cerrado' : 'Sin plan cerrado'}
                            </span>
                            <span>{nombre}</span>
                            {!estado.fases[fase] && (
                                <span className="text-base-content/60">— no se puede registrar ninguna pieza</span>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            <div className="mt-1 text-xs text-base-content/60">
                Entra lo programado en un plan cerrado de esta semana o de antes, y lo que ya tiene inspección en esa
                fase. La 1ª transformación no se limita.
            </div>
        </div>
    );
}
