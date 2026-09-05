import { CodigoBarras } from '@/components/alm/codigo-barras';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import type { AlmArea, AlmArticulo, AlmClasificacionAbc, AlmOpcion, AlmOpcionClase, AlmProductoTipo } from '@/types/models';
import { Link, useForm } from '@inertiajs/react';
import { ImageIcon, LockIcon } from 'lucide-react';
import { useState } from 'react';

type Props = {
    /** Sin artículo es un alta; con él, la corrección de uno que ya existe. */
    articulo?: AlmArticulo;
    /** El que le tocaría al siguiente. Sólo en el alta. */
    codigoSugerido?: string;
    areas: AlmArea[];
    unidades: string[];
    tipos: AlmOpcion[];
    clases: AlmOpcionClase[];
};

/**
 * El formulario del catálogo, compartido por el alta y la corrección.
 *
 * Compartido a propósito: son veinte campos con reglas cruzadas —un insumo no se
 * sigue pieza por pieza, lo que no lleva kardex no tiene clase de conteo— y dos
 * copias se desincronizarían a la primera regla nueva.
 */
export function ArticuloForm({ articulo, codigoSugerido, areas, unidades, tipos, clases }: Props) {
    const esAlta = articulo === undefined;

    const form = useForm({
        descripcion: articulo?.descripcion ?? '',
        unidad: articulo?.unidad ?? '',
        idsteelex: articulo?.idsteelex ?? '',
        area_id: articulo?.area_id === null || articulo?.area_id === undefined ? '' : String(articulo.area_id),
        codigo_barras: articulo?.codigo_barras === articulo?.codigo ? '' : (articulo?.codigo_barras ?? ''),
        tipo: (articulo?.tipo ?? 'insumo') as AlmProductoTipo,
        clasificacion_abc: (articulo?.clasificacion_abc ?? 'C') as AlmClasificacionAbc,
        se_controla_por_pieza: articulo?.se_controla_por_pieza ?? false,
        requiere_verificacion: articulo?.requiere_verificacion ?? false,
        stock_minimo: articulo?.stock_minimo === null || articulo?.stock_minimo === undefined ? '' : String(articulo.stock_minimo),
        imagen: null as File | null,
    });

    const [vistaPrevia, setVistaPrevia] = useState<string | null>(articulo?.imagen_url ?? null);

    // El código no se teclea nunca: en el alta lo pone el sistema al guardar, y
    // en la corrección ya se usó para etiquetar cajas y sellar movimientos.
    const codigo = articulo?.codigo ?? codigoSugerido ?? '';
    const barrasEfectivo = form.data.codigo_barras.trim() === '' ? codigo : form.data.codigo_barras.trim();
    const regla = clases.find((c) => c.value === form.data.clasificacion_abc);

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();

        // `useForm` manda multipart en cuanto detecta un File, y PUT con archivo
        // no llega: por eso la corrección va por POST con _method.
        if (esAlta) {
            form.post('/admin/almacen/articulos');

            return;
        }

        form.transform((datos) => ({ ...datos, _method: 'put' }));
        form.post(`/admin/almacen/articulos/${articulo.id}`);
    };

    const elegirImagen = (archivo: File | null) => {
        form.setData('imagen', archivo);
        setVistaPrevia(archivo ? URL.createObjectURL(archivo) : (articulo?.imagen_url ?? null));
    };

    return (
        <form onSubmit={enviar} className="max-w-3xl space-y-6">
            <div className="rounded-box border-base-300 border p-4">
                <h2 className="mb-4 font-medium">Identificación</h2>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <FormField
                        label="Código"
                        htmlFor="codigo"
                        description={
                            esAlta
                                ? 'Lo asigna el sistema al guardar: un consecutivo, sin familias.'
                                : 'No se corrige: con él se etiquetaron cajas y se sellaron movimientos.'
                        }
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

                    {/* Sólo en la corrección: en el alta el producto se crea
                        junto con el artículo, así que no hay nada que enseñar
                        todavía. Es una etiqueta, no un campo: el emparejado no
                        se corrige desde aquí. */}
                    {!esAlta && (
                        <div className="form-control w-full">
                            <span className="label-text">Producto en Compras</span>
                            <p className="mt-2 text-sm">
                                {articulo.producto ? (
                                    <>
                                        <span className="font-mono">{articulo.producto.codigo ?? 'Sin código'}</span>
                                        <span className="text-base-content/60"> — {articulo.producto.descripcion}</span>
                                    </>
                                ) : (
                                    <span className="text-base-content/50">
                                        Sin ligar: todavía no se empareja con ningún producto de Compras.
                                    </span>
                                )}
                            </p>
                        </div>
                    )}

                    <FormField label="Unidad" htmlFor="unidad" error={form.errors.unidad} required>
                        <Select
                            id="unidad"
                            value={form.data.unidad}
                            onValueChange={(v) => form.setData('unidad', v)}
                            placeholder="¿En qué se mide?"
                        >
                            {unidades.map((u) => (
                                <SelectItem key={u} value={u}>
                                    {u}
                                </SelectItem>
                            ))}
                        </Select>
                    </FormField>

                    <FormField
                        label="Descripción"
                        htmlFor="descripcion"
                        error={form.errors.descripcion}
                        className="md:col-span-2"
                        required
                    >
                        <Input
                            id="descripcion"
                            value={form.data.descripcion}
                            onChange={(e) => form.setData('descripcion', e.target.value)}
                            placeholder='Tornillo A325 3/4" x 2"'
                        />
                    </FormField>

                    <FormField
                        label="Área"
                        htmlFor="area_id"
                        error={form.errors.area_id}
                        description={
                            areas.length === 0
                                ? 'No hay áreas dadas de alta todavía: se capturan en Almacén → Áreas.'
                                : 'A qué parte de la operación pertenece. Sale del catálogo de áreas.'
                        }
                    >
                        <Select
                            id="area_id"
                            value={form.data.area_id}
                            onValueChange={(v) => form.setData('area_id', v)}
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
                        error={form.errors.idsteelex}
                        description="Opcional. Cómo se llama este artículo en Steelex, para poder conciliar mientras los dos sistemas convivan. Texto libre, hasta 150 caracteres."
                    >
                        <Input
                            id="idsteelex"
                            value={form.data.idsteelex}
                            onChange={(e) => form.setData('idsteelex', e.target.value)}
                            maxLength={150}
                            placeholder="MAT-000412"
                        />
                    </FormField>

                    <FormField
                        label="Imagen"
                        htmlFor="imagen"
                        error={form.errors.imagen}
                        description="Opcional. Sirve para reconocer el artículo sin leer la descripción."
                        className="md:col-span-2"
                    >
                        <div className="flex items-center gap-4">
                            {vistaPrevia ? (
                                <img
                                    src={vistaPrevia}
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
                                    onChange={(e) => elegirImagen(e.target.files?.[0] ?? null)}
                                />
                                {form.data.imagen && (
                                    <button
                                        type="button"
                                        className="btn btn-ghost btn-xs mt-2"
                                        onClick={() => elegirImagen(null)}
                                    >
                                        Descartar la imagen nueva
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
                        error={form.errors.codigo_barras}
                        description="Déjalo vacío y se usa el código del artículo. Llénalo sólo si la caja ya trae uno impreso de fábrica."
                    >
                        <Input
                            id="codigo_barras"
                            value={form.data.codigo_barras}
                            onChange={(e) => form.setData('codigo_barras', e.target.value.toUpperCase())}
                            placeholder={codigo}
                            className="font-mono"
                        />
                    </FormField>

                    <div>
                        <span className="label label-text text-xs">Así se va a imprimir</span>
                        <div className="rounded-box border-base-300 border bg-white p-3">
                            <CodigoBarras valor={barrasEfectivo} altura={44} />
                        </div>
                    </div>
                </div>
            </div>

            <div className="rounded-box border-base-300 border p-4">
                <h2 className="mb-4 font-medium">Comportamiento en almacén</h2>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <FormField
                        label="Tipo"
                        htmlFor="tipo"
                        error={form.errors.tipo}
                        description="Define si se gasta o si sale y regresa."
                        required
                    >
                        <Select
                            id="tipo"
                            value={form.data.tipo}
                            onValueChange={(v) => {
                                const nuevo = v as AlmProductoTipo;
                                form.setData('tipo', nuevo);

                                // Serializar un insumo no tiene sentido: se gasta.
                                if (nuevo === 'insumo') {
                                    form.setData('se_controla_por_pieza', false);
                                }
                            }}
                        >
                            {tipos.map((t) => (
                                <SelectItem key={t.value} value={t.value}>
                                    {t.label}
                                </SelectItem>
                            ))}
                        </Select>
                    </FormField>

                    <FormField
                        label="Clase de conteo"
                        htmlFor="clasificacion_abc"
                        error={form.errors.clasificacion_abc}
                        description={regla ? `${regla.label}: ${regla.descripcion}` : undefined}
                        required
                    >
                        <Select
                            id="clasificacion_abc"
                            value={form.data.clasificacion_abc}
                            onValueChange={(v) => form.setData('clasificacion_abc', v as AlmClasificacionAbc)}
                        >
                            {clases.map((c) => (
                                <SelectItem key={c.value} value={c.value}>
                                    {c.value} — {c.label} (cada {c.frecuencia_dias} días)
                                </SelectItem>
                            ))}
                        </Select>
                    </FormField>

                    <FormField
                        label="Stock mínimo"
                        htmlFor="stock_minimo"
                        error={form.errors.stock_minimo}
                        description="Opcional. Debajo de esto, la existencia se marca en rojo."
                    >
                        <Input
                            id="stock_minimo"
                            type="number"
                            min="0"
                            step="0.001"
                            value={form.data.stock_minimo}
                            onChange={(e) => form.setData('stock_minimo', e.target.value)}
                            placeholder="0"
                        />
                    </FormField>

                    <div className="md:col-span-2">
                        <label className="flex cursor-pointer items-start gap-3">
                            <input
                                type="checkbox"
                                className="checkbox checkbox-sm mt-0.5"
                                checked={form.data.se_controla_por_pieza}
                                onChange={(e) => form.setData('se_controla_por_pieza', e.target.checked)}
                                disabled={form.data.tipo === 'insumo'}
                            />
                            <span>
                                <span className="font-medium">Se controla por pieza</span>
                                <span className="text-base-content/60 block text-sm">
                                    Cada unidad se da de alta en Activos con su número de serie —y ahí mismo su marca,
                                    su modelo y su id de mantenimiento—, lleva su propia etiqueta y se presta bajo
                                    resguardo. Sin esto el kardex sabe cuántas pulidoras salieron, pero no quién tiene
                                    cuál. Sólo aplica a los activos: un insumo se gasta.
                                </span>
                                {form.errors.se_controla_por_pieza && (
                                    <span className="text-error block text-sm">{form.errors.se_controla_por_pieza}</span>
                                )}
                            </span>
                        </label>
                    </div>

                    <div className="md:col-span-2">
                        <label className="flex cursor-pointer items-start gap-3">
                            <input
                                type="checkbox"
                                className="checkbox checkbox-sm mt-0.5"
                                checked={form.data.requiere_verificacion}
                                onChange={(e) => form.setData('requiere_verificacion', e.target.checked)}
                            />
                            <span>
                                <span className="font-medium">Inspección de mantenimiento</span>
                                <span className="text-base-content/60 block text-sm">
                                    Márcalo para el equipo cuyo mantenimiento hay que revisar al recibirlo (una pulidora
                                    sí, un andamio no). La entrada queda detenida hasta que alguien palomee esa
                                    revisión.
                                </span>
                            </span>
                        </label>
                    </div>

                </div>
            </div>

            <p className="text-base-content/60 text-sm">
                El <strong>precio</strong> no se captura aquí: se va formando solo con lo que cotizan los proveedores en
                Compras, y la ficha del artículo muestra ese histórico. La <strong>ubicación</strong> tampoco, porque es
                por almacén — el mismo tornillo puede vivir en el Rack A-1 de planta y en un contenedor de obra.
            </p>

            <div className="flex justify-end gap-2">
                <Button variant="outline" asChild>
                    <Link href={esAlta ? '/admin/almacen/articulos' : `/admin/almacen/articulos/${articulo.id}`}>
                        Cancelar
                    </Link>
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {esAlta ? 'Guardar artículo' : 'Guardar cambios'}
                </Button>
            </div>
        </form>
    );
}
