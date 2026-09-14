import { Link } from '@inertiajs/react';
import { ArrowDownIcon, ArrowUpIcon } from 'lucide-react';
import { useState } from 'react';
import { formatDate } from '@/components/ui/formatted-date';
import type { CostosCargoHistorial } from '@/types/models';

const fmt = (v: number) => `$${Number(v).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;

const DOCUMENTO: Record<NonNullable<CostosCargoHistorial['documento']>['tipo'], { label: string; ruta: string }> = {
    requisicion: { label: 'Requisición', ruta: 'requisiciones' },
    afectacion: { label: 'Afectación', ruta: 'afectaciones' },
};

/**
 * Historial de cargos de un presupuesto: cada renglón es una afectación a un
 * centro de costo con la OC y las solicitudes de pago aprobadas que la
 * respaldan. Llega del más reciente al más antiguo; el encabezado de la fecha
 * invierte el orden.
 */
export function HistorialCargos({ cargos }: { cargos: CostosCargoHistorial[] }) {
    const [recientesPrimero, setRecientesPrimero] = useState(true);

    if (cargos.length === 0) {
        return <p className="text-sm text-base-content/60">Este presupuesto todavía no tiene cargos.</p>;
    }

    const ordenados = recientesPrimero ? cargos : [...cargos].reverse();
    const vigentes = cargos.filter((cargo) => !cargo.revertido);
    const totalEjercido = vigentes.filter((c) => c.afectacion === 'ejercido').reduce((sum, c) => sum + Number(c.monto), 0);
    const totalApartado = vigentes.filter((c) => c.afectacion === 'apartado').reduce((sum, c) => sum + Number(c.monto), 0);

    return (
        <div className="overflow-x-auto">
            <table className="table w-full">
                <thead>
                    <tr>
                        <th>
                            <button
                                type="button"
                                className="inline-flex items-center gap-1 hover:text-primary"
                                onClick={() => setRecientesPrimero((previo) => !previo)}
                            >
                                Fecha
                                {recientesPrimero ? <ArrowDownIcon className="size-3" /> : <ArrowUpIcon className="size-3" />}
                            </button>
                        </th>
                        <th>Centro de Costos</th>
                        <th>Concepto</th>
                        <th>OC</th>
                        <th>Solicitud de pago</th>
                        <th>Estado</th>
                        <th className="text-right">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    {ordenados.map((cargo) => (
                        <tr key={cargo.id} className={cargo.revertido ? 'text-base-content/50' : ''}>
                            <td className="whitespace-nowrap text-sm">{formatDate(cargo.fecha) ?? '-'}</td>
                            <td className="text-sm">
                                <span className="font-mono">{cargo.centro.codigo}</span>
                                <div className="text-xs text-base-content/60">{cargo.centro.descripcion}</div>
                            </td>
                            <td className="text-sm">
                                {cargo.descripcion ?? '-'}
                                {cargo.documento && (
                                    <div className="text-xs">
                                        <Link
                                            href={`/admin/costos/${DOCUMENTO[cargo.documento.tipo].ruta}/${cargo.documento.id}`}
                                            className="link link-primary"
                                        >
                                            {DOCUMENTO[cargo.documento.tipo].label} {cargo.documento.folio}
                                        </Link>
                                    </div>
                                )}
                            </td>
                            <td className="whitespace-nowrap font-mono text-sm">
                                {cargo.orden_compra ? (
                                    <Link href={`/admin/costos/ordenes-compra/${cargo.orden_compra.id}`} className="link link-primary">
                                        {cargo.orden_compra.folio}
                                    </Link>
                                ) : (
                                    '-'
                                )}
                            </td>
                            <td className="font-mono text-sm">
                                {cargo.solicitudes_pago.length > 0
                                    ? cargo.solicitudes_pago.map((sp) => (
                                          <div key={sp.id} className="whitespace-nowrap">
                                              <Link href={`/admin/costos/solicitudes-pago/${sp.id}`} className="link link-primary">
                                                  {sp.folio}
                                              </Link>
                                          </div>
                                      ))
                                    : '-'}
                            </td>
                            <td>
                                {cargo.revertido ? (
                                    <span className="badge badge-ghost badge-sm" title="El documento se canceló y el cargo ya se revirtió.">
                                        Revertido
                                    </span>
                                ) : cargo.afectacion === 'apartado' ? (
                                    <span className="badge badge-info badge-sm">Apartado</span>
                                ) : (
                                    <span className="badge badge-success badge-sm">Ejercido</span>
                                )}
                            </td>
                            <td className={`text-right font-mono ${cargo.revertido ? 'line-through' : ''}`}>
                                {fmt(cargo.monto)}
                                {cargo.monto_origen !== null && (
                                    <div className="text-xs text-base-content/60">
                                        {fmt(cargo.monto_origen)} {cargo.moneda?.toUpperCase()}
                                    </div>
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
                <tfoot>
                    <tr className="font-bold">
                        <td colSpan={5}>{cargos.length} cargos</td>
                        <td className="text-right">Ejercido</td>
                        <td className="text-right font-mono">{fmt(totalEjercido)}</td>
                    </tr>
                    {totalApartado > 0 && (
                        <tr className="font-bold">
                            <td colSpan={5}></td>
                            <td className="text-right">Apartado</td>
                            <td className="text-right font-mono">{fmt(totalApartado)}</td>
                        </tr>
                    )}
                </tfoot>
            </table>
        </div>
    );
}
