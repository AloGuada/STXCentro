import { CodigoBarras } from '@/components/alm/codigo-barras';
import { MiniaturaArticulo } from '@/components/alm/miniatura-articulo';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import {
    ACTIVOS_DEMO,
    ARTICULOS_DEMO,
    EXISTENCIAS_DEMO,
    REGLAS_ABC,
    TIPOS_ARTICULO,
    UNIDADES_ARTICULO,
} from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import type { AlmArea, AlmClasificacionAbc, AlmProductoTipo } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { LockIcon, TriangleAlertIcon } from 'lucide-react';
import { useState } from 'react';

const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

type Props = {
    /** Id del artículo. La maqueta lo lee de la URL y busca en los datos demo. */
    articuloId?: number;
    /** Catálogo real: es lo único de esta pantalla que no son datos de ejemplo. */
    areas: AlmArea[];
};

/**
 * Corrección de un artículo ya dado de alta.
 *
 * Es el alta con los campos llenos salvo por dos cosas: el código no se cambia
 * —ya salió impreso en etiquetas y anaqueles— y lo que aquí se toca puede
 * chocar con lo que el almacén ya movió, así que los interruptores que dejarían
 * el kardex inconsistente avisan antes de guardar.
 */
export default function ArticuloEdit({ articuloId, areas }: Props) {
    const articulo = ARTICULOS_DEMO.find((a) => a.id === articuloId) ?? ARTICULOS_DEMO[0];

    const existencias = EXISTENCIAS_DEMO.filter((e) => e.producto === articulo.codigo);
    const piezas = ACTIVOS_DEMO.filter((p) => p.producto_id === articulo.id);

    const [descripcion, setDescripcion] = useState(articulo.descripcion);
    const [unidad, setUnidad] = useState(articulo.unidad);
    const [marca, setMarca] = useState(articulo.marca ?? '');
    const [modelo, setModelo] = useState(articulo.modelo ?? '');
    const [idsteelex, setIdsteelex] = useState(articulo.idsteelex ?? '');
    // El demo guarda el nombre del área, no su id: se busca en el catálogo real
    // para preseleccionarla. Con backend llegará el `area_id` y esto se va.
    const [areaId, setAreaId] = useState(String(areas.find((a) => a.descripcion === articulo.area)?.id ?? ''));
    // Vacío significa "usa el código": sólo se llena cuando la caja ya trae uno
    // impreso de fábrica y no vale la pena taparlo con etiqueta nuestra.
    const [codigoBarras, setCodigoBarras] = useState(
        articulo.codigo_barras && articulo.codigo_barras !== articulo.codigo ? articulo.codigo_barras : '',
    );
    const [clasificacion, setClasificacion] = useState<AlmClasificacionAbc>(articulo.clasificacion_abc);
    const [imagen, setImagen] = useState<string | null>(articulo.imagen_url);
    const [tipo, setTipo] = useState<AlmProductoTipo>(articulo.tipo);
    const [requiereVerificacion, setRequiereVerificacion] = useState(articulo.requiere_verificacion);
    const [controlaInventario, setControlaInventario] = useState(articulo.controla_inventario);
    const [seControlaPorPieza, setSeControlaPorPieza] = useState(articulo.se_controla_por_pieza);
    const [stockMinimo, setStockMinimo] = useState(articulo.stock_minimo === null ? '' : String(articulo.stock_minimo));

    const barrasEfectivo = codigoBarras.trim() === '' ? articulo.codigo : codigoBarras.trim();
    const barrasOriginal = articulo.codigo_barras ?? articulo.codigo;
    const regla = REGLAS_ABC.find((r) => r.clasificacion === clasificacion);

    // Sacarlo del kardex teniendo existencia deja ese saldo sin dueño: nadie lo
    // va a corregir después, porque las pantallas de almacén ya no lo listan.
    const sacaDelKardexConSaldo = !controlaInventario && articulo.existencia_total > 0;
    // Las piezas ya dadas de alta traen serie, etiqueta y resguardo. Dejar de
    // controlar por pieza las deja huérfanas.
    const abandonaPiezas = !seControlaPorPieza && piezas.length > 0;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Inventarios', href: '/admin/almacen/existencias' },
        { title: 'Artículos', href: '/admin/almacen/articulos' },
        { title: articulo.codigo, href: `/admin/almacen/articulos/${articulo.id}` },
        { title: 'Editar', href: `/admin/almacen/articulos/${articulo.id}/edit` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${articulo.codigo}`} />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">
                        Editar <span className="font-mono">{articulo.codigo}</span>
                    </h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Se corrige en el catálogo que comparten Compras y Almacén: el cambio se ve de los dos lados.
                    </p>
                </div>

                <div className="alert alert-warning mb-4">
                    <span>Vista de maqueta: el formulario todavía no guarda nada.</span>
                </div>

                <form onSubmit={(e) => e.preventDefault()} className="max-w-3xl space-y-6">
                    <div className="rounded-box border-base-300 border p-4">
                        <h2 className="mb-4 font-medium">Identificación</h2>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <FormField
                                label="Código"
                                htmlFor="codigo"
                                description="No se cambia: ya salió impreso en etiquetas y anaqueles."
                            >
                                <label className="input input-bordered flex items-center gap-2 opacity-70">
                                    <LockIcon className="text-base-content/40 size-4" />
                                    <input
                                        id="codigo"
                                        value={articulo.codigo}
                                        readOnly
                                        className="grow font-mono"
                                        aria-label="Código del artículo"
                                    />
                                </label>
                            </FormField>

                            <FormField
                                label="Unidad"
                                htmlFor="unidad"
                                description={
                                    articulo.existencia_total > 0
                                        ? `Cambiarla no reconvierte lo que ya está en piso (${numero(articulo.existencia_total)} ${articulo.unidad}).`
                                        : undefined
                                }
                                required
                            >
                                <Select id="unidad" value={unidad} onValueChange={setUnidad} placeholder="¿En qué se mide?">
                                    {UNIDADES_ARTICULO.map((u) => (
                                        <SelectItem key={u} value={u}>
                                            {u}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField label="Descripción" htmlFor="descripcion" className="md:col-span-2" required>
                                <Input
                                    id="descripcion"
                                    value={descripcion}
                                    onChange={(e) => setDescripcion(e.target.value)}
                                    placeholder='Tornillo A325 3/4" x 2"'
                                />
                            </FormField>

                            <FormField
                                label="Marca"
                                htmlFor="marca"
                                description="Opcional. El material a granel no la lleva."
                            >
                                <Input
                                    id="marca"
                                    value={marca}
                                    onChange={(e) => setMarca(e.target.value)}
                                    placeholder="DeWalt"
                                />
                            </FormField>

                            <FormField
                                label="Modelo"
                                htmlFor="modelo"
                                description="Opcional. Es lo que se pide al reponer una herramienta."
                            >
                                <Input
                                    id="modelo"
                                    value={modelo}
                                    onChange={(e) => setModelo(e.target.value)}
                                    placeholder="DWE4120"
                                />
                            </FormField>

                            <FormField
                                label="Área"
                                htmlFor="area_id"
                                description={
                                    areas.length === 0
                                        ? 'No hay áreas dadas de alta todavía: se capturan en Almacén → Áreas.'
                                        : 'A qué parte de la operación pertenece. Sale del catálogo de áreas.'
                                }
                            >
                                <Select
                                    id="area_id"
                                    value={areaId}
                                    onValueChange={setAreaId}
                                    placeholder="¿A qué área pertenece?"
                                    disabled={areas.length === 0}
                                >
                                    <SelectItem value="">Sin área</SelectItem>
                                    {areas.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)}>
                                            {a.descripcion}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField
                                label="ID Steelex"
                                htmlFor="idsteelex"
                                description="Opcional. Cómo se llama este artículo en Steelex, para poder conciliar mientras los dos sistemas convivan. Texto libre, hasta 150 caracteres."
                                className="md:col-span-2"
                            >
                                <Input
                                    id="idsteelex"
                                    value={idsteelex}
                                    onChange={(e) => setIdsteelex(e.target.value)}
                                    maxLength={150}
                                    placeholder="MAT-000412"
                                />
                            </FormField>

                            <FormField
                                label="Imagen"
                                htmlFor="imagen"
                                description="Opcional. Sirve para reconocer el artículo sin leer la descripción. Pasa el mouse encima para verla completa."
                                className="md:col-span-2"
                            >
                                <div className="flex items-center gap-4">
                                    <MiniaturaArticulo
                                        url={imagen}
                                        descripcion={descripcion}
                                        className="size-20"
                                        iconClassName="size-6"
                                    />
                                    <div className="flex-1">
                                        <input
                                            id="imagen"
                                            type="file"
                                            accept="image/*"
                                            className="file-input file-input-bordered file-input-sm w-full max-w-xs"
                                            onChange={(e) => {
                                                const archivo = e.target.files?.[0];

                                                // Sin archivo no se borra la que ya tenía: cancelar el
                                                // diálogo no es lo mismo que quitar la foto.
                                                if (archivo) {
                                                    setImagen(URL.createObjectURL(archivo));
                                                }
                                            }}
                                        />
                                        {imagen && (
                                            <button
                                                type="button"
                                                className="btn btn-ghost btn-xs mt-2"
                                                onClick={() => setImagen(null)}
                                            >
                                                Quitar imagen
                                            </button>
                                        )}
                                    </div>
                                </div>
                            </FormField>
                        </div>
                    </div>

                    <div className="rounded-box border-base-300 border p-4">
                        <h2 className="mb-4 font-medium">Código de barras</h2>

                        <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                            <FormField
                                label="Código de barras"
                                htmlFor="codigo_barras"
                                description="Déjalo vacío y se usa el código del artículo. Llénalo sólo si la caja ya trae uno impreso de fábrica."
                            >
                                <Input
                                    id="codigo_barras"
                                    value={codigoBarras}
                                    onChange={(e) => setCodigoBarras(e.target.value.toUpperCase())}
                                    placeholder={articulo.codigo}
                                    className="font-mono"
                                    disabled={!controlaInventario}
                                />
                            </FormField>

                            <div>
                                <span className="label label-text text-xs">Así se va a imprimir</span>
                                {controlaInventario ? (
                                    <div className="rounded-box border-base-300 border bg-white p-3">
                                        <CodigoBarras valor={barrasEfectivo} altura={44} />
                                    </div>
                                ) : (
                                    <p className="text-base-content/50 text-sm">
                                        Sin kardex no hay nada que escanear: un flete no se guarda en un anaquel.
                                    </p>
                                )}
                            </div>
                        </div>

                        {/* Cambiarlo invalida lo ya pegado: quien escanee la etiqueta vieja
                            no encuentra el artículo. */}
                        {controlaInventario && barrasEfectivo !== barrasOriginal && (
                            <p className="text-warning mt-3 flex items-start gap-2 text-sm">
                                <TriangleAlertIcon className="mt-0.5 size-4 shrink-0" />
                                <span>
                                    Las etiquetas ya impresas con <span className="font-mono">{barrasOriginal}</span>{' '}
                                    dejan de servir: hay que volver a imprimirlas.
                                </span>
                            </p>
                        )}
                    </div>

                    <div className="rounded-box border-base-300 border p-4">
                        <h2 className="mb-4 font-medium">Comportamiento en almacén</h2>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <FormField
                                label="Tipo"
                                htmlFor="tipo"
                                description="Define si se gasta o si sale y regresa."
                                required
                            >
                                <Select
                                    id="tipo"
                                    value={tipo}
                                    onValueChange={(v) => {
                                        const nuevo = v as AlmProductoTipo;
                                        setTipo(nuevo);

                                        // Serializar un insumo no tiene sentido: se gasta.
                                        if (nuevo === 'insumo') {
                                            setSeControlaPorPieza(false);
                                        }
                                    }}
                                >
                                    {Object.entries(TIPOS_ARTICULO).map(([valor, etiqueta]) => (
                                        <SelectItem key={valor} value={valor}>
                                            {etiqueta}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField
                                label="Clase de conteo"
                                htmlFor="clasificacion_abc"
                                description={regla ? `${regla.etiqueta}: ${regla.descripcion}` : undefined}
                                required
                            >
                                <Select
                                    id="clasificacion_abc"
                                    value={clasificacion}
                                    onValueChange={(v) => setClasificacion(v as AlmClasificacionAbc)}
                                    disabled={!controlaInventario}
                                >
                                    {REGLAS_ABC.map((r) => (
                                        <SelectItem key={r.clasificacion} value={r.clasificacion}>
                                            {r.clasificacion} — {r.etiqueta} (cada {r.frecuencia_dias} días)
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField
                                label="Stock mínimo"
                                htmlFor="stock_minimo"
                                description="Opcional. Debajo de esto, la existencia se marca en rojo."
                            >
                                <Input
                                    id="stock_minimo"
                                    type="number"
                                    min="0"
                                    step="0.001"
                                    value={stockMinimo}
                                    onChange={(e) => setStockMinimo(e.target.value)}
                                    placeholder="0"
                                    disabled={!controlaInventario}
                                />
                            </FormField>

                            <div className="md:col-span-2">
                                <label className="flex cursor-pointer items-start gap-3">
                                    <input
                                        type="checkbox"
                                        className="checkbox checkbox-sm mt-0.5"
                                        checked={seControlaPorPieza}
                                        onChange={(e) => setSeControlaPorPieza(e.target.checked)}
                                        disabled={tipo === 'insumo'}
                                    />
                                    <span>
                                        <span className="font-medium">Se controla por pieza</span>
                                        <span className="text-base-content/60 block text-sm">
                                            Cada unidad se da de alta con su número de serie y su propia etiqueta, y se
                                            presta bajo resguardo. Sin esto el kardex sabe cuántas pulidoras salieron,
                                            pero no quién tiene cuál. Sólo aplica a los activos: un insumo se gasta.
                                        </span>
                                    </span>
                                </label>

                                {abandonaPiezas && (
                                    <p className="text-warning mt-2 flex items-start gap-2 text-sm">
                                        <TriangleAlertIcon className="mt-0.5 size-4 shrink-0" />
                                        <span>
                                            Ya hay {piezas.length} pieza(s) con número de serie y resguardo. Al quitarlo
                                            se pierde el rastro de quién tiene cuál: primero hay que darlas de baja en
                                            Activos.
                                        </span>
                                    </p>
                                )}
                            </div>

                            <div className="md:col-span-2">
                                <label className="flex cursor-pointer items-start gap-3">
                                    <input
                                        type="checkbox"
                                        className="checkbox checkbox-sm mt-0.5"
                                        checked={requiereVerificacion}
                                        onChange={(e) => setRequiereVerificacion(e.target.checked)}
                                    />
                                    <span>
                                        <span className="font-medium">Inspección de mantenimiento</span>
                                        <span className="text-base-content/60 block text-sm">
                                            Márcalo para el equipo cuyo mantenimiento hay que revisar al recibirlo (una
                                            pulidora sí, un andamio no). La entrada queda detenida hasta que alguien
                                            palomee esa revisión.
                                        </span>
                                    </span>
                                </label>
                            </div>

                            <div className="md:col-span-2">
                                <label className="flex cursor-pointer items-start gap-3">
                                    <input
                                        type="checkbox"
                                        className="checkbox checkbox-sm mt-0.5"
                                        checked={controlaInventario}
                                        onChange={(e) => setControlaInventario(e.target.checked)}
                                    />
                                    <span>
                                        <span className="font-medium">Lleva kardex</span>
                                        <span className="text-base-content/60 block text-sm">
                                            Desmárcalo para lo que se compra pero no se almacena (fletes, servicios,
                                            maniobras). Sin kardex no aparece en existencias ni en los movimientos, y
                                            tampoco entra a los conteos ni a las etiquetas.
                                        </span>
                                    </span>
                                </label>

                                {sacaDelKardexConSaldo && (
                                    <p className="text-warning mt-2 flex items-start gap-2 text-sm">
                                        <TriangleAlertIcon className="mt-0.5 size-4 shrink-0" />
                                        <span>
                                            Hay {numero(articulo.existencia_total)} {articulo.unidad} en{' '}
                                            {existencias.length} almacén(es). Al quitarlo, ese saldo deja de aparecer en
                                            existencias sin que nadie lo haya dado de baja: primero hay que sacarlo con
                                            una salida o un ajuste.
                                        </span>
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>

                    <p className="text-base-content/60 text-sm">
                        El <strong>precio</strong> no se captura aquí: se va formando solo con lo que cotizan los
                        proveedores en Compras, y la ficha del artículo muestra ese histórico. La{' '}
                        <strong>ubicación</strong> tampoco, porque es por almacén — se corrige en la ficha, ahí donde se
                        ve qué cantidad hay en cada uno.
                    </p>

                    <div className="flex justify-end gap-2">
                        <Button variant="outline" asChild>
                            <Link href={`/admin/almacen/articulos/${articulo.id}`}>Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled>
                            Guardar cambios
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
