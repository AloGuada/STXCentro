import { Button } from '@/components/ui/button';
import type { CostosRequisicion } from '@/types/models';
import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

type RubroOption = { id: number; label: string };

type Props = {
    requisicion: CostosRequisicion;
    rubros: RubroOption[];
    open: boolean;
    onClose: () => void;
};

/**
 * Modal de generacion de OCs desde una requisicion aprobada. Para cada
 * seleccion el comprador asigna un obra_rubro_id, y captura los datos
 * globales de OC (moneda, fecha entrega, notas). El backend agrupa por
 * proveedor y crea N OCs.
 */
export function GenerarOrdenesModal({ requisicion, rubros, open, onClose }: Props) {
    const selecciones = useMemo(
        () => (requisicion.detalles ?? []).flatMap((d) =>
            (d.selecciones ?? []).map((s) => ({ ...s, partidaDescripcion: d.descripcion, partidaUnidad: d.unidad }))
        ),
        [requisicion],
    );

    const [moneda, setMoneda] = useState<'mxn' | 'usd' | 'eur'>('mxn');
    const [fechaEntrega, setFechaEntrega] = useState<string>('');
    const [notas, setNotas] = useState<string>('');
    const [rubroPorSel, setRubroPorSel] = useState<Record<number, number | ''>>({});
    const [submitting, setSubmitting] = useState(false);
    const [errorMsg, setErrorMsg] = useState<string | null>(null);

    if (!open) return null;

    const setRubro = (selId: number, rubroId: number | '') => {
        setRubroPorSel((prev) => ({ ...prev, [selId]: rubroId }));
    };

    const allReady = selecciones.every((s) => Number.isFinite(rubroPorSel[s.id] as number) && rubroPorSel[s.id] !== '');

    const submit = () => {
        if (!allReady) {
            setErrorMsg('Asigna un rubro a cada selección antes de continuar.');
            return;
        }

        setErrorMsg(null);
        setSubmitting(true);
        router.post(`/admin/costos/requisiciones/${requisicion.id}/generar-ordenes`, {
            moneda,
            fecha_entrega_esperada: fechaEntrega || null,
            notas: notas || null,
            rubros: selecciones.map((s) => ({
                seleccion_id: s.id,
                obra_rubro_id: rubroPorSel[s.id],
            })),
        }, {
            preserveScroll: true,
            onError: (errors) => {
                setSubmitting(false);
                setErrorMsg(Object.values(errors)[0] ?? 'No se pudieron generar las OCs.');
            },
            onSuccess: () => onClose(),
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <dialog className="modal modal-open">
            <div className="modal-box max-w-3xl">
                <h3 className="font-bold text-lg mb-3">Generar órdenes de compra</h3>
                <p className="mb-4 text-sm text-base-content/60">
                    Una OC por cada proveedor distinto. Asigna el rubro de obra para cada partida.
                </p>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                    <div>
                        <label className="label-text text-xs">Moneda</label>
                        <select
                            className="select select-bordered select-sm w-full"
                            value={moneda}
                            onChange={(e) => setMoneda(e.target.value as 'mxn' | 'usd' | 'eur')}
                        >
                            <option value="mxn">MXN</option>
                            <option value="usd">USD</option>
                            <option value="eur">EUR</option>
                        </select>
                    </div>
                    <div>
                        <label className="label-text text-xs">Fecha de entrega</label>
                        <input
                            type="date"
                            className="input input-bordered input-sm w-full"
                            value={fechaEntrega}
                            onChange={(e) => setFechaEntrega(e.target.value)}
                        />
                    </div>
                    <div>
                        <label className="label-text text-xs">Notas</label>
                        <input
                            type="text"
                            className="input input-bordered input-sm w-full"
                            value={notas}
                            onChange={(e) => setNotas(e.target.value)}
                        />
                    </div>
                </div>

                <div className="overflow-x-auto rounded-lg border border-base-300 mb-3">
                    <table className="table table-sm">
                        <thead>
                            <tr>
                                <th>Partida</th>
                                <th>Proveedor</th>
                                <th className="text-right">Cantidad</th>
                                <th className="min-w-[260px]">Rubro de obra *</th>
                            </tr>
                        </thead>
                        <tbody>
                            {selecciones.map((s) => (
                                <tr key={s.id}>
                                    <td>
                                        <div className="text-sm">{s.partidaDescripcion}</div>
                                        <div className="text-xs text-base-content/60">{s.partidaUnidad}</div>
                                    </td>
                                    <td>{s.proveedor?.razon_social ?? `#${s.proveedor_id}`}</td>
                                    <td className="text-right">{Number(s.cantidad).toLocaleString('es-MX')}</td>
                                    <td>
                                        <select
                                            className="select select-bordered select-sm w-full"
                                            value={rubroPorSel[s.id] ?? ''}
                                            onChange={(e) => setRubro(s.id, e.target.value ? Number(e.target.value) : '')}
                                        >
                                            <option value="">Selecciona rubro...</option>
                                            {rubros.map((r) => (
                                                <option key={r.id} value={r.id}>{r.label}</option>
                                            ))}
                                        </select>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {errorMsg && <p className="alert alert-error text-sm mb-3">{errorMsg}</p>}

                <div className="modal-action">
                    <Button variant="outline" onClick={onClose} disabled={submitting}>Cancelar</Button>
                    <Button onClick={submit} disabled={submitting || !allReady}>
                        {submitting ? 'Generando...' : 'Generar OCs'}
                    </Button>
                </div>
            </div>
        </dialog>
    );
}
