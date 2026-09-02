import { ButtonLink } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { ArrowLeftIcon, LockIcon } from 'lucide-react';

const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });
const moneda = (n: number) => n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });

type Props = {
    ajuste: {
        id: number;
        folio: string | null;
        fecha: string | null;
        almacen: string | null;
        almacen_nombre: string | null;
        obra: string | null;
        motivo: string;
        motivo_etiqueta: string;
        observaciones: string | null;
        autorizo: string | null;
        creado_en: string | null;
    };
    detalles: {
        id: number;
        codigo: string | null;
        descripcion: string | null;
        unidad: string | null;
        cantidad_sistema: number;
        cantidad_contada: number;
        diferencia: number;
        costo_unitario: number | null;
        observaciones: string | null;
    }[];
};

export default function AjusteShow({ ajuste, detalles }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Inventarios', href: '/admin/almacen/existencias' },
        { title: 'Ajustes', href: '/admin/almacen/ajustes' },
        { title: ajuste.folio ?? String(ajuste.id), href: `/admin/almacen/ajustes/${ajuste.id}` },
    ];

    const neta = detalles.reduce((suma, d) => suma + d.diferencia, 0);
    const conDiferencia = detalles.filter((d) => d.diferencia !== 0).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Ajuste ${ajuste.folio ?? ajuste.id}`} />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="font-mono text-2xl font-semibold">{ajuste.folio}</h1>
                            <span className="badge badge-sm badge-ghost">{ajuste.motivo_etiqueta}</span>
                        </div>
                        <p className="text-base-content/60 mt-1 text-sm">
                            {ajuste.almacen} · {ajuste.almacen_nombre}
                            {ajuste.obra ? ` (${ajuste.obra})` : ''} · {ajuste.fecha}
                        </p>
                        {ajuste.autorizo && (
                            <p className="text-base-content/60 text-sm">Autorizó {ajuste.autorizo}</p>
                        )}
                    </div>

                    <ButtonLink href="/admin/almacen/ajustes" variant="outline">
                        <ArrowLeftIcon className="size-4" />
                        Volver
                    </ButtonLink>
                </div>

                {/* Los documentos de almacén no se editan: es lo que hace que el
                    kardex se pueda auditar hacia atrás. */}
                <div className="alert mb-4">
                    <LockIcon className="size-4" />
                    <span>
                        Este documento ya no se modifica. Si algo quedó mal, se corrige con otro ajuste y los dos
                        quedan en el kardex.
                    </span>
                </div>

                {ajuste.observaciones && (
                    <div className="rounded-box border-base-300 mb-4 border p-4">
                        <h2 className="mb-1 font-medium">Observaciones</h2>
                        <p className="text-base-content/80 text-sm">{ajuste.observaciones}</p>
                    </div>
                )}

                <div className="rounded-box border-base-300 border">
                    <div className="border-base-300 flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3">
                        <h2 className="font-medium">Conteo</h2>
                        <p className="text-base-content/60 text-sm">
                            {conDiferencia} de {detalles.length} renglón(es) con diferencia · neta{' '}
                            <span className={neta < 0 ? 'text-error' : neta > 0 ? 'text-success' : ''}>
                                {neta > 0 ? '+' : ''}
                                {numero(neta)}
                            </span>
                        </p>
                    </div>
                    <table className="table table-sm">
                        <thead className="bg-base-200">
                            <tr>
                                <th>Artículo</th>
                                <th>Unidad</th>
                                <th className="text-right">Sistema</th>
                                <th className="text-right">Contado</th>
                                <th className="text-right">Diferencia</th>
                                <th className="text-right">Costo unitario</th>
                                <th>Observaciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {detalles.map((d) => (
                                <tr key={d.id} className="hover">
                                    <td>
                                        <span className="font-mono text-xs">{d.codigo}</span>
                                        <span className="block">{d.descripcion}</span>
                                    </td>
                                    <td className="text-base-content/60 font-mono text-xs">{d.unidad}</td>
                                    <td className="text-base-content/60 text-right font-mono">
                                        {numero(d.cantidad_sistema)}
                                    </td>
                                    <td className="text-right font-mono">{numero(d.cantidad_contada)}</td>
                                    <td className="text-right font-mono">
                                        {d.diferencia === 0 ? (
                                            <span className="text-base-content/40">—</span>
                                        ) : (
                                            <span
                                                className={
                                                    d.diferencia < 0
                                                        ? 'text-error font-semibold'
                                                        : 'text-success font-semibold'
                                                }
                                            >
                                                {d.diferencia > 0 ? '+' : ''}
                                                {numero(d.diferencia)}
                                            </span>
                                        )}
                                    </td>
                                    <td className="text-right font-mono">
                                        {d.costo_unitario === null ? (
                                            <span
                                                className="text-base-content/40"
                                                title="Se valuó al costo promedio vigente"
                                            >
                                                —
                                            </span>
                                        ) : (
                                            moneda(d.costo_unitario)
                                        )}
                                    </td>
                                    <td className="text-base-content/60 text-sm">{d.observaciones}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <p className="text-base-content/60 mt-4 text-sm">
                    Sólo los renglones con diferencia dejaron asiento en el kardex: contar exacto es información, pero
                    no es un movimiento.
                </p>
            </div>
        </AppLayout>
    );
}
