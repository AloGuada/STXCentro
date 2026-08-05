import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import { etiquetaDePieza } from '@/lib/prod/piezas';
import type { ProdDestajo, ProdGrupoTrabajo, ProdPendienteLiquidar } from '@/types/models';
import { router } from '@inertiajs/react';
import { ClockIcon } from 'lucide-react';
import { useState } from 'react';

type Props = {
    destajo: ProdDestajo;
    pendientes: ProdPendienteLiquidar[];
    gruposTrabajo: ProdGrupoTrabajo[];
};

type Borrador = { cantidad: number; porcentaje: number; grupo: string };

const num = (n: number) => Number(n).toLocaleString('es-MX', { maximumFractionDigits: 2 });

export function PendientesLiquidar({ destajo, pendientes, gruposTrabajo }: Props) {
    const [borradores, setBorradores] = useState<Record<number, Borrador>>({});
    const [enviando, setEnviando] = useState<number | null>(null);

    if (pendientes.length === 0) {
        return null;
    }

    const borradorDe = (p: ProdPendienteLiquidar): Borrador =>
        borradores[p.concepto_id] ?? {
            cantidad: p.cantidad_sugerida,
            porcentaje: p.porcentaje_sugerido,
            grupo: p.grupo_trabajo_id ? String(p.grupo_trabajo_id) : '',
        };

    const editar = (conceptoId: number, cambio: Partial<Borrador>) =>
        setBorradores((prev) => ({
            ...prev,
            [conceptoId]: { ...borradorDe(pendientes.find((p) => p.concepto_id === conceptoId)!), ...cambio },
        }));

    const liquidar = (p: ProdPendienteLiquidar) => {
        const b = borradorDe(p);
        setEnviando(p.concepto_id);

        router.post(
            `/admin/prod/destajos/${destajo.id}/registros`,
            {
                fecha: destajo.fecha_inicio.slice(0, 10),
                concepto_id: p.concepto_id,
                grupo_trabajo_id: b.grupo,
                cantidad: b.cantidad,
                porcentaje: b.porcentaje,
            },
            { preserveScroll: true, onFinish: () => setEnviando(null) },
        );
    };

    return (
        <div className="rounded-box border-warning/40 bg-warning/5 border p-4">
            <h3 className="mb-1 flex items-center gap-2 font-semibold">
                <ClockIcon className="size-4" /> Pendientes por liquidar
            </h3>
            <p className="text-base-content/60 mb-3 text-sm">
                Piezas que se pagaron a medias en semanas anteriores. Ajusta cantidad, porcentaje o grupo antes de
                agregarlas a este destajo; si la pieza sigue sin terminarse, déjalas para después.
            </p>

            <div className="overflow-x-auto">
                <table className="table table-sm">
                    <thead>
                        <tr>
                            <th>Pieza</th>
                            <th className="text-right">Catálogo</th>
                            <th className="text-right">Pagado</th>
                            <th className="text-right">Saldo</th>
                            <th>Grupo</th>
                            <th className="text-right">Cantidad</th>
                            <th className="text-right">%</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {pendientes.map((p) => {
                            const b = borradorDe(p);
                            const consumo = (b.cantidad * b.porcentaje) / 100;
                            const rebasa = consumo > p.saldo + 0.0001;

                            return (
                                <tr key={p.concepto_id} className="hover">
                                    <td>
                                        <div className="font-medium">{etiquetaDePieza(p.marca, p.etapa)}</div>
                                        <div className="text-base-content/50 text-xs">
                                            {p.obra ? `[${p.obra}] ` : ''}
                                            {p.descripcion}
                                        </div>
                                    </td>
                                    <td className="text-right font-mono">{num(p.cantidad_catalogo)}</td>
                                    <td className="text-right font-mono">{num(p.pagado)}</td>
                                    <td className="text-right font-mono font-semibold">{num(p.saldo)}</td>
                                    <td className="min-w-[9rem]">
                                        <Select
                                            value={b.grupo}
                                            onValueChange={(v) => editar(p.concepto_id, { grupo: v })}
                                            placeholder="Grupo"
                                        >
                                            {gruposTrabajo.map((g) => (
                                                <SelectItem key={g.id} value={String(g.id)}>
                                                    {g.descripcion}
                                                </SelectItem>
                                            ))}
                                        </Select>
                                    </td>
                                    <td className="w-24">
                                        <Input
                                            type="number"
                                            min={1}
                                            value={b.cantidad}
                                            onChange={(e) =>
                                                editar(p.concepto_id, { cantidad: Number(e.target.value) })
                                            }
                                            error={rebasa}
                                        />
                                    </td>
                                    <td className="w-24">
                                        <Input
                                            type="number"
                                            min={1}
                                            max={100}
                                            step="0.01"
                                            value={b.porcentaje}
                                            onChange={(e) =>
                                                editar(p.concepto_id, { porcentaje: Number(e.target.value) })
                                            }
                                            error={rebasa}
                                        />
                                    </td>
                                    <td className="text-right">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => liquidar(p)}
                                            disabled={!b.grupo || rebasa || enviando === p.concepto_id}
                                            title={rebasa ? `Se pasa del saldo (${num(p.saldo)})` : undefined}
                                        >
                                            Liquidar
                                        </Button>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
