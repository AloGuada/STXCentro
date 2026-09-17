import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { QalPndAvance } from '@/types/models';
import { useForm } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

type Props = {
    obraId: number;
    plan: QalPndAvance[];
    nota: string | null;
    abierto: boolean;
    onCerrar: () => void;
};

type Fila = {
    metodo: string;
    pactado: boolean;
    comprometidas: number | string;
};

/**
 * Lo que se pactó con el cliente: cuántas pruebas por método y por qué.
 *
 * **La palomita no es un cero.** Un método sin palomita no entra en el
 * contrato y desaparece del plan; un método con palomita y un cero significa
 * que se pactaron cero pruebas, que es un compromiso distinto y se puede
 * incumplir. Por eso la casilla y el número son dos controles y no uno.
 */
export function PlanDialog({ obraId, plan, nota, abierto, onCerrar }: Props) {
    const dialogo = useRef<HTMLDialogElement>(null);

    const { data, setData, put, processing, errors } = useForm<{ nota: string; plan: Fila[] }>({
        nota: nota ?? '',
        plan: plan.map((metodo) => ({
            metodo: metodo.metodo,
            pactado: metodo.comprometidas !== null,
            comprometidas: metodo.comprometidas ?? '',
        })),
    });

    useEffect(() => {
        const elemento = dialogo.current;

        if (!elemento) {
            return;
        }

        if (abierto && !elemento.open) {
            elemento.showModal();
        } else if (!abierto && elemento.open) {
            elemento.close();
        }
    }, [abierto]);

    const cambiar = (indice: number, cambios: Partial<Fila>) => {
        setData(
            'plan',
            data.plan.map((fila, i) => (i === indice ? { ...fila, ...cambios } : fila)),
        );
    };

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        put(`/admin/calidad/pnd/plan/${obraId}`, { preserveScroll: true, onSuccess: onCerrar });
    };

    return (
        <dialog ref={dialogo} className="modal" onClose={onCerrar}>
            <div className="modal-box max-w-2xl">
                <h3 className="text-lg font-semibold">Plan de PND comprometido</h3>
                <p className="text-base-content/60 mt-1 text-sm">
                    El denominador del avance. Se pacta por método: comprometer 60 ensayos y hacer 60 del más barato
                    cumple el número y no cumple el contrato.
                </p>

                <form onSubmit={enviar} className="mt-4 space-y-4">
                    <div className="space-y-2">
                        {data.plan.map((fila, indice) => {
                            const metodo = plan[indice];

                            return (
                                <div key={fila.metodo} className="flex items-center gap-3 rounded-box border border-base-300 p-3">
                                    <input
                                        type="checkbox"
                                        className="checkbox checkbox-sm"
                                        checked={fila.pactado}
                                        onChange={(e) => cambiar(indice, { pactado: e.target.checked })}
                                        aria-label={`${fila.metodo} entra en el contrato`}
                                    />

                                    <div className="flex-1">
                                        <div className="font-mono font-semibold">{fila.metodo}</div>
                                        <div className="text-base-content/60 text-xs">
                                            {metodo?.nombre} · {metodo?.detecta}
                                        </div>
                                    </div>

                                    {fila.pactado ? (
                                        <Input
                                            type="number"
                                            min={0}
                                            className="w-28 text-right font-mono"
                                            value={fila.comprometidas}
                                            onChange={(e) => cambiar(indice, { comprometidas: e.target.value })}
                                            aria-label={`Pruebas comprometidas de ${fila.metodo}`}
                                        />
                                    ) : (
                                        <span className="text-base-content/40 text-xs">no entra en el contrato</span>
                                    )}
                                </div>
                            );
                        })}
                    </div>

                    <div className="form-control w-full">
                        <label className="label" htmlFor="pnd-nota">
                            <span className="label-text">De dónde sale ese número</span>
                        </label>
                        <textarea
                            id="pnd-nota"
                            className="textarea textarea-bordered w-full"
                            rows={2}
                            placeholder="10% de las juntas de penetración completa, cláusula 7.3"
                            value={data.nota}
                            onChange={(e) => setData('nota', e.target.value)}
                        />
                        <label className="label">
                            <span className="label-text-alt text-base-content/60">
                                Dejarlo escrito evita la discusión de dentro de seis meses sobre si eran 60 u 80.
                            </span>
                        </label>
                        {errors.nota && <span className="text-error text-xs">{errors.nota}</span>}
                    </div>

                    <div className="modal-action">
                        <Button type="button" variant="ghost" onClick={onCerrar}>
                            Cancelar
                        </Button>
                        <Button type="submit" variant="primary" loading={processing}>
                            Guardar plan
                        </Button>
                    </div>
                </form>
            </div>

            <form method="dialog" className="modal-backdrop">
                <button type="submit">cerrar</button>
            </form>
        </dialog>
    );
}
