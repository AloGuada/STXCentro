import { FormField } from '@/components/form';
import { CodigoBarras } from '@/components/alm/codigo-barras';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { REGLAS_ABC, siguienteCodigoArticulo, TIPOS_ARTICULO, UNIDADES_ARTICULO } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import type { AlmArea, AlmClasificacionAbc, AlmProductoTipo } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { ImageIcon, LockIcon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Artículos', href: '/admin/almacen/articulos' },
    { title: 'Nuevo', href: '/admin/almacen/articulos/create' },
];

type Props = {
    /** Catálogo real: es lo único de esta pantalla que no son datos de ejemplo. */
    areas: AlmArea[];
};

export default function ArticuloCreate({ areas }: Props) {
    // El código no se teclea: lo pone el sistema al guardar. Se muestra desde
    // ahora para que quien da de alta sepa con qué va a quedar etiquetado.
    const codigo = siguienteCodigoArticulo();

    const [descripcion, setDescripcion] = useState('');
    const [unidad, setUnidad] = useState('');
    // Sólo se anota, no se valida ni se cruza: es el nombre del artículo en el
    // sistema anterior, para conciliar mientras los dos convivan.
    const [idsteelex, setIdsteelex] = useState('');
    const [areaId, setAreaId] = useState('');
    // Vacío significa "usa el código": sólo se llena cuando la caja ya trae uno
    // impreso de fábrica y no vale la pena taparlo con etiqueta nuestra.
    const [codigoBarras, setCodigoBarras] = useState('');
    const [clasificacion, setClasificacion] = useState<AlmClasificacionAbc>('C');
    const [imagen, setImagen] = useState<string | null>(null);
    const [tipo, setTipo] = useState<AlmProductoTipo>('insumo');
    const [requiereVerificacion, setRequiereVerificacion] = useState(false);
    const [controlaInventario, setControlaInventario] = useState(true);
    const [seControlaPorPieza, setSeControlaPorPieza] = useState(false);
    const [stockMinimo, setStockMinimo] = useState('');

    const barrasEfectivo = codigoBarras.trim() === '' ? codigo : codigoBarras.trim();
    const regla = REGLAS_ABC.find((r) => r.clasificacion === clasificacion);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo artículo" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Nuevo artículo</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Se da de alta en el catálogo que comparten Compras y Almacén: el mismo código sirve para
                        cotizar y para el kardex.
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
                                description="Lo asigna el sistema al guardar: un consecutivo, sin familias."
                            >
                                <label className="input input-bordered flex items-center gap-2 opacity-70">
                                    <LockIcon className="text-base-content/40 size-4" />
                                    <input
                                        id="codigo"
                                        value={codigo}
                                        readOnly
                                        className="grow font-mono"
                                        aria-label="Código asignado automáticamente"
                                    />
                                </label>
                            </FormField>

                            <FormField label="Unidad" htmlFor="unidad" required>
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
                                description="Opcional. Sirve para reconocer el artículo sin leer la descripción."
                                className="md:col-span-2"
                            >
                                <div className="flex items-center gap-4">
                                    {imagen ? (
                                        <img
                                            src={imagen}
                                            alt="Vista previa"
                                            className="border-base-300 size-20 rounded border object-cover"
                                        />
                                    ) : (
                                        <div className="border-base-300 text-base-content/30 flex size-20 items-center justify-center rounded border border-dashed">
                                            <ImageIcon className="size-6" />
                                        </div>
                                    )}
                                    <div className="flex-1">
                                        <input
                                            id="imagen"
                                            type="file"
                                            accept="image/*"
                                            className="file-input file-input-bordered file-input-sm w-full max-w-xs"
                                            onChange={(e) => {
                                                const archivo = e.target.files?.[0];
                                                setImagen(archivo ? URL.createObjectURL(archivo) : null);
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
                                    placeholder={codigo}
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
                                            Cada unidad se da de alta en Activos con su número de serie —y ahí mismo su
                                            marca, su modelo y su id de mantenimiento—, lleva su propia etiqueta y se
                                            presta bajo resguardo. Sin esto el kardex sabe cuántas pulidoras salieron,
                                            pero no quién tiene cuál. Sólo aplica a los activos: un insumo se gasta.
                                        </span>
                                    </span>
                                </label>
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
                            </div>
                        </div>
                    </div>

                    <p className="text-base-content/60 text-sm">
                        El <strong>precio</strong> no se captura aquí: se va formando solo con lo que cotizan los
                        proveedores en Compras, y la ficha del artículo muestra ese histórico. La{' '}
                        <strong>ubicación</strong> tampoco, porque es por almacén — el mismo tornillo puede vivir en
                        el Rack A-1 de planta y en un contenedor de obra.
                    </p>

                    <div className="flex justify-end gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/admin/almacen/articulos">Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled>
                            Guardar artículo
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
