import { CodigoBarras } from '@/components/alm/codigo-barras';
import { MiniaturaArticulo } from '@/components/alm/miniatura-articulo';
import { ButtonLink } from '@/components/ui/button';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { AlmArticulo, AlmArticuloExistencia, AlmArticuloPrecio } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import { ArrowLeftIcon, PencilIcon, TagIcon, TrendingDownIcon, TrendingUpIcon } from 'lucide-react';

const moneda = (n: number) => n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

const CLASE_ABC: Record<string, string> = {
    A: 'badge-error',
    B: 'badge-warning',
    C: 'badge-ghost',
};

const FRECUENCIA_ABC: Record<string, string> = {
    A: 'cada 30 días',
    B: 'cada 90 días',
    C: 'cada 180 días',
};

type Props = {
    articulo: AlmArticulo;
    existencias: AlmArticuloExistencia[];
    precios: AlmArticuloPrecio[];
    /** Los lugares activos de cada almacén donde hay saldo, por `almacen_id`. */
    ubicaciones: Record<number, { id: number; ruta: string }[]>;
};

/**
 * Ficha del artículo: lo que hoy está repartido entre Compras y Almacén junto en
 * una pantalla — qué es, cuánto ha costado y dónde está.
 */
export default function ArticuloShow({ articulo, existencias, precios, ubicaciones }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Inventarios', href: '/admin/almacen/existencias' },
        { title: 'Artículos', href: '/admin/almacen/articulos' },
        { title: articulo.codigo, href: `/admin/almacen/articulos/${articulo.id}` },
    ];

    // Cuánto se movió el precio contra la compra anterior. Es la pregunta que se
    // hace quien autoriza: «¿por qué ahora cuesta esto?».
    const variacion =
        precios.length >= 2 && precios[1].precio !== 0
            ? ((precios[0].precio - precios[1].precio) / precios[1].precio) * 100
            : null;

    const barras = articulo.codigo_barras ?? articulo.codigo;

    const acomodar = (existenciaId: number, ubicacionId: string) =>
        router.patch(
            `/admin/almacen/existencias/${existenciaId}/ubicacion`,
            { ubicacion_id: ubicacionId === '' ? null : Number(ubicacionId) },
            { preserveScroll: true },
        );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${articulo.codigo} — ${articulo.descripcion}`} />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div className="flex items-start gap-4">
                        <MiniaturaArticulo
                            url={articulo.imagen_url}
                            descripcion={articulo.descripcion}
                            className="size-16"
                            iconClassName="size-6"
                        />
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="font-mono text-2xl font-semibold">{articulo.codigo}</h1>
                                <span className="badge badge-sm">
                                    {articulo.tipo === 'activo' ? 'Activo' : 'Insumo'}
                                </span>
                                {articulo.controla_inventario && (
                                    <span
                                        className={`badge badge-sm ${CLASE_ABC[articulo.clasificacion_abc] ?? 'badge-ghost'}`}
                                        title={`Se cuenta ${FRECUENCIA_ABC[articulo.clasificacion_abc] ?? ''}`}
                                    >
                                        Clase {articulo.clasificacion_abc}
                                    </span>
                                )}
                                {articulo.area && <span className="badge badge-sm badge-ghost">{articulo.area}</span>}
                            </div>
                            <p className="mt-1 text-lg">{articulo.descripcion}</p>
                            <p className="text-base-content/60 text-sm">Se mide en {articulo.unidad}</p>
                            {/* Sólo si está anotado: es dato de conciliación con el
                                sistema anterior, no algo que se necesite a diario. */}
                            {articulo.idsteelex && (
                                <p className="text-base-content/60 mt-1 text-sm">
                                    En Steelex: <span className="font-mono">{articulo.idsteelex}</span>
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="flex gap-2">
                        <ButtonLink href="/admin/almacen/articulos" variant="outline">
                            <ArrowLeftIcon className="size-4" />
                            Volver
                        </ButtonLink>
                        <ButtonLink href={`/admin/almacen/articulos/${articulo.id}/edit`} variant="outline">
                            <PencilIcon className="size-4" />
                            Editar
                        </ButtonLink>
                        {articulo.controla_inventario && (
                            <ButtonLink href={`/admin/almacen/etiquetas?articulo=${articulo.id}`} variant="primary">
                                <TagIcon className="size-4" />
                                Imprimir etiquetas
                            </ButtonLink>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div className="rounded-box border-base-300 border p-4">
                        <h2 className="mb-3 font-medium">Código de barras</h2>
                        <div className="rounded-box border-base-300 border bg-white p-4">
                            <CodigoBarras valor={barras} altura={56} />
                        </div>
                        <p className="text-base-content/60 mt-3 text-sm">
                            {articulo.codigo_barras && articulo.codigo_barras !== articulo.codigo
                                ? 'Es el que traía impreso el fabricante: se respeta para no pegarle encima una etiqueta nuestra.'
                                : 'Se generó del código del artículo. Si la caja ya trae uno de fábrica, se puede sustituir.'}
                        </p>
                    </div>

                    <div className="rounded-box border-base-300 border p-4">
                        <h2 className="mb-3 font-medium">Último precio</h2>
                        {precios.length === 0 ? (
                            <p className="text-base-content/50 text-sm">
                                Todavía no se ha cotizado ni comprado. El histórico se llena solo con las cotizaciones
                                de Compras.
                            </p>
                        ) : (
                            <>
                                <p className="font-mono text-3xl">{moneda(precios[0].precio)}</p>
                                <p className="text-base-content/60 mt-1 text-sm">
                                    {precios[0].proveedor ?? 'Sin proveedor'} · {precios[0].fecha}
                                </p>
                                {variacion !== null && (
                                    <p
                                        className={`mt-2 flex items-center gap-1 text-sm ${
                                            variacion > 0 ? 'text-error' : 'text-success'
                                        }`}
                                    >
                                        {variacion > 0 ? (
                                            <TrendingUpIcon className="size-4" />
                                        ) : (
                                            <TrendingDownIcon className="size-4" />
                                        )}
                                        {variacion > 0 ? '+' : ''}
                                        {variacion.toFixed(1)}% contra la compra anterior
                                    </p>
                                )}
                            </>
                        )}
                    </div>

                    <div className="rounded-box border-base-300 border p-4">
                        <h2 className="mb-3 font-medium">Existencia total</h2>
                        {articulo.controla_inventario ? (
                            <>
                                <p className="font-mono text-3xl">
                                    {numero(articulo.existencia_total)}
                                    <span className="text-base-content/40 ml-1 text-base">{articulo.unidad}</span>
                                </p>
                                <p className="text-base-content/60 mt-1 text-sm">
                                    Repartida en {existencias.length} almacén(es)
                                    {articulo.stock_minimo !== null && ` · mínimo ${numero(articulo.stock_minimo)}`}
                                </p>
                                <p className="text-base-content/60 mt-2 text-sm">
                                    Se cuenta {FRECUENCIA_ABC[articulo.clasificacion_abc] ?? ''}.
                                </p>
                            </>
                        ) : (
                            <p className="text-base-content/50 text-sm">
                                No lleva kardex: se compra pero no se almacena, así que no hay existencia que mostrar.
                            </p>
                        )}
                    </div>
                </div>

                {articulo.controla_inventario && (
                    <div className="rounded-box border-base-300 mt-4 border">
                        <div className="border-base-300 border-b px-4 py-3">
                            <h2 className="font-medium">Dónde está</h2>
                            <p className="text-base-content/60 text-sm">
                                La ubicación es por almacén: el mismo artículo puede vivir en un rack de planta y en un
                                contenedor de obra.
                            </p>
                        </div>
                        <table className="table table-sm">
                            <thead className="bg-base-200">
                                <tr>
                                    <th>Almacén</th>
                                    <th className="text-right">Existencia</th>
                                    <th className="w-72">Ubicación</th>
                                </tr>
                            </thead>
                            <tbody>
                                {existencias.length === 0 ? (
                                    <tr>
                                        <td colSpan={3} className="text-base-content/50 py-6 text-center">
                                            No hay existencia de este artículo en ningún almacén.
                                        </td>
                                    </tr>
                                ) : (
                                    existencias.map((e) => {
                                        const lugares = ubicaciones[e.almacen_id] ?? [];

                                        return (
                                            <tr key={e.id} className="hover">
                                                <td>
                                                    <span className="badge badge-sm badge-ghost font-mono">
                                                        {e.almacen}
                                                    </span>
                                                    {e.obra && (
                                                        <span className="text-base-content/60 ml-2 text-xs">
                                                            {e.obra}
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="text-right font-mono">
                                                    {numero(e.cantidad)}
                                                    <span className="text-base-content/40"> {articulo.unidad}</span>
                                                </td>
                                                <td>
                                                    {lugares.length === 0 ? (
                                                        <span className="text-base-content/50 text-sm">
                                                            Este almacén todavía no tiene ubicaciones dadas de alta.
                                                        </span>
                                                    ) : (
                                                        <Select
                                                            value={String(e.ubicacion_id ?? '')}
                                                            onValueChange={(v) => acomodar(e.id, v)}
                                                            className="select-sm"
                                                        >
                                                            <SelectItem value="">Sin acomodar</SelectItem>
                                                            {lugares.map((u) => (
                                                                <SelectItem key={u.id} value={String(u.id)}>
                                                                    {u.ruta}
                                                                </SelectItem>
                                                            ))}
                                                        </Select>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>
                )}

                <div className="rounded-box border-base-300 mt-4 border">
                    <div className="border-base-300 border-b px-4 py-3">
                        <h2 className="font-medium">Historial de precios</h2>
                        <p className="text-base-content/60 text-sm">
                            Se llena solo desde las cotizaciones y las órdenes de compra: aquí no se captura nada.
                        </p>
                    </div>
                    <table className="table table-sm">
                        <thead className="bg-base-200">
                            <tr>
                                <th>Fecha</th>
                                <th>Proveedor</th>
                                <th>Origen</th>
                                <th className="text-right">Precio</th>
                                <th className="text-right">Variación</th>
                            </tr>
                        </thead>
                        <tbody>
                            {precios.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="text-base-content/50 py-6 text-center">
                                        Sin precios registrados.
                                    </td>
                                </tr>
                            ) : (
                                precios.map((p, indice) => {
                                    const anterior = precios[indice + 1];
                                    const delta =
                                        anterior && anterior.precio !== 0
                                            ? ((p.precio - anterior.precio) / anterior.precio) * 100
                                            : null;

                                    return (
                                        <tr key={p.id} className="hover">
                                            <td className="font-mono text-xs">{p.fecha}</td>
                                            <td>{p.proveedor ?? <span className="text-base-content/40">—</span>}</td>
                                            <td className="text-base-content/60 font-mono text-xs">
                                                {p.requisicion_id ? `Requisición #${p.requisicion_id}` : 'Captura'}
                                            </td>
                                            <td className="text-right font-mono">{moneda(p.precio)}</td>
                                            <td className="text-right font-mono text-xs">
                                                {delta === null ? (
                                                    <span className="text-base-content/40">—</span>
                                                ) : (
                                                    <span className={delta > 0 ? 'text-error' : 'text-success'}>
                                                        {delta > 0 ? '+' : ''}
                                                        {delta.toFixed(1)}%
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                {articulo.se_controla_por_pieza && (
                    <div className="alert alert-info mt-4">
                        <span>
                            Este artículo se controla por pieza. El padrón de piezas —número de serie, marca, modelo y
                            quién trae cuál— vive en Activos, que todavía no tiene backend.
                        </span>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
