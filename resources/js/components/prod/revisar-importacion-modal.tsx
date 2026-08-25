import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { ProdPlanEstado, ProdPlanGrupo, ProdPlanImportacion, ProdPlanRenglon } from '@/types/models';
import { AlertTriangleIcon, InfoIcon, Loader2Icon } from 'lucide-react';
import { useState } from 'react';

type Props = {
    open: boolean;
    onClose: () => void;
    plan: ProdPlanImportacion | null;
    cargando: boolean;
    error: string | null;
    confirmando: boolean;
    onConfirmar: () => void;
};

const CLASE_ESTADO: Record<ProdPlanEstado, string> = {
    aplicable: 'badge-success',
    omitida: 'badge-warning',
    error: 'badge-error',
};

const ETIQUETA_ESTADO: Record<ProdPlanEstado, string> = {
    aplicable: 'Entra',
    omitida: 'Se omite',
    error: 'Con problema',
};

/**
 * Paso 1 del import: enseña lo que va a pasar antes de tocar la base.
 *
 * Es presentacional a propósito: el archivo y las peticiones viven en el
 * formulario que lo abre, porque confirmar tiene que pasar por Inertia para que
 * la pantalla del destajo se refresque con los registros nuevos.
 */
export function RevisarImportacionModal({
    open,
    onClose,
    plan,
    cargando,
    error,
    confirmando,
    onConfirmar,
}: Props) {
    const [filtro, setFiltro] = useState<ProdPlanEstado | 'todos'>('todos');

    const renglones = plan?.renglones.filter((r) => filtro === 'todos' || r.estado === filtro) ?? [];
    const resumen = plan?.resumen;
    const sinNadaQueAplicar = !!resumen && resumen.aplicables === 0;

    return (
        <Dialog open={open} onOpenChange={(abierto) => !abierto && onClose()}>
            <DialogContent className="max-w-5xl">
                <DialogHeader>
                    <DialogTitle>Revisar importación</DialogTitle>
                </DialogHeader>

                {cargando && (
                    <div className="flex items-center justify-center gap-2 py-12">
                        <Loader2Icon className="size-5 animate-spin" />
                        <span>Analizando el archivo…</span>
                    </div>
                )}

                {!cargando && error && (
                    <div className="alert alert-error">
                        <span>{error}</span>
                    </div>
                )}

                {!cargando && !error && plan && resumen && (
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <Tarjeta valor={resumen.aplicables} etiqueta="van a entrar" clase="text-success" />
                            <Tarjeta
                                valor={resumen.omitidas + resumen.ignorados_por_evento}
                                etiqueta="se ignoran"
                                clase="text-warning"
                            />
                            <Tarjeta valor={resumen.errores} etiqueta="con problema" clase="text-error" />
                            {resumen.asignadas_por_sistema > 0 && (
                                <Tarjeta
                                    valor={resumen.asignadas_por_sistema}
                                    etiqueta="los eligió el sistema"
                                    clase="text-info"
                                />
                            )}
                        </div>

                        {resumen.asignadas_por_sistema > 0 && (
                            <div className="alert alert-info">
                                <InfoIcon className="size-5 shrink-0" />
                                <span>
                                    El archivo no trae QR para {resumen.asignadas_por_sistema} movimiento(s): se tomó
                                    cada vez la pieza con el <strong>QR disponible más chico</strong> que todavía no se
                                    ha pagado en ese proceso. Revísalos abajo.
                                </span>
                            </div>
                        )}

                        {plan.ignorados.length > 0 && (
                            <p className="text-base-content/60 text-sm">
                                {resumen.ignorados_por_evento} renglón(es) de eventos que no pagan destajo:{' '}
                                {plan.ignorados.map((i) => `${i.evento} (${i.renglones})`).join(', ')}.
                            </p>
                        )}

                        {plan.por_grupo.length > 0 && <PorGrupo grupos={plan.por_grupo} />}

                        <div className="flex flex-wrap gap-2">
                            {(['todos', 'aplicable', 'omitida', 'error'] as const).map((valor) => (
                                <button
                                    key={valor}
                                    type="button"
                                    className={`badge badge-lg ${filtro === valor ? 'badge-primary' : 'badge-ghost'}`}
                                    onClick={() => setFiltro(valor)}
                                >
                                    {valor === 'todos' ? 'Todos' : ETIQUETA_ESTADO[valor]}
                                </button>
                            ))}
                        </div>

                        <div className="rounded-box border-base-300 max-h-[45vh] overflow-auto border">
                            <table className="table table-sm">
                                <thead className="bg-base-200 sticky top-0">
                                    <tr>
                                        <th>Referencia</th>
                                        <th>Marca</th>
                                        <th>QS</th>
                                        <th>QR</th>
                                        <th>Proceso</th>
                                        <th>Grupo</th>
                                        <th className="text-right">%</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {renglones.length === 0 ? (
                                        <tr>
                                            <td colSpan={8} className="text-base-content/50 py-6 text-center">
                                                Ningún renglón en este filtro.
                                            </td>
                                        </tr>
                                    ) : (
                                        renglones.map((renglon, i) => <Renglon key={i} renglon={renglon} />)
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {plan.truncado && (
                            <p className="text-base-content/60 text-xs">
                                Mostrando {plan.mostrados} renglones; los conteos de arriba son del archivo completo.
                            </p>
                        )}

                        {sinNadaQueAplicar && (
                            <div className="alert alert-warning">
                                <AlertTriangleIcon className="size-5 shrink-0" />
                                <span>
                                    {resumen.errores > 0
                                        ? 'Ningún renglón puede entrar. Corrige el archivo y vuelve a subirlo.'
                                        : 'El archivo no trae movimientos de los eventos que pagan destajo.'}
                                </span>
                            </div>
                        )}
                    </div>
                )}

                <DialogFooter>
                    <Button variant="outline" onClick={onClose} disabled={confirmando}>
                        Cancelar
                    </Button>
                    <Button onClick={onConfirmar} disabled={cargando || confirmando || !plan || sinNadaQueAplicar}>
                        {confirmando && <Loader2Icon className="size-4 animate-spin" />}
                        Importar {resumen ? `${resumen.aplicables} registros` : ''}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/**
 * A quién le va a quedar cada cosa. Es lo que se revisa antes de aceptar: los
 * movimientos son lo que se escribe y las piezas lo que se paga, que dejan de
 * ser lo mismo en cuanto hay avances parciales.
 *
 * Los totales los manda el backend sobre el archivo completo; la tabla de abajo
 * va truncada y sumarla daría de menos justo en los archivos grandes.
 */
function PorGrupo({ grupos }: { grupos: ProdPlanGrupo[] }) {
    const total = (campo: 'movimientos' | 'piezas' | 'no_entran') =>
        grupos.reduce((suma, g) => suma + g[campo], 0);

    return (
        <div>
            <h3 className="mb-2 text-sm font-semibold">Qué le toca a cada grupo</h3>
            <div className="rounded-box border-base-300 max-h-56 overflow-auto border">
                <table className="table table-sm">
                    <thead className="bg-base-200 sticky top-0">
                        <tr>
                            <th>Grupo</th>
                            <th className="text-right">Movimientos</th>
                            <th className="text-right">Piezas</th>
                            <th className="text-right">No entran</th>
                        </tr>
                    </thead>
                    <tbody>
                        {grupos.map((grupo) => (
                            <tr key={grupo.grupo_trabajo_id ?? 'sin-grupo'} className="hover">
                                <td className={grupo.grupo === null ? 'text-error text-sm' : 'text-sm'}>
                                    {grupo.grupo ?? 'Sin grupo resuelto'}
                                </td>
                                <td className="text-right font-mono">{grupo.movimientos}</td>
                                <td className="text-right font-mono">{grupo.piezas.toFixed(2)}</td>
                                <td className="text-right font-mono">
                                    {grupo.no_entran > 0 ? (
                                        <span className="text-error">{grupo.no_entran}</span>
                                    ) : (
                                        <span className="text-base-content/30">—</span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    {grupos.length > 1 && (
                        <tfoot className="bg-base-200">
                            <tr>
                                <th>Total</th>
                                <th className="text-right font-mono">{total('movimientos')}</th>
                                <th className="text-right font-mono">{total('piezas').toFixed(2)}</th>
                                <th className="text-right font-mono">{total('no_entran')}</th>
                            </tr>
                        </tfoot>
                    )}
                </table>
            </div>
        </div>
    );
}

function Tarjeta({ valor, etiqueta, clase }: { valor: number; etiqueta: string; clase: string }) {
    return (
        <div className="rounded-box border-base-300 border p-3">
            <div className={`text-2xl font-semibold ${clase}`}>{valor}</div>
            <div className="text-base-content/60 text-xs">{etiqueta}</div>
        </div>
    );
}

function Renglon({ renglon }: { renglon: ProdPlanRenglon }) {
    return (
        <tr className="hover">
            <td className="text-xs">{renglon.referencia}</td>
            <td>{renglon.marca ?? <span className="text-base-content/40">—</span>}</td>
            <td className="font-mono text-xs">{renglon.qs ?? '—'}</td>
            <td className="font-mono text-xs">
                {renglon.qr ?? <span className="text-base-content/40">—</span>}
                {renglon.por_qs && (
                    <span
                        className="badge badge-xs badge-info ml-1"
                        title={
                            `El archivo no traía QR; se tomó la pieza con el QR disponible más chico de las ` +
                            `${renglon.candidatas} que comparten ` +
                            (renglon.asignado_por === 'marca' ? 'esta marca.' : 'este QS.')
                        }
                    >
                        {renglon.asignado_por === 'marca' ? 'por marca' : 'por QS'}
                    </span>
                )}
            </td>
            <td>{renglon.proceso ?? '—'}</td>
            <td>{renglon.grupo ?? '—'}</td>
            <td className="text-right font-mono">{renglon.porcentaje ?? '—'}</td>
            <td>
                <span className={`badge badge-sm ${CLASE_ESTADO[renglon.estado]}`}>
                    {ETIQUETA_ESTADO[renglon.estado]}
                </span>
                {renglon.motivo && <p className="text-base-content/60 mt-1 max-w-md text-xs">{renglon.motivo}</p>}
            </td>
        </tr>
    );
}
