import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import { etiquetaDePieza, etiquetaDeUnidad } from '@/lib/prod/piezas';
import type { ProdDestajo, ProdGrupoTrabajo, ProdPendienteLiquidar } from '@/types/models';
import { router } from '@inertiajs/react';
import { ClockIcon } from 'lucide-react';
import { useState } from 'react';

type Props = {
    destajo: ProdDestajo;
    pendientes: ProdPendienteLiquidar[];
    gruposTrabajo: ProdGrupoTrabajo[];
};

type Borrador = { porcentaje: number; grupo: string };

const pct = (n: number) => `${(Number(n) * 100).toLocaleString('es-MX', { maximumFractionDigits: 2 })}%`;

export function PendientesLiquidar({ destajo, pendientes, gruposTrabajo }: Props) {
    const [borradores, setBorradores] = useState<Record<string, Borrador>>({});
    const [enviando, setEnviando] = useState<string | null>(null);

    if (pendientes.length === 0) {
        return null;
    }

    // Una pieza puede quedar a medias en más de un proceso —y en más de un paso
    // dentro del mismo proceso—, así que la fila se identifica por la terna.
    const claveDe = (p: ProdPendienteLiquidar) => `${p.pieza_id}|${p.proceso_id}|${p.subproceso_id ?? 0}`;

    const borradorDe = (p: ProdPendienteLiquidar): Borrador =>
        borradores[claveDe(p)] ?? {
            porcentaje: p.porcentaje_sugerido,
            grupo: p.grupo_trabajo_id ? String(p.grupo_trabajo_id) : '',
        };

    const editar = (p: ProdPendienteLiquidar, cambio: Partial<Borrador>) =>
        setBorradores((prev) => ({ ...prev, [claveDe(p)]: { ...borradorDe(p), ...cambio } }));

    const liquidar = (p: ProdPendienteLiquidar) => {
        const b = borradorDe(p);
        setEnviando(claveDe(p));

        router.post(
            `/admin/prod/destajos/${destajo.id}/registros`,
            {
                fecha: destajo.fecha_inicio.slice(0, 10),
                piezas: [p.pieza_id],
                proceso_id: p.proceso_id,
                subproceso_id: p.subproceso_id,
                grupo_trabajo_id: b.grupo,
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
                Piezas que se pagaron a medias en semanas anteriores. Ajusta el porcentaje o el grupo antes de
                agregarlas a este destajo; si la pieza sigue sin terminarse, déjalas para después.
            </p>

            <div className="overflow-x-auto">
                <table className="table table-sm">
                    <thead>
                        <tr>
                            <th>Pieza</th>
                            <th>Proceso</th>
                            <th className="text-center">Avance</th>
                            <th className="text-right">Pagado</th>
                            <th className="text-right">Saldo</th>
                            <th>Grupo</th>
                            <th className="text-right">%</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {pendientes.map((p) => {
                            const clave = claveDe(p);
                            const b = borradorDe(p);
                            const rebasa = b.porcentaje / 100 > p.saldo + 0.0001;

                            return (
                                <tr key={clave} className="hover">
                                    <td>
                                        <div className="font-medium">
                                            {etiquetaDePieza(p.marca, p.lote)}{' '}
                                            <span className="font-mono text-xs">{etiquetaDeUnidad(p)}</span>
                                        </div>
                                        <div className="text-base-content/50 text-xs">
                                            {p.obra ? `[${p.obra}] ` : ''}
                                            {p.descripcion}
                                        </div>
                                    </td>
                                    <td>
                                        <span className="badge badge-sm badge-ghost">{p.proceso}</span>
                                        {p.subproceso && (
                                            <span className="badge badge-sm badge-info ml-1">{p.subproceso}</span>
                                        )}
                                    </td>
                                    <td className="text-center font-mono">{p.numero_avance}º</td>
                                    <td className="text-right font-mono">{pct(p.pagado)}</td>
                                    <td className="text-right font-mono font-semibold">{pct(p.saldo)}</td>
                                    <td className="min-w-[9rem]">
                                        <Select
                                            value={b.grupo}
                                            onValueChange={(v) => editar(p, { grupo: v })}
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
                                            max={100}
                                            step="0.01"
                                            value={b.porcentaje}
                                            onChange={(e) => editar(p, { porcentaje: Number(e.target.value) })}
                                            error={rebasa}
                                        />
                                    </td>
                                    <td className="text-right">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => liquidar(p)}
                                            disabled={!b.grupo || rebasa || enviando === clave}
                                            title={rebasa ? `Se pasa del saldo (${pct(p.saldo)})` : undefined}
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
