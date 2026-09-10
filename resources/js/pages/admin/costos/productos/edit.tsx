import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosProducto } from '@/types/models';
import { TIPO_MONEDA_LABELS } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import { type FormEvent } from 'react';

type Props = { producto: CostosProducto };

const fmtMoney = (n: number | string) =>
    `$${Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;

const fmtDate = (d: string) => new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });

export default function ProductosEdit({ producto }: Props) {
    const { can } = useCan();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/productos' },
        { title: 'Productos', href: '/admin/costos/productos' },
        { title: producto.descripcion, href: `/admin/costos/productos/${producto.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        codigo: producto.codigo ?? '',
        descripcion: producto.descripcion,
        unidad: producto.unidad,
        activo: producto.activo,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/costos/productos/${producto.id}`, { preserveScroll: true });
    };

    const precios = producto.precios ?? [];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${producto.descripcion}`} />

            <div className="p-6">
                <div className="grid w-full max-w-5xl gap-6 lg:grid-cols-2">
                    <div>
                        <div className="mb-6 flex items-center justify-between">
                            <h1 className="text-2xl font-semibold">Editar producto</h1>
                            {can('costos.productos.eliminar') && (
                                <DeleteDialog
                                    title="Eliminar producto"
                                    description={`¿Eliminar "${producto.descripcion}"? Se borrará también su histórico de precios.`}
                                    deleteUrl={`/admin/costos/productos/${producto.id}`}
                                />
                            )}
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            <FormField label="Código" htmlFor="codigo" error={errors.codigo}>
                                <Input id="codigo" value={data.codigo} onChange={(e) => setData('codigo', e.target.value)} />
                            </FormField>
                            <FormField label="Descripción" htmlFor="descripcion" error={errors.descripcion} required>
                                <Input id="descripcion" value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} />
                            </FormField>
                            <FormField label="Unidad" htmlFor="unidad" error={errors.unidad} required>
                                <Input id="unidad" value={data.unidad} onChange={(e) => setData('unidad', e.target.value)} />
                            </FormField>
                            <label className="flex cursor-pointer items-center gap-2">
                                <input type="checkbox" className="checkbox checkbox-sm" checked={data.activo} onChange={(e) => setData('activo', e.target.checked)} />
                                <span className="text-sm">Activo</span>
                            </label>

                            {/* El otro lado del ligado. Es una etiqueta, no un
                                campo: emparejar no se hace desde aquí. */}
                            <div>
                                <span className="label-text">Artículo en Almacén</span>
                                <p className="mt-1 text-sm">
                                    {producto.articulo ? (
                                        <Link
                                            href={`/admin/almacen/articulos/${producto.articulo.id}`}
                                            className="link link-hover"
                                        >
                                            <span className="font-mono">{producto.articulo.codigo ?? 'Sin código'}</span>
                                            <span className="text-base-content/60"> — {producto.articulo.descripcion}</span>
                                        </Link>
                                    ) : (
                                        <span className="text-base-content/50">
                                            Sin ligar: no lleva kardex. Es un servicio, un flete, o algo que nadie ha
                                            clasificado todavía.
                                        </span>
                                    )}
                                </p>
                            </div>
                            <div className="flex justify-end gap-2">
                                <Button variant="outline" asChild>
                                    <Link href="/admin/costos/productos">Volver</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Loader2Icon className="size-4 animate-spin" />}
                                    Guardar
                                </Button>
                            </div>
                        </form>
                    </div>

                    <div>
                        <h2 className="mb-3 text-lg font-medium">Histórico de precios</h2>
                        {precios.length === 0 ? (
                            <p className="text-sm text-base-content/50">Aún no hay precios registrados para este producto.</p>
                        ) : (
                            <div className="overflow-x-auto rounded-lg border border-base-300">
                                <table className="table table-xs">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Proveedor</th>
                                            <th className="text-right">Precio</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {precios.map((p) => (
                                            <tr key={p.id}>
                                                <td>{fmtDate(p.fecha)}</td>
                                                <td>{p.proveedor?.razon_social ?? '—'}</td>
                                                <td className="text-right">
                                                    {fmtMoney(p.precio)} <span className="text-[10px] text-base-content/50">{TIPO_MONEDA_LABELS[p.moneda]}</span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
