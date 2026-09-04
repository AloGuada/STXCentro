import { RegistrosPorMarca } from '@/components/prod/registros-por-marca';
import type {
    Concepto,
    Obra,
    ProdGrupoTrabajo,
    ProdGrupoPrecioSubproceso,
    ProdPagoExtra,
    ProdPieza,
    ProdProceso,
    ProdRegistro,
    ProdTipoPagoExtra,
} from '@/types/models';
import { router } from '@inertiajs/react';
import { Trash2Icon } from 'lucide-react';

export type RegistroPreview = ProdRegistro & {
    pieza?: ProdPieza & { marca?: Concepto & { obra?: Obra } };
    proceso?: ProdProceso;
    subproceso?: ProdGrupoPrecioSubproceso;
    grupo_trabajo?: ProdGrupoTrabajo;
};

export type PagoExtraPreview = ProdPagoExtra & {
    tipo?: ProdTipoPagoExtra;
    grupo_trabajo?: ProdGrupoTrabajo;
};

const money = (n: number) => Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

type Props = {
    destajoId: number;
    grupoNombre: string;
    registros: RegistroPreview[];
    pagosExtra: PagoExtraPreview[];
};

export function GrupoDestajoCard({ destajoId, grupoNombre, registros, pagosExtra }: Props) {
    const totalExtras = pagosExtra.reduce((acc, pe) => acc + Number(pe.monto ?? pe.precio * pe.dias * pe.personas), 0);
    // Un renglon es una pieza; los equivalentes son lo que realmente se gasta
    // del catalogo cuando hay parcialidades.
    const totalPiezas = registros.length;
    const totalEquivalentes = registros.reduce((acc, r) => acc + Number(r.porcentaje ?? 100) / 100, 0);
    const hayParciales = registros.some((r) => Number(r.porcentaje ?? 100) < 100);

    const eliminarRegistro = (id: number) => {
        router.delete(`/admin/prod/destajos/${destajoId}/registros/${id}`, { preserveScroll: true });
    };

    const eliminarPagoExtra = (id: number) => {
        router.delete(`/admin/prod/destajos/${destajoId}/pagos-extra/${id}`, { preserveScroll: true });
    };

    return (
        <div className="rounded-box border border-base-300 overflow-hidden">
            <div className="flex items-center justify-between border-b border-base-300 bg-base-200 px-4 py-2">
                <span className="font-semibold">{grupoNombre}</span>
                <span className="text-base-content/60 text-xs">
                    {totalPiezas} piezas
                    {hayParciales && ` (${totalEquivalentes.toLocaleString('es-MX')} equivalentes)`} ·{' '}
                    {pagosExtra.length} pagos extra
                </span>
            </div>

            <div className="space-y-4 p-4">
                <div>
                    <div className="mb-1 flex items-center gap-2">
                        <span className="text-sm font-medium">Producción</span>
                        <span className="badge badge-sm badge-info">{registros.length}</span>
                    </div>
                    <RegistrosPorMarca registros={registros} onEliminar={eliminarRegistro} />
                </div>

                <div>
                    <div className="mb-1 flex items-center gap-2">
                        <span className="text-sm font-medium">Pagos extra</span>
                        <span className="badge badge-sm badge-warning">{pagosExtra.length}</span>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="table table-sm">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Descripción</th>
                                    <th className="text-right">Precio</th>
                                    <th className="text-right">Días</th>
                                    <th className="text-right">Personas</th>
                                    <th className="text-right">Monto</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                {pagosExtra.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="text-base-content/50 py-4 text-center">
                                            Sin pagos extra
                                        </td>
                                    </tr>
                                ) : (
                                    pagosExtra.map((pe) => (
                                        <tr key={pe.id} className="hover">
                                            <td>{pe.tipo?.descripcion}</td>
                                            <td>{pe.descripcion}</td>
                                            <td className="text-right font-mono">${money(pe.precio)}</td>
                                            <td className="text-right font-mono">{Number(pe.dias)}</td>
                                            <td className="text-right font-mono">{pe.personas}</td>
                                            <td className="text-right font-mono">
                                                ${money(Number(pe.monto ?? pe.precio * pe.dias * pe.personas))}
                                            </td>
                                            <td className="text-right">
                                                <button
                                                    className="btn btn-ghost btn-xs text-error"
                                                    onClick={() => eliminarPagoExtra(pe.id)}
                                                    aria-label="Eliminar pago extra"
                                                >
                                                    <Trash2Icon className="size-3.5" />
                                                </button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                            {pagosExtra.length > 0 && (
                                <tfoot>
                                    <tr className="font-semibold">
                                        <td colSpan={5} className="text-right">
                                            Subtotal extras
                                        </td>
                                        <td className="text-right font-mono">${money(totalExtras)}</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            )}
                        </table>
                    </div>
                </div>
            </div>
        </div>
    );
}
