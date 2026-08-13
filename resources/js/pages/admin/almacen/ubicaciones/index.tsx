import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { ALMACENES_DEMO, EXISTENCIAS_DEMO, TIPOS_UBICACION, ubicacionesDe } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import type { AlmUbicacionDemo, AlmUbicacionTipo } from '@/types/models';
import { Head } from '@inertiajs/react';
import { CornerDownRightIcon, PlusIcon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Insumos', href: '/admin/almacen/existencias' },
    { title: 'Ubicaciones', href: '/admin/almacen/ubicaciones' },
];

/**
 * Cuelga las ubicaciones de sus padres y las devuelve en el orden en que se
 * recorren físicamente, con el nivel de profundidad para poder sangrarlas.
 */
function arbolDe(ubicaciones: AlmUbicacionDemo[]): { ubicacion: AlmUbicacionDemo; nivel: number }[] {
    const filas: { ubicacion: AlmUbicacionDemo; nivel: number }[] = [];

    const colgar = (padreId: number | null, nivel: number) => {
        ubicaciones
            .filter((u) => u.padre_id === padreId)
            .forEach((u) => {
                filas.push({ ubicacion: u, nivel });
                colgar(u.id, nivel + 1);
            });
    };

    colgar(null, 0);

    return filas;
}

/**
 * El tercer nivel del inventario: obra → almacén → ubicación.
 *
 * Hasta ahora el lugar dentro del almacén era un texto libre que servía para
 * anotar pero no para filtrar. Con el catálogo se puede preguntar qué hay en un
 * rack, y el inventario cíclico tiene una ruta que recorrer en vez de una lista
 * suelta de artículos.
 */
