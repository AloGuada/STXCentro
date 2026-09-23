import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { etiquetaDeAlmacen } from '@/lib/alm/almacenes';
import type { BreadcrumbItem } from '@/types';
import type { AlmAlmacenOpcion, AlmProductoOpcion } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { InfoIcon, PlusIcon, Trash2Icon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Activos', href: '/admin/almacen/activos' },
    { title: 'Alta', href: '/admin/almacen/activos/create' },
];

/**
 * Lo que se captura de cada pieza. La serie es la que la identifica; marca,
 * modelo e id de mantenimiento la acompañan como información suya y no del
 * artículo: el catálogo dice qué es —una pulidora de 4 1/2"— y esto, con qué se
 * cumplió.
 */
type Pieza = {
    no_serie: string;
    marca: string;
    modelo: string;
    id_mantenimiento: string;
    costo: string;
    condicion: string;
};

const PIEZA_VACIA: Pieza = { no_serie: '', marca: '', modelo: '', id_mantenimiento: '', costo: '', condicion: 'Buena' };

/** El catálogo dice cómo se da de alta: con serie, pieza por pieza; sin serie, por cantidad. */
type ArticuloActivo = AlmProductoOpcion & { se_controla_por_pieza: boolean };

type Props = {
    almacenes: AlmAlmacenOpcion[];
    articulos: ArticuloActivo[];
    ubicacionesPorAlmacen: Record<number, { id: number; ruta: string }[]>;
};

