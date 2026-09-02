import { ButtonLink } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeftIcon, LockIcon, TriangleAlertIcon } from 'lucide-react';

const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });
const moneda = (n: number) => n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });

type Props = {
    entrada: {
        id: number;
        folio: string | null;
        fecha: string | null;
        registrada_at: string | null;
        almacen: string | null;
        almacen_nombre: string | null;
        orden_compra_id: number | null;
        orden_folio: string | null;
        proveedor: string | null;
        sin_orden: boolean;
        observaciones: string | null;
        recibio: string | null;
        cancelada: boolean;
        motivo_cancelacion: string | null;
        importe: number;
    };
    detalles: {
        id: number;
        codigo: string | null;
        descripcion: string | null;
        unidad: string | null;
        cantidad: number;
        precio_unitario: number;
        importe: number;
        /** Falso cuando el renglón se recibió pero no movió existencia. */
        mueve_kardex: boolean;
        observaciones: string | null;
    }[];
};

export default function EntradaShow({ entrada, detalles }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Inventarios', href: '/admin/almacen/existencias' },
        { title: 'Entradas', href: '/admin/almacen/entradas' },
        { title: entrada.folio ?? String(entrada.id), href: `/admin/almacen/entradas/${entrada.id}` },
    ];

    const sinKardex = detalles.filter((d) => !d.mueve_kardex).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Entrada ${entrada.folio ?? entrada.id}`} />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className={`font-mono text-2xl font-semibold ${entrada.cancelada ? 'line-through' : ''}`}>
                                {entrada.folio}
                            </h1>
                            {entrada.cancelada && <span className="badge badge-ghost">Cancelada</span>}
                            {entrada.sin_orden && <span className="badge badge-sm badge-ghost">Sin orden</span>}
                        </div>
                        <p className="text-base-content/60 mt-1 text-sm">
                            {entrada.almacen} · {entrada.almacen_nombre} · {entrada.fecha}
                            {entrada.registrada_at && (
                                <span
                                    className="text-base-content/40"
                                    title="Cuándo se capturó. La fecha de arriba es cuándo entró el material."
                                >
                                    {' '}· registrada {entrada.registrada_at}
                                </span>
                            )}
                        </p>
                        {entrada.orden_folio && (
                            <p className="text-sm">
                                Contra{' '}
                                <Link
                                    href={`/admin/costos/ordenes-compra/${entrada.orden_compra_id}`}
                                    className="link link-hover font-mono"
                                >
                                    {entrada.orden_folio}
                                </Link>
                                {entrada.proveedor && ` · ${entrada.proveedor}`}
                            </p>
                        )}
                        {entrada.recibio && (
                            <p className="text-base-content/60 text-sm">Recibió {entrada.recibio}</p>
                        )}
                    </div>

                    <ButtonLink href="/admin/almacen/entradas" variant="outline">
                        <ArrowLeftIcon className="size-4" />
                        Volver
                    </ButtonLink>
                </div>

                {entrada.cancelada ? (
                    <div className="alert alert-warning mb-4">
                        <span>
                            Cancelada: {entrada.motivo_cancelacion}. El material salió del kardex con un movimiento
                            espejo; si ya se había consumido, el saldo pudo quedar en negativo — y eso es correcto: se
                            gastó algo que ahora se dice no haber recibido.
                        </span>
                    </div>
                ) : (
                    <div className="alert mb-4">
                        <LockIcon className="size-4" />
                        <span>
                            {entrada.sin_orden
                                ? 'Esta entrada no cuelga de ninguna orden de compra: no afecta presupuesto ni factura, sólo suma existencia.'
                                : 'Esta recepción es la misma que ve Compras: es la que destraba la factura y ajusta el presupuesto por diferencia de precio. Se cancela desde la orden.'}
                        </span>
                    </div>
                )}

                {sinKardex > 0 && (
                    <div className="alert alert-warning mb-4">
                        <TriangleAlertIcon className="size-4" />
                        <span>
                            {sinKardex} renglón(es) se recibieron pero no movieron existencia: son servicios, o
                            artículos que Compras tecleó sin código y nadie ha clasificado. Mientras estén así, el
                            material entra sin quedar en el kardex.
                        </span>
                    </div>
                )}

                {entrada.observaciones && (
                    <div className="rounded-box border-base-300 mb-4 border p-4">
                        <h2 className="mb-1 font-medium">Observaciones</h2>
                        <p className="text-base-content/80 text-sm">{entrada.observaciones}</p>
                    </div>
                )}

                <div className="rounded-box border-base-300 border">
                    <div className="border-base-300 flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3">
                        <h2 className="font-medium">Qué llegó</h2>
                        <p className="text-base-content/60 text-sm">
                            Importe recibido: <span className="font-mono">{moneda(entrada.importe)}</span>
                        </p>
                    </div>
                    <table className="table table-sm">
                        <thead className="bg-base-200">
                            <tr>
                                <th>Artículo</th>
                                <th>Unidad</th>
                                <th className="text-right">Cantidad</th>
                                <th className="text-right">Costo unitario</th>
                                <th className="text-right">Importe</th>
                                <th className="text-center">Kardex</th>
                                <th>Observaciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {detalles.map((d) => (
                                <tr key={d.id} className={d.mueve_kardex ? 'hover' : 'bg-warning/10'}>
                                    <td>
                                        <span className="font-mono text-xs">{d.codigo}</span>
                                        <span className="block">{d.descripcion}</span>
                                    </td>
                                    <td className="text-base-content/60 font-mono text-xs">{d.unidad}</td>
                                    <td className="text-right font-mono">{numero(d.cantidad)}</td>
                                    <td className="text-base-content/60 text-right font-mono">
                                        {moneda(d.precio_unitario)}
                                    </td>
                                    <td className="text-right font-mono">{moneda(d.importe)}</td>
                                    <td className="text-center">
                                        {d.mueve_kardex ? (
                                            <span className="badge badge-sm badge-success">Sí</span>
                                        ) : (
                                            <span
                                                className="badge badge-sm badge-warning"
                                                title="Se recibió pero no movió existencia"
                                            >
                                                No
                                            </span>
                                        )}
                                    </td>
                                    <td className="text-base-content/60 text-sm">{d.observaciones}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
