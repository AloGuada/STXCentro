import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { ArbolDeLectura, EditorDeArbol, contarSecciones, editableDe, paraGuardar } from './arbol';
import type { NodoEditable, PlantillaDosier } from './tipos';

const BASE = '/admin/calidad/dosier/plantillas';

type Props = {
    plantillas: PlantillaDosier[];
    plantillaElegida: number | null;
    puedeEditar: boolean;
};

/**
 * El catálogo de plantillas del dosier: la lista a la izquierda y, a la
 * derecha, la plantilla elegida con su árbol.
 *
 * Una plantilla se crea vacía o partiendo de otra, y se desactiva en vez de
 * borrarse: los dosieres que nacieron de ella conservan su nombre. Cambiar una
 * plantilla no toca los dosieres ya creados, porque cada uno copió el árbol.
 */
export function CatalogoDePlantillas({ plantillas, plantillaElegida, puedeEditar }: Props) {
    const [elegida, setElegida] = useState<number | null>(plantillaElegida ?? plantillas[0]?.id ?? null);
    const plantilla = plantillas.find((p) => p.id === elegida) ?? null;

    const nueva = useForm({ nombre: '', descripcion: '', desde: '' });

    const crear = (e: React.FormEvent) => {
        e.preventDefault();
        nueva.post(BASE, { preserveScroll: true, onSuccess: () => nueva.reset() });
    };

    return (
        <div className="grid gap-4 lg:grid-cols-[18rem_minmax(0,1fr)]">
            <div className="space-y-3">
                <ul className="rounded-box border-base-300 divide-base-300 divide-y border">
                    {plantillas.map((p) => (
                        <li key={p.id}>
                            <button
                                type="button"
                                onClick={() => setElegida(p.id)}
                                className={`w-full px-3 py-2 text-left ${p.id === elegida ? 'bg-primary/10' : 'hover:bg-base-200'} ${p.activo ? '' : 'opacity-60'}`}
                            >
                                <span className="block text-sm font-semibold">{p.nombre}</span>
                                <span className="text-base-content/60 text-xs">
                                    {p.secciones} secciones{!p.activo && ' · desactivada'}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>

                {puedeEditar && (
                    <form onSubmit={crear} className="rounded-box border-base-300 space-y-2 border p-3">
                        <div className="text-sm font-semibold">Nueva plantilla</div>
                        <input
                            className={`input input-sm input-bordered w-full ${nueva.errors.nombre ? 'input-error' : ''}`}
                            placeholder="Nombre"
                            maxLength={120}
                            value={nueva.data.nombre}
                            onChange={(e) => nueva.setData('nombre', e.target.value)}
                        />
                        {nueva.errors.nombre && <p className="text-error text-xs">{nueva.errors.nombre}</p>}
                        <select
                            className="select select-sm select-bordered w-full"
                            value={nueva.data.desde}
                            onChange={(e) => nueva.setData('desde', e.target.value)}
                        >
                            <option value="">Vacía</option>
                            {plantillas.map((p) => (
                                <option key={p.id} value={String(p.id)}>
                                    Copiar de «{p.nombre}»
                                </option>
                            ))}
                        </select>
                        <Button type="submit" size="sm" disabled={nueva.processing}>
                            Crear
                        </Button>
                    </form>
                )}
            </div>

            {plantilla ? (
                <EditorDePlantilla
                    // Al guardar vuelve el árbol del servidor (con ids nuevos): el borrador se reinicia con él.
                    key={`${plantilla.id}:${JSON.stringify(plantilla.arbol)}`}
                    plantilla={plantilla}
                    puedeEditar={puedeEditar}
                />
            ) : (
                <div className="rounded-box border-base-300 text-base-content/60 border border-dashed p-10 text-center text-sm">
                    No hay plantillas todavía.
                </div>
            )}
        </div>
    );
}

function EditorDePlantilla({ plantilla, puedeEditar }: { plantilla: PlantillaDosier; puedeEditar: boolean }) {
    const original = editableDe(plantilla.arbol);
    const [borrador, setBorrador] = useState<NodoEditable[]>(original);
    const [guardando, setGuardando] = useState(false);
    const [errores, setErrores] = useState<string | null>(null);
    const datos = useForm({ nombre: plantilla.nombre, descripcion: plantilla.descripcion ?? '' });

    const cambiado = JSON.stringify(paraGuardar(borrador)) !== JSON.stringify(paraGuardar(original));

    const guardarArbol = () => {
        setGuardando(true);
        setErrores(null);
        router.put(
            `${BASE}/${plantilla.id}/arbol`,
            { arbol: paraGuardar(borrador) },
            {
                preserveScroll: true,
                onError: (e) => setErrores(Object.values(e).join(' ')),
                onFinish: () => setGuardando(false),
            },
        );
    };

    const guardarDatos = (e: React.FormEvent) => {
        e.preventDefault();
        datos.put(`${BASE}/${plantilla.id}`, { preserveScroll: true });
    };

    const alternar = () => router.patch(`${BASE}/${plantilla.id}/toggle`, {}, { preserveScroll: true });

    return (
        <div className="rounded-box border-base-300 space-y-4 border p-4">
            {puedeEditar ? (
                <form onSubmit={guardarDatos} className="grid gap-2 md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)_auto_auto]">
                    <input
                        className={`input input-sm input-bordered font-semibold ${datos.errors.nombre ? 'input-error' : ''}`}
                        maxLength={120}
                        value={datos.data.nombre}
                        onChange={(e) => datos.setData('nombre', e.target.value)}
                    />
                    <input
                        className="input input-sm input-bordered"
                        maxLength={500}
                        placeholder="Para qué tipo de obra sirve"
                        value={datos.data.descripcion}
                        onChange={(e) => datos.setData('descripcion', e.target.value)}
                    />
                    <Button type="submit" size="sm" variant="outline" disabled={datos.processing || !datos.isDirty}>
                        Guardar datos
                    </Button>
                    <Button type="button" size="sm" variant="ghost" onClick={alternar}>
                        {plantilla.activo ? 'Desactivar' : 'Reactivar'}
                    </Button>
                    {datos.errors.nombre && <p className="text-error text-xs md:col-span-4">{datos.errors.nombre}</p>}
                </form>
            ) : (
                <div>
                    <div className="text-lg font-semibold">{plantilla.nombre}</div>
                    {plantilla.descripcion && <p className="text-base-content/60 text-sm">{plantilla.descripcion}</p>}
                </div>
            )}

            {!plantilla.activo && (
                <div className="alert alert-warning py-2 text-sm">Desactivada: no se ofrece al crear un dosier nuevo.</div>
            )}

            {puedeEditar ? (
                <>
                    <EditorDeArbol valor={borrador} onChange={setBorrador} />
                    <div className="border-base-300 flex flex-wrap items-center gap-2 border-t pt-3">
                        <Button type="button" size="sm" onClick={guardarArbol} disabled={!cambiado || guardando}>
                            Guardar secciones
                        </Button>
                        <Button type="button" size="sm" variant="ghost" onClick={() => setBorrador(original)} disabled={!cambiado || guardando}>
                            Descartar cambios
                        </Button>
                        <span className="text-base-content/60 text-xs">
                            {contarSecciones(borrador)} secciones
                            {cambiado && ' · hay cambios sin guardar'}
                        </span>
                    </div>
                    {errores && <div className="alert alert-error py-2 text-sm">{errores}</div>}
                </>
            ) : (
                <ArbolDeLectura arbol={plantilla.arbol} />
            )}
        </div>
    );
}