export default function ActivoCreate({ almacenes, articulos, ubicacionesPorAlmacen }: Props) {
    const form = useForm({
        articulo_id: '',
        almacen_id: '',
        ubicacion_id: '',
        piezas: [{ ...PIEZA_VACIA }] as Pieza[],
        cantidad: '',
        costo: '',
        observaciones: '',
    });

    const articuloElegido = articulos.find((a) => String(a.id) === form.data.articulo_id);
    // Sin artículo elegido se asume por pieza: es el caso que tiene más campos
    // y así la pantalla no brinca al elegir.
    const porCantidad = articuloElegido !== undefined && !articuloElegido.se_controla_por_pieza;

    // La carga inicial son decenas de piezas: teclear renglón por renglón hace
    // que nadie la termine, así que se pueden pegar las series de golpe y
    // corregir después la que se salga.
    const [pegado, setPegado] = useState('');

    const ubicaciones = form.data.almacen_id === '' ? [] : (ubicacionesPorAlmacen[Number(form.data.almacen_id)] ?? []);

    const cambiar = (indice: number, campo: keyof Pieza, valor: string) =>
        form.setData(
            'piezas',
            form.data.piezas.map((p, i) => (i === indice ? { ...p, [campo]: valor } : p)),
        );

    const agregar = () => form.setData('piezas', [...form.data.piezas, { ...PIEZA_VACIA }]);

    const quitar = (indice: number) =>
        form.setData(
            'piezas',
            form.data.piezas.length === 1 ? [{ ...PIEZA_VACIA }] : form.data.piezas.filter((_, i) => i !== indice),
        );

    /**
     * Cada renglón pegado es una pieza. Hereda marca, modelo y costo del último
     * renglón que ya los traiga: un alta suele ser del mismo lote, y volver a
     * teclear «DeWalt» treinta veces es lo que hace que se dejen en blanco.
     */
    const agregarPegadas = () => {
        const series = pegado
            .split('\n')
            .map((s) => s.trim())
            .filter(Boolean);

        if (series.length === 0) {
            return;
        }

        const ultimo = [...form.data.piezas].reverse().find((p) => p.marca !== '' || p.modelo !== '');
        const vacias = form.data.piezas.filter((p) => p.no_serie.trim() !== '');

        form.setData('piezas', [
            ...vacias,
            ...series.map((no_serie) => ({
                ...PIEZA_VACIA,
                no_serie,
                marca: ultimo?.marca ?? '',
                modelo: ultimo?.modelo ?? '',
                costo: ultimo?.costo ?? '',
            })),
        ]);

        setPegado('');
    };

    const errorDe = (indice: number, campo: string): string | undefined =>
        (form.errors as Record<string, string | undefined>)[`piezas.${indice}.${campo}`];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Alta de activos" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Alta de activos</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        El catálogo decide la forma: un activo con número de serie se da de alta pieza por pieza y
                        cada una suma 1; un activo sin serie entra como un solo registro por cantidad. Las dos dejan su
                        asiento en el kardex.
                    </p>
                </div>

                {articulos.length === 0 ? (
                    <div className="alert alert-warning">
                        <span>Ningún artículo es de tipo «Activo». Márcalo primero en el catálogo de Artículos.</span>
                    </div>
                ) : (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post('/admin/almacen/activos');
                        }}
                        className="space-y-6"
                    >
                        <div className="rounded-box border-base-300 border p-4">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <FormField
                                    label="Artículo"
                                    htmlFor="articulo_id"
                                    error={form.errors.articulo_id}
                                    required
                                >
                                    <Select
                                        id="articulo_id"
                                        value={form.data.articulo_id}
                                        onValueChange={(v) => form.setData('articulo_id', v)}
                                        placeholder="¿Qué activo entra?"
                                    >
                                        {articulos.map((a) => (
                                            <SelectItem key={a.id} value={String(a.id)}>
                                                {a.codigo} — {a.descripcion}
                                                {a.se_controla_por_pieza ? ' · por pieza' : ' · por cantidad'}
                                            </SelectItem>
                                        ))}
                                    </Select>
                                </FormField>

                                <FormField label="Almacén" htmlFor="almacen_id" error={form.errors.almacen_id} required>
                                    <Select
                                        id="almacen_id"
                                        value={form.data.almacen_id}
                                        onValueChange={(v) => {
                                            form.setData('almacen_id', v);
                                            // El lugar es del almacén viejo: dejarlo
                                            // pondría la pieza en un rack de otra bodega.
                                            form.setData('ubicacion_id', '');
                                        }}
                                        placeholder="¿Dónde quedan?"
                                    >
                                        {almacenes.map((a) => (
                                            <SelectItem key={a.id} value={String(a.id)}>
                                                {etiquetaDeAlmacen(a)} — {a.nombre}
                                            </SelectItem>
                                        ))}
                                    </Select>
                                </FormField>

                                <FormField
                                    label="Ubicación"
                                    htmlFor="ubicacion_id"
                                    error={form.errors.ubicacion_id}
                                    description={
                                        ubicaciones.length === 0
                                            ? 'Este almacén no tiene ubicaciones dadas de alta; el material queda sin acomodar.'
                                            : porCantidad
                                              ? 'Dónde queda el renglón.'
                                              : 'Todas las piezas de esta alta quedan en el mismo lugar.'
                                    }
                                >
                                    <Select
                                        id="ubicacion_id"
                                        value={form.data.ubicacion_id}
                                        onValueChange={(v) => form.setData('ubicacion_id', v)}
                                        disabled={ubicaciones.length === 0}
                                    >
                                        <SelectItem value="">Sin acomodar</SelectItem>
                                        {ubicaciones.map((u) => (
                                            <SelectItem key={u.id} value={String(u.id)}>
                                                {u.ruta}
                                            </SelectItem>
                                        ))}
                                    </Select>
                                </FormField>
                            </div>
                        </div>

                        {porCantidad ? (
                            <div className="rounded-box border-base-300 border p-4">
                                <h2 className="mb-1 font-medium">Cuántos entran</h2>
                                <p className="text-base-content/60 mb-4 text-sm">
                                    {articuloElegido?.descripcion} no lleva número de serie: se lleva como un solo
                                    registro por cantidad, y de ahí se presta y se devuelve. Si ya tiene renglón en
                                    este almacén, lo que entre se le suma.
                                </p>
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <FormField label="Cantidad" htmlFor="cantidad" error={form.errors.cantidad} required>
                                        <Input
                                            id="cantidad"
                                            type="number"
                                            min="0"
                                            step="0.0001"
                                            value={form.data.cantidad}
                                            onChange={(e) => form.setData('cantidad', e.target.value)}
                                            placeholder="12"
                                        />
                                    </FormField>
                                    <FormField
                                        label="Costo unitario"
                                        htmlFor="costo"
                                        error={form.errors.costo}
                                        description="Opcional. Vacío entra al costo promedio que ya tenga el renglón."
                                    >
                                        <Input
                                            id="costo"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={form.data.costo}
                                            onChange={(e) => form.setData('costo', e.target.value)}
                                            placeholder="0.00"
                                        />
                                    </FormField>
                                    <FormField label="Observaciones" htmlFor="observaciones" error={form.errors.observaciones}>
                                        <Input
                                            id="observaciones"
                                            value={form.data.observaciones}
                                            onChange={(e) => form.setData('observaciones', e.target.value)}
                                            placeholder="Compra de arranque, donación de obra..."
                                        />
                                    </FormField>
                                </div>
                            </div>
                        ) : (
                            <>
                                <div className="rounded-box border-base-300 border p-4">
                                    <h2 className="mb-2 font-medium">Pegar series</h2>
                                    <p className="text-base-content/60 mb-2 text-sm">
                                        Una por renglón. Heredan la marca, el modelo y el costo del último renglón que ya los
                                        traiga: un alta suele ser del mismo lote.
                                    </p>
                                    <div className="flex gap-2">
                                        <textarea
                                            className="textarea textarea-bordered flex-1 font-mono text-sm"
                                            rows={3}
                                            value={pegado}
                                            onChange={(e) => setPegado(e.target.value)}
                                            placeholder={'PUL-4120-07\nPUL-4120-08\nPUL-4120-11'}
                                        />
                                        <Button type="button" variant="outline" onClick={agregarPegadas}>
                                            Agregar
                                        </Button>
                                    </div>
                                </div>

                                <div>
                                    <div className="mb-2 flex items-center justify-between">
                                        <h2 className="text-lg font-semibold">
                                            Piezas ({form.data.piezas.filter((p) => p.no_serie.trim() !== '').length})
                                        </h2>
                                        <Button type="button" variant="outline" onClick={agregar}>
                                            <PlusIcon className="size-4" />
                                            Agregar renglón
                                        </Button>
                                    </div>

                                    {typeof form.errors.piezas === 'string' && (
                                        <p className="text-error mb-2 text-sm">{form.errors.piezas}</p>
                                    )}

                                    <div className="rounded-box border-base-300 overflow-x-auto border">
                                        <table className="table table-sm">
                                            <thead className="bg-base-200">
                                                <tr>
                                                    <th className="w-56">No. de serie</th>
                                                    <th>Marca</th>
                                                    <th>Modelo</th>
                                                    <th>Id de mto.</th>
                                                    <th className="w-32 text-right">Costo</th>
                                                    <th>Condición</th>
                                                    <th className="w-10"></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {form.data.piezas.map((p, i) => (
                                                    <tr key={i} className="hover">
                                                        <td>
                                                            <Input
                                                                className="input-sm font-mono"
                                                                value={p.no_serie}
                                                                onChange={(e) => cambiar(i, 'no_serie', e.target.value)}
                                                                placeholder="PUL-4120-07"
                                                                error={Boolean(errorDe(i, 'no_serie'))}
                                                            />
                                                            {errorDe(i, 'no_serie') && (
                                                                <span className="text-error text-xs">
                                                                    {errorDe(i, 'no_serie')}
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td>
                                                            <Input
                                                                className="input-sm"
                                                                value={p.marca}
                                                                onChange={(e) => cambiar(i, 'marca', e.target.value)}
                                                                placeholder="DeWalt"
                                                            />
                                                        </td>
                                                        <td>
                                                            <Input
                                                                className="input-sm"
                                                                value={p.modelo}
                                                                onChange={(e) => cambiar(i, 'modelo', e.target.value)}
                                                                placeholder="DWE4120"
                                                            />
                                                        </td>
                                                        <td>
                                                            <Input
                                                                className="input-sm font-mono"
                                                                value={p.id_mantenimiento}
                                                                onChange={(e) => cambiar(i, 'id_mantenimiento', e.target.value)}
                                                                placeholder="MTO-0071"
                                                            />
                                                        </td>
                                                        <td>
                                                            <Input
                                                                type="number"
                                                                min="0"
                                                                step="0.01"
                                                                className="input-sm text-right"
                                                                value={p.costo}
                                                                onChange={(e) => cambiar(i, 'costo', e.target.value)}
                                                                placeholder="0.00"
                                                            />
                                                        </td>
                                                        <td>
                                                            <Input
                                                                className="input-sm"
                                                                value={p.condicion}
                                                                onChange={(e) => cambiar(i, 'condicion', e.target.value)}
                                                            />
                                                        </td>
                                                        <td>
                                                            <button
                                                                type="button"
                                                                className="btn btn-ghost btn-xs"
                                                                onClick={() => quitar(i)}
                                                                aria-label="Quitar renglón"
                                                            >
                                                                <Trash2Icon className="size-3.5" />
                                                            </button>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div className="alert">
                                    <InfoIcon className="size-4" />
                                    <span>
                                        El costo va por pieza porque el promedio del renglón sale de promediarlas: dos pulidoras
                                        del mismo modelo compradas con dos años de diferencia no valen lo mismo.
                                    </span>
                                </div>
                            </>
                        )}

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/almacen/activos">Cancelar</Link>
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {porCantidad ? 'Dar de alta la cantidad' : 'Dar de alta'}
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </AppLayout>
    );
}