export default function UbicacionesIndex() {
    const [almacen, setAlmacen] = useState(ALMACENES_DEMO[0].clave);
    const [mostrarInactivas, setMostrarInactivas] = useState(false);

    const [codigo, setCodigo] = useState('');
    const [nombre, setNombre] = useState('');
    const [tipo, setTipo] = useState<AlmUbicacionTipo>('rack');
    const [padre, setPadre] = useState('');

    const delAlmacen = ubicacionesDe(almacen).filter((u) => mostrarInactivas || u.activa);
    const filas = arbolDe(delAlmacen);

    /** Cuántos artículos viven en cada lugar: sin esto, borrar es a ciegas. */
    const articulosEn = (ubicacionId: number) =>
        EXISTENCIAS_DEMO.filter((e) => e.ubicacion_id === ubicacionId).length;

    const sinAcomodar = EXISTENCIAS_DEMO.filter((e) => e.almacen === almacen && e.ubicacion_id === null).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Ubicaciones" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Ubicaciones</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Dónde está el material dentro de cada almacén: pasillos, racks, niveles y contenedores. El
                        almacén dice en qué bodega; esto dice en qué anaquel.
                    </p>
                </div>

                <div className="alert alert-warning mb-4">
                    <span>Vista de maqueta: los datos son de ejemplo, todavía no hay backend.</span>
                </div>

                <div className="mb-4 flex flex-wrap items-end gap-3">
                    <div className="w-72">
                        <label className="label label-text text-xs">Almacén</label>
                        <Select value={almacen} onValueChange={setAlmacen}>
                            {ALMACENES_DEMO.map((a) => (
                                <SelectItem key={a.id} value={a.clave}>
                                    {a.clave} — {a.nombre}
                                    {a.obra ? ` (${a.obra})` : ''}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>

                    <label className="mb-2 flex cursor-pointer items-center gap-2">
                        <input
                            type="checkbox"
                            className="checkbox checkbox-sm"
                            checked={mostrarInactivas}
                            onChange={(e) => setMostrarInactivas(e.target.checked)}
                        />
                        <span className="text-sm">Mostrar dadas de baja</span>
                    </label>
                </div>

                {sinAcomodar > 0 && (
                    <div className="alert alert-info mb-4">
                        <span>
                            {sinAcomodar} artículo(s) de este almacén no tienen lugar asignado. Se pueden acomodar desde
                            la ficha del artículo o desde Existencias.
                        </span>
                    </div>
                )}

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div className="rounded-box border-base-300 overflow-hidden border lg:col-span-2">
                        <table className="table table-sm">
                            <thead className="bg-base-200">
                                <tr>
                                    <th>Lugar</th>
                                    <th>Código</th>
                                    <th>Tipo</th>
                                    <th className="text-right">Artículos</th>
                                    <th className="text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filas.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="text-base-content/50 py-6 text-center">
                                            Este almacén todavía no tiene ubicaciones. Mientras no las tenga, su
                                            material aparece como «sin acomodar».
                                        </td>
                                    </tr>
                                ) : (
                                    filas.map(({ ubicacion, nivel }) => (
                                        <tr key={ubicacion.id} className={ubicacion.activa ? 'hover' : 'hover opacity-50'}>
                                            <td>
                                                <span
                                                    className="flex items-center gap-1"
                                                    style={{ paddingLeft: `${nivel * 1.25}rem` }}
                                                >
                                                    {nivel > 0 && (
                                                        <CornerDownRightIcon className="text-base-content/30 size-3" />
                                                    )}
                                                    {ubicacion.nombre}
                                                </span>
                                            </td>
                                            <td className="font-mono text-xs">{ubicacion.codigo}</td>
                                            <td className="text-base-content/60 text-sm">
                                                {TIPOS_UBICACION[ubicacion.tipo]}
                                            </td>
                                            <td className="text-right font-mono">
                                                {articulosEn(ubicacion.id) || (
                                                    <span className="text-base-content/30">—</span>
                                                )}
                                            </td>
                                            <td className="text-center">
                                                {ubicacion.activa ? (
                                                    <span className="badge badge-sm badge-success">Activa</span>
                                                ) : (
                                                    <span
                                                        className="badge badge-sm badge-ghost"
                                                        title="No se borra: hay movimientos históricos que la mencionan"
                                                    >
                                                        De baja
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <form onSubmit={(e) => e.preventDefault()} className="rounded-box border-base-300 border p-4">
                        <h2 className="mb-4 font-medium">Nueva ubicación</h2>

                        <div className="space-y-4">
                            <FormField
                                label="Cuelga de"
                                htmlFor="padre"
                                description="Vacío la deja al nivel del almacén. Un nivel cuelga de un rack, y el rack de un pasillo."
                            >
                                <Select id="padre" value={padre} onValueChange={setPadre}>
                                    <SelectItem value="">Nada — va al nivel del almacén</SelectItem>
                                    {delAlmacen
                                        .filter((u) => u.activa)
                                        .map((u) => (
                                            <SelectItem key={u.id} value={String(u.id)}>
                                                {u.nombre} ({u.codigo})
                                            </SelectItem>
                                        ))}
                                </Select>
                            </FormField>

                            <FormField
                                label="Código"
                                htmlFor="codigo"
                                description="Es lo que se rotula en el anaquel. Único dentro del almacén."
                                required
                            >
                                <Input
                                    id="codigo"
                                    value={codigo}
                                    onChange={(e) => setCodigo(e.target.value.toUpperCase())}
                                    placeholder="A-1-3"
                                    className="font-mono"
                                />
                            </FormField>

                            <FormField label="Nombre" htmlFor="nombre" required>
                                <Input
                                    id="nombre"
                                    value={nombre}
                                    onChange={(e) => setNombre(e.target.value)}
                                    placeholder="Nivel 3"
                                />
                            </FormField>

                            <FormField label="Tipo" htmlFor="tipo" required>
                                <Select
                                    id="tipo"
                                    value={tipo}
                                    onValueChange={(v) => setTipo(v as AlmUbicacionTipo)}
                                >
                                    {Object.entries(TIPOS_UBICACION).map(([valor, etiqueta]) => (
                                        <SelectItem key={valor} value={valor}>
                                            {etiqueta}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <Button type="submit" className="w-full" disabled>
                                <PlusIcon className="size-4" />
                                Agregar ubicación
                            </Button>
                        </div>

                        <p className="text-base-content/60 mt-4 text-sm">
                            Una ubicación con material no se borra: se da de baja. El kardex viejo la sigue
                            mencionando, y borrarla dejaría movimientos apuntando a un lugar que ya no existe.
                        </p>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
