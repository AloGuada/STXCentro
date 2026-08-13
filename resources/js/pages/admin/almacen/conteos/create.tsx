import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import {
    ALMACENES_DEMO,
    ARTICULOS_DEMO,
    CLASES_ABC,
    EXISTENCIAS_DEMO,
    REGLAS_ABC,
    rutaUbicacion,
    ubicacionesDe,
    USUARIOS_DEMO,
} from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import type { AlmProductoTipo } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { InfoIcon } from 'lucide-react';
import { useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Insumos', href: '/admin/almacen/existencias' },
    { title: 'Inventarios cíclicos', href: '/admin/almacen/conteos' },
    { title: 'Nuevo conteo', href: '/admin/almacen/conteos/create' },
];

const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

/**
 * Conteo suelto: el que se levanta cuando hay sospecha de faltante y no se
 * puede esperar a que el programa alcance esa zona.
 *
 * Se arma con filtros y no renglón por renglón, porque lo que se cuenta es un
 * recorrido físico: "todo el Rack A-1", no una lista de artículos elegidos a
 * mano que obliga a caminar el almacén en zigzag.
 */
export default function ConteoCreate() {
    const [almacen, setAlmacen] = useState('');
    const [ubicacion, setUbicacion] = useState('');
    const [clasificacion, setClasificacion] = useState('');
    const [responsable, setResponsable] = useState('');
    const [fecha, setFecha] = useState('');
    const [incluirCeros, setIncluirCeros] = useState(true);

    const ubicaciones = almacen ? ubicacionesDe(almacen).filter((u) => u.activa) : [];

    /** Lo que va a caer en la hoja con los filtros de ahorita. */
    const renglones = useMemo(() => {
        if (!almacen) {
            return [];
        }

        return EXISTENCIAS_DEMO.filter((e) => e.almacen === almacen)
            .map((e) => ({ existencia: e, articulo: ARTICULOS_DEMO.find((a) => a.codigo === e.producto) }))
            .filter(({ existencia, articulo }) => {
                if (!articulo?.controla_inventario) {
                    return false;
                }

                if (ubicacion && String(existencia.ubicacion_id) !== ubicacion) {
                    return false;
                }

                if (clasificacion && articulo.clasificacion_abc !== clasificacion) {
                    return false;
                }

                return incluirCeros || existencia.cantidad > 0;
            });
    }, [almacen, ubicacion, clasificacion, incluirCeros]);

    const tipoLegible: Record<AlmProductoTipo, string> = {
        insumo: 'Insumo',
        herramienta: 'Herramienta',
        activo: 'Activo',
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo conteo" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Nuevo conteo suelto</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Fuera del programa: se arma cuando hay sospecha de faltante y no puede esperar a que le toque a
                        esa zona.
                    </p>
                </div>

                <div className="alert alert-warning mb-4">
                    <span>Vista de maqueta: el formulario todavía no guarda nada.</span>
                </div>

                <form onSubmit={(e) => e.preventDefault()} className="space-y-6">
                    <div className="rounded-box border-base-300 border p-4">
                        <h2 className="mb-4 font-medium">Qué se va a contar</h2>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <FormField label="Almacén" htmlFor="almacen" required>
                                <Select
                                    id="almacen"
                                    value={almacen}
                                    onValueChange={(v) => {
                                        setAlmacen(v);
                                        setUbicacion('');
                                    }}
                                    placeholder="Elige el almacén"
                                >
                                    {ALMACENES_DEMO.map((a) => (
                                        <SelectItem key={a.id} value={a.clave}>
                                            {a.clave} — {a.nombre}
                                            {a.obra ? ` (${a.obra})` : ''}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField
                                label="Zona"
                                htmlFor="ubicacion"
                                description="Vacío cuenta el almacén completo."
                            >
                                <Select
                                    id="ubicacion"
                                    value={ubicacion}
                                    onValueChange={setUbicacion}
                                    disabled={!almacen}
                                >
                                    <SelectItem value="">
                                        {almacen ? 'Todo el almacén' : 'Elige un almacén'}
                                    </SelectItem>
                                    {ubicaciones.map((u) => (
                                        <SelectItem key={u.id} value={String(u.id)}>
                                            {rutaUbicacion(u.id)}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField
                                label="Clase"
                                htmlFor="clasificacion"
                                description="Vacío incluye las tres."
                            >
                                <Select id="clasificacion" value={clasificacion} onValueChange={setClasificacion}>
                                    <SelectItem value="">Todas las clases</SelectItem>
                                    {REGLAS_ABC.map((r) => (
                                        <SelectItem key={r.clasificacion} value={r.clasificacion}>
                                            {r.clasificacion} — {r.etiqueta}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField label="Responsable" htmlFor="responsable" required>
                                <Select
                                    id="responsable"
                                    value={responsable}
                                    onValueChange={setResponsable}
                                    placeholder="¿Quién cuenta?"
                                >
                                    {USUARIOS_DEMO.map((u) => (
                                        <SelectItem key={u.id} value={String(u.id)}>
                                            {u.nombre} — {u.puesto}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField label="Fecha del conteo" htmlFor="fecha" required>
                                <Input
                                    id="fecha"
                                    type="date"
                                    value={fecha}
                                    onChange={(e) => setFecha(e.target.value)}
                                />
                            </FormField>

                            <div className="md:col-span-2 lg:col-span-3">
                                <label className="flex cursor-pointer items-start gap-3">
                                    <input
                                        type="checkbox"
                                        className="checkbox checkbox-sm mt-0.5"
                                        checked={incluirCeros}
                                        onChange={(e) => setIncluirCeros(e.target.checked)}
                                    />
                                    <span>
                                        <span className="font-medium">Incluir lo que está en ceros</span>
                                        <span className="text-base-content/60 block text-sm">
                                            Déjalo palomeado: la mitad de las diferencias aparecen justo donde el
                                            sistema cree que no hay nada. Quitarlo hace la hoja más corta, pero
                                            entonces ese material no se revisa nunca.
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div className="rounded-box border-base-300 border">
                        <div className="border-base-300 flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3">
                            <h2 className="font-medium">Hoja de conteo</h2>
                            <span className="text-base-content/60 text-sm">
                                {renglones.length} artículo(s)
                            </span>
                        </div>

                        {almacen === '' ? (
                            <p className="text-base-content/50 px-4 py-8 text-center text-sm">
                                Elige un almacén para ver qué entra a la hoja.
                            </p>
                        ) : renglones.length === 0 ? (
                            <p className="text-base-content/50 px-4 py-8 text-center text-sm">
                                Ningún artículo cae con esos filtros.
                            </p>
                        ) : (
                            <table className="table table-sm">
                                <thead className="bg-base-200">
                                    <tr>
                                        <th>Código</th>
                                        <th>Descripción</th>
                                        <th>Ubicación</th>
                                        <th className="w-20">Clase</th>
                                        <th className="text-right">Según el sistema</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {renglones.map(({ existencia, articulo }) => (
                                        <tr key={existencia.producto} className="hover">
                                            <td className="font-mono text-xs">{existencia.producto}</td>
                                            <td>
                                                {existencia.descripcion}
                                                {articulo && articulo.tipo !== 'insumo' && (
                                                    <span className="badge badge-xs badge-ghost ml-2">
                                                        {tipoLegible[articulo.tipo]}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="text-base-content/60 text-sm">
                                                {rutaUbicacion(existencia.ubicacion_id) ?? (
                                                    <span className="text-base-content/40">Sin acomodar</span>
                                                )}
                                            </td>
                                            <td>
                                                {articulo && (
                                                    <span
                                                        className={`badge badge-sm ${CLASES_ABC[articulo.clasificacion_abc]}`}
                                                    >
                                                        {articulo.clasificacion_abc}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="text-right font-mono">
                                                <span className={existencia.cantidad <= 0 ? 'text-error' : ''}>
                                                    {numero(existencia.cantidad)}
                                                </span>
                                                <span className="text-base-content/40"> {existencia.unidad}</span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>

                    <div className="alert">
                        <InfoIcon className="size-4" />
                        <span>
                            Lo que dice el sistema se congela al generar la hoja. Si se leyera al cerrar, una salida
                            capturada a media mañana convertiría un conteo correcto en una diferencia inventada.
                        </span>
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/admin/almacen/conteos">Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled>
                            Generar hoja de conteo
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
