import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type AnticipoOption = {
    id: number;
    folio: string;
    monto: number;
    saldo_disponible: number;
    fecha: string;
    referencia: string | null;
};

type Props = {
    facturaId: number;
    saldoFactura: number;
    open: boolean;
    onClose: () => void;
};

const formatMoney = (n: number) => `$${Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;

/**
 * Modal para aplicar un anticipo del mismo proveedor (y misma moneda) a la
 * factura abierta. Carga via fetch los anticipos vigentes con saldo > 0
 * disponibles para esta factura. Valida que el monto no exceda ni saldo
 * del anticipo ni saldo pendiente de la factura.
 */
export function AplicarAnticipoModal({ facturaId, saldoFactura, open, onClose }: Props) {
    const [anticipos, setAnticipos] = useState<AnticipoOption[]>([]);
    const [loading, setLoading] = useState(false);
    const [anticipoId, setAnticipoId] = useState<number | ''>('');
    const [monto, setMonto] = useState<string>('');
    const [notas, setNotas] = useState<string>('');
    const [submitting, setSubmitting] = useState(false);
    const [errorMsg, setErrorMsg] = useState<string | null>(null);

    useEffect(() => {
        if (!open) return;
        setLoading(true);
        fetch(`/admin/costos/facturas/${facturaId}/anticipos-disponibles`, {
            headers: { Accept: 'application/json' },
        })
            .then((r) => r.json())
            .then((d) => setAnticipos(d.anticipos ?? []))
            .finally(() => setLoading(false));
    }, [open, facturaId]);

    if (!open) return null;

    const anticipoSeleccionado = anticipos.find((a) => a.id === anticipoId);
    const maxMonto = anticipoSeleccionado
        ? Math.min(Number(anticipoSeleccionado.saldo_disponible), saldoFactura)
        : 0;

    const submit = () => {
        if (anticipoId === '') {
            setErrorMsg('Selecciona un anticipo.');
            return;
        }
        const montoNum = Number(monto);
        if (!Number.isFinite(montoNum) || montoNum <= 0) {
            setErrorMsg('Indica un monto válido.');
            return;
        }
        if (montoNum > maxMonto + 0.001) {
            setErrorMsg(`El monto excede ${formatMoney(maxMonto)} disponible.`);
            return;
        }

        setErrorMsg(null);
        setSubmitting(true);
        router.post('/admin/costos/anticipos/aplicar', {
            anticipo_id: anticipoId,
            factura_id: facturaId,
            monto: montoNum,
            notas: notas || null,
        }, {
            preserveScroll: true,
            onError: (errors) => {
                setSubmitting(false);
                setErrorMsg(Object.values(errors)[0] ?? 'No se pudo aplicar.');
            },
            onSuccess: () => {
                setAnticipoId('');
                setMonto('');
                setNotas('');
                onClose();
            },
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <dialog className="modal modal-open">
            <div className="modal-box max-w-xl">
                <h3 className="font-bold text-lg mb-3">Aplicar anticipo</h3>
                <p className="mb-3 text-sm text-base-content/60">
                    Saldo pendiente de la factura: <strong>{formatMoney(saldoFactura)}</strong>
                </p>

                {loading ? (
                    <p className="py-4 text-center text-sm text-base-content/60">Cargando anticipos disponibles...</p>
                ) : anticipos.length === 0 ? (
                    <p className="alert alert-warning text-sm">
                        Este proveedor no tiene anticipos vigentes con saldo disponible en la misma moneda.
                    </p>
                ) : (
                    <div className="space-y-3">
                        <div>
                            <label className="label-text text-xs">Anticipo *</label>
                            <select
                                className="select select-bordered select-sm w-full"
                                value={anticipoId}
                                onChange={(e) => setAnticipoId(e.target.value ? Number(e.target.value) : '')}
                            >
                                <option value="">Selecciona...</option>
                                {anticipos.map((a) => (
                                    <option key={a.id} value={a.id}>
                                        {a.folio} — Saldo {formatMoney(a.saldo_disponible)} de {formatMoney(a.monto)} ({a.fecha})
                                    </option>
                                ))}
                            </select>
                        </div>

                        {anticipoSeleccionado && (
                            <div className="text-xs text-base-content/60">
                                Aplicable hasta {formatMoney(maxMonto)}
                            </div>
                        )}

                        <div>
                            <label className="label-text text-xs">Monto a aplicar *</label>
                            <input
                                type="number"
                                step="0.01"
                                max={maxMonto}
                                className="input input-bordered input-sm w-full"
                                value={monto}
                                onChange={(e) => setMonto(e.target.value)}
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
                )}

                {errorMsg && <p className="alert alert-error mt-3 text-sm">{errorMsg}</p>}

                <div className="modal-action">
                    <Button variant="outline" onClick={onClose} disabled={submitting}>Cancelar</Button>
                    {anticipos.length > 0 && (
                        <Button onClick={submit} disabled={submitting || anticipoId === ''}>
                            {submitting ? 'Aplicando...' : 'Aplicar'}
                        </Button>
                    )}
                </div>
            </div>
        </dialog>
    );
}
