import { CodigoBarras } from '@/components/alm/codigo-barras';
import { MiniaturaArticulo } from '@/components/alm/miniatura-articulo';
import { ButtonLink } from '@/components/ui/button';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import {
    ACTIVOS_DEMO,
    ARTICULOS_DEMO,
    CLASES_ABC,
    ESTATUS_ACTIVO,
    EXISTENCIAS_DEMO,
    preciosDe,
    REGLAS_ABC,
    rutaUbicacion,
    TIPOS_ARTICULO,
    ubicacionesDe,
} from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeftIcon, PencilIcon, TagIcon, TrendingDownIcon, TrendingUpIcon } from 'lucide-react';
import { useState } from 'react';

const moneda = (n: number) => n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

type Props = {
    /** Id del artículo. La maqueta lo lee de la URL y busca en los datos demo. */
    articuloId?: number;
};

/**
 * Ficha del artículo: todo lo que hoy está repartido entre Compras y Almacén
 * junto en una pantalla — qué es, cuánto ha costado, dónde está y, si se
 * controla por pieza, cuáles piezas existen.
 */
export default function ArticuloShow({ articuloId }: Props) {
    const articulo = ARTICULOS_DEMO.find((a) => a.id === articuloId) ?? ARTICULOS_DEMO[0];

    const precios = preciosDe(articulo.id);
    const existencias = EXISTENCIAS_DEMO.filter((e) => e.producto === articulo.codigo);
    const piezas = ACTIVOS_DEMO.filter((p) => p.producto_id === articulo.id);
    const regla = REGLAS_ABC.find((r) => r.clasificacion === articulo.clasificacion_abc);

    // La ubicación es lo único que se corrige desde aquí: es por almacén, y
    // quien acomoda el material no debería tener que entrar a otra pantalla.
    const [ubicaciones, setUbicaciones] = useState<Record<string, string>>({});

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Inventarios', href: '/admin/almacen/existencias' },
        { title: 'Artículos', href: '/admin/almacen/articulos' },
        { title: articulo.codigo, href: `/admin/almacen/articulos/${articulo.id}` },
    ];

    // Cuánto se movió el precio contra la compra anterior. Es la pregunta que
    // se hace quien autoriza: "¿por qué ahora cuesta esto?".
    const variacion =
        precios.length >= 2 ? ((precios[0].precio - precios[1].precio) / precios[1].precio) * 100 : null;

    const barras = articulo.codigo_barras ?? articulo.codigo;

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
                                <span className="badge badge-sm">{TIPOS_ARTICULO[articulo.tipo]}</span>
                                {articulo.controla_inventario && (
                                    <span
                                        className={`badge badge-sm ${CLASES_ABC[articulo.clasificacion_abc]}`}
                                        title={regla ? `Se cuenta cada ${regla.frecuencia_dias} días` : undefined}
                                    >
                                        Clase {articulo.clasificacion_abc}
                                    </span>
                                )}
                                {articulo.area && (
                                    <span className="badge badge-sm badge-ghost">{articulo.area}</span>
                                )}
                            </div>
                            <p className="mt-1 text-lg">{articulo.descripcion}</p>
                            <p className="text-base-content/60 text-sm">
                                {[articulo.marca, articulo.modelo].filter(Boolean).join(' · ') || 'Sin marca ni modelo'}
                                {' · se mide en '}
                                {articulo.unidad}
                            </p>
                            {/* Sólo si está anotado: es dato de conciliación con el sistema
                                anterior, no algo que el almacenista necesite a diario. */}
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

                <div className="alert alert-warning mb-4">
                    <span>Vista de maqueta: los datos son de ejemplo, todavía no hay backend.</span>
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
                                    {precios[0].proveedor} · {precios[0].fecha}
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
                                {regla && (
                                    <p className="text-base-content/60 mt-2 text-sm">
                                        Se cuenta cada {regla.frecuencia_dias} días ({regla.etiqueta.toLowerCase()}).
                                    </p>
                                )}
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
                                        const clave = `${e.almacen}-${e.producto}`;
                                        const valor = ubicaciones[clave] ?? String(e.ubicacion_id ?? '');

                                        return (
                                            <tr key={clave} className="hover">
                                                <td>
                                                    <span className="badge badge-sm badge-ghost font-mono">
                                                        {e.almacen}
                                                    </span>
                                                </td>
                                                <td className="text-right font-mono">
                                                    {numero(e.cantidad)}
                                                    <span className="text-base-content/40"> {e.unidad}</span>
                                                </td>
                                                <td>
                                                    <Select
                                                        value={valor}
                                                        onValueChange={(v) =>
                                                            setUbicaciones((prev) => ({ ...prev, [clave]: v }))
                                                        }
                                                        className="select-sm"
                                                    >
                                                        <SelectItem value="">Sin acomodar</SelectItem>
                                                        {ubicacionesDe(e.almacen)
                                                            .filter((u) => u.activa)
                                                            .map((u) => (
                                                                <SelectItem key={u.id} value={String(u.id)}>
                                                                    {rutaUbicacion(u.id)}
                                                                </SelectItem>
                                                            ))}
                                                    </Select>
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
                                    const delta = anterior ? ((p.precio - anterior.precio) / anterior.precio) * 100 : null;

                                    return (
                                        <tr key={p.id} className="hover">
                                            <td className="font-mono text-xs">{p.fecha}</td>
                                            <td>{p.proveedor}</td>
                                            <td className="text-base-content/60 font-mono text-xs">{p.origen}</td>
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
                    <div className="rounded-box border-base-300 mt-4 border">
                        <div className="border-base-300 flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3">
                            <div>
                                <h2 className="font-medium">Piezas</h2>
                                <p className="text-base-content/60 text-sm">
                                    Cada una suma 1 a la existencia y lleva su propio número de serie y su etiqueta. El
                                    kardex por cantidad no cambia.
                                </p>
                            </div>
                            <Link href="/admin/almacen/activos" className="btn btn-ghost btn-sm">
                                Ver en Activos
                            </Link>
                        </div>
                        <table className="table table-sm">
                            <thead className="bg-base-200">
                                <tr>
                                    <th>No. de serie</th>
                                    <th>Código de barras</th>
                                    <th>Almacén</th>
                                    <th>Ubicación</th>
                                    <th>Estado</th>
                                    <th>Condición</th>
                                </tr>
                            </thead>
                            <tbody>
                                {piezas.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="text-base-content/50 py-6 text-center">
                                            Está marcado por pieza pero no se ha dado de alta ninguna.
                                        </td>
                                    </tr>
                                ) : (
                                    piezas.map((p) => (
                                        <tr key={p.id} className="hover">
                                            <td className="font-mono">{p.no_serie}</td>
                                            <td className="text-base-content/60 font-mono text-xs">
                                                {p.codigo_barras ?? '—'}
                                            </td>
                                            <td>
                                                <span className="badge badge-sm badge-ghost font-mono">{p.almacen}</span>
                                            </td>
                                            <td className="text-base-content/60 text-sm">
                                                {p.ubicacion ?? <span className="text-base-content/40">—</span>}
                                            </td>
                                            <td>
                                                <span className={`badge badge-sm ${ESTATUS_ACTIVO[p.estatus].clase}`}>
                                                    {ESTATUS_ACTIVO[p.estatus].etiqueta}
                                                </span>
                                            </td>
                                            <td className="text-base-content/60 text-sm">{p.condicion}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
