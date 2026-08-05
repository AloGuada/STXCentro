import { Button } from '@/components/ui/button';
import type { Obra, ProdProceso } from '@/types/models';
import { router, usePage } from '@inertiajs/react';
import { HammerIcon } from 'lucide-react';
import { useState } from 'react';

type Props = {
    obra: Obra;
    /** Procesos que la obra paga hoy. */
    procesosDeLaObra: ProdProceso[];
    /** Todo el catálogo de procesos activos, para poder agregar. */
    procesosDisponibles: ProdProceso[];
};

/**
 * Qué se paga como destajo en esta obra. Es lo que decide qué procesos ofrece la
 * captura y a cuáles hay que ponerles tarifa en el grupo de precios.
 */
export function ProcesosDeObra({ obra, procesosDeLaObra, procesosDisponibles }: Props) {
    const [seleccion, setSeleccion] = useState<number[]>(procesosDeLaObra.map((p) => p.id));
    const [guardando, setGuardando] = useState(false);
    const errores = (usePage().props.errors ?? {}) as Record<string, string>;

    const original = procesosDeLaObra.map((p) => p.id).sort().join(',');
    const hayCambios = [...seleccion].sort().join(',') !== original;

    const alternar = (id: number) =>
        setSeleccion((prev) => (prev.includes(id) ? prev.filter((p) => p !== id) : [...prev, id]));

    const guardar = () => {
        setGuardando(true);
        router.put(
            `/admin/prod/obras/${obra.id}/procesos`,
            { procesos: seleccion },
            { preserveScroll: true, onFinish: () => setGuardando(false) },
        );
    };

    return (
        <div className="rounded-box border-base-300 border p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="flex items-center gap-2 font-semibold">
                        <HammerIcon className="size-4" /> Procesos que paga esta obra
                    </h2>
                    <p className="text-base-content/60 text-sm">
                        Cada pieza se paga una vez por proceso. Sólo los marcados aparecen en la captura y piden tarifa
                        en el grupo de precios.
                    </p>
                </div>
                {hayCambios && (
                    <Button size="sm" onClick={guardar} disabled={guardando}>
                        Guardar cambios
                    </Button>
                )}
            </div>

            {errores.procesos && <p className="text-error mt-2 text-sm">{errores.procesos}</p>}

            <div className="mt-3 flex flex-wrap gap-3">
                {procesosDisponibles.length === 0 ? (
                    <p className="text-base-content/50 text-sm">
                        No hay procesos en el catálogo todavía.
                    </p>
                ) : (
                    procesosDisponibles.map((p) => (
                        <label key={p.id} className="flex cursor-pointer items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                className="checkbox checkbox-sm"
                                checked={seleccion.includes(p.id)}
                                onChange={() => alternar(p.id)}
                            />
                            {p.nombre}
                        </label>
                    ))
                )}
            </div>
        </div>
    );
}
