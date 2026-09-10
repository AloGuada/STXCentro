import { router, useForm } from '@inertiajs/react';
import { CheckCircle2Icon, PencilIcon, TriangleAlertIcon, XCircleIcon } from 'lucide-react';
import { type ReactNode, useEffect, useState } from 'react';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

/** Un campo del catálogo. Casi todos son un texto; la vigencia es una fecha. */
export type CampoCatalogo = {
    k: string;
    label: string;
    ph?: string;
    req?: boolean;
    tipo?: 'text' | 'date';
    /** Se guarda siempre en mayúsculas: es una clave, no un nombre. */
    mayusculas?: boolean;
    /** Ancho en la rejilla del formulario. */
    ancho?: 'normal' | 'ancho';
};

/** Una fila cualquiera de catálogo: id, si sigue activa, y sus campos. */
export type FilaCatalogo = {
    id: number;
    activo: boolean;
} & Record<string, unknown>;

type Props = {
    /** Identifica el panel; por omisión es también el segmento de la ruta. */
    clave: string;
    /** Segmento de la ruta cuando no coincide con la clave (`defectos`). */
    ruta?: string;
    /** Valores que viajan en cada alta sin mostrarse, p. ej. el ámbito del defecto. */
    fijos?: Record<string, string>;
    descripcion: string;
    campos: CampoCatalogo[];
    filas: FilaCatalogo[];
    puedeCrear: boolean;
    puedeEditar: boolean;
    /** Lo que hay que saber antes de tocar esta lista. */
    ayuda?: ReactNode;
    /** Columna extra calculada, p. ej. el aviso de certificación vencida. */
    aviso?: (fila: FilaCatalogo) => ReactNode;
};

const vacio = (campos: CampoCatalogo[]): Record<string, string> =>
    Object.fromEntries(campos.map((c) => [c.k, '']));

/**
 * Un catálogo de Calidad: el alta arriba y la lista abajo, en la misma pantalla.
 *
 * Se edita aquí y no entrando renglón por renglón porque es lo que se viene a
 * hacer: corregir dos nombres y salir. Obligar a navegar a una pantalla de
 * edición por cada uno lo volvería inservible para una lista de 86 soldadores.
 *
 * No hay botón de borrar en ningún catálogo. Desactivar saca el valor de los
 * desplegables pero **no toca los registros ya guardados** — borrarlo dejaría
 * reportes citando algo que ya no existe.
 */
export function CatalogoPanel({
    clave,
    ruta,
    fijos,
    descripcion,
    campos,
    filas,
    puedeCrear,
    puedeEditar,
    ayuda,
    aviso,
}: Props) {
    const [editando, setEditando] = useState<number | null>(null);
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm<Record<string, string>>({
        ...vacio(campos),
        ...fijos,
    });

    // Cambiar de pestaña no debe dejar a medias la edición de la anterior.
    useEffect(() => {
        setEditando(null);
        reset();
        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [clave]);

    const base = `/admin/calidad/catalogos/${ruta ?? clave}`;

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();

        const opciones = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setEditando(null);
            },
        };

        if (editando === null) {
            post(base, opciones);
        } else {
            put(`${base}/${editando}`, opciones);
        }
    };

    const editar = (fila: FilaCatalogo) => {
        setEditando(fila.id);
        campos.forEach((c) => setData(c.k, fila[c.k] == null ? '' : String(fila[c.k])));
        clearErrors();
    };

    const cancelar = () => {
        setEditando(null);
        reset();
        clearErrors();
    };

    const alternar = (fila: FilaCatalogo) => {
        router.patch(`${base}/${fila.id}/toggle`, {}, { preserveScroll: true });
    };

    const puedeCapturar = editando === null ? puedeCrear : puedeEditar;
    const activos = filas.filter((f) => f.activo).length;

    return (
        <div>
            <p className="text-base-content/60 mb-4 text-sm">{descripcion}</p>

            {puedeCapturar && (
                <form onSubmit={enviar} className="rounded-box border-base-300 mb-4 border p-4">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        {campos.map((c) => (
                            <FormField
                                key={c.k}
                                label={c.label}
                                htmlFor={`${clave}-${c.k}`}
                                required={c.req}
                                error={errors[c.k]}
                                className={c.ancho === 'ancho' ? 'md:col-span-2' : undefined}
                            >
                                <Input
                                    id={`${clave}-${c.k}`}
                                    type={c.tipo === 'date' ? 'date' : 'text'}
                                    value={data[c.k] ?? ''}
                                    onChange={(e) =>
                                        setData(c.k, c.mayusculas ? e.target.value.toUpperCase() : e.target.value)
                                    }
                                    placeholder={c.ph}
                                    className={c.mayusculas ? 'font-mono' : undefined}
                                    error={!!errors[c.k]}
                                />
                            </FormField>
                        ))}
                    </div>

                    <div className="mt-4 flex flex-wrap gap-2">
                        <Button type="submit" variant="primary" disabled={processing}>
                            {editando === null ? 'Añadir' : 'Guardar cambios'}
                        </Button>
                        {editando !== null && (
                            <Button type="button" variant="outline" onClick={cancelar}>
                                Cancelar
                            </Button>
                        )}
                    </div>
                </form>
            )}

            <div className="rounded-box border-base-300 overflow-x-auto border">
                <table className="table table-sm">
                    <thead className="bg-base-200">
                        <tr>
                            {campos.map((c) => (
                                <th key={c.k}>{c.label}</th>
                            ))}
                            {aviso && <th></th>}
                            <th className="w-28 text-center">Estado</th>
                            {puedeEditar && <th className="w-24 text-right">Acciones</th>}
                        </tr>
                    </thead>
                    <tbody>
                        {filas.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={campos.length + (aviso ? 1 : 0) + (puedeEditar ? 2 : 1)}
                                    className="text-base-content/50 py-6 text-center"
                                >
                                    Esta lista está vacía.
                                    {puedeCrear && ' Añade el primer valor arriba.'}
                                </td>
                            </tr>
                        ) : (
                            filas.map((fila) => (
                                <tr
                                    key={fila.id}
                                    className={fila.activo ? 'hover' : 'hover opacity-50'}
                                >
                                    {campos.map((c) => (
                                        <td key={c.k} className={c.mayusculas ? 'font-mono' : undefined}>
                                            {fila[c.k] == null || fila[c.k] === '' ? (
                                                <span className="text-base-content/30">—</span>
                                            ) : (
                                                String(fila[c.k])
                                            )}
                                        </td>
                                    ))}
                                    {aviso && <td>{aviso(fila)}</td>}
                                    <td className="text-center">
                                        {fila.activo ? (
                                            <span className="badge badge-sm badge-success">activo</span>
                                        ) : (
                                            <span className="badge badge-sm badge-ghost">desactivado</span>
                                        )}
                                    </td>
                                    {puedeEditar && (
                                        <td>
                                            <div className="flex justify-end gap-1">
                                                <button
                                                    type="button"
                                                    className="btn btn-ghost btn-xs"
                                                    onClick={() => editar(fila)}
                                                    title="Editar"
                                                >
                                                    <PencilIcon className="size-3.5" />
                                                </button>
                                                <button
                                                    type="button"
                                                    className="btn btn-ghost btn-xs"
                                                    onClick={() => alternar(fila)}
                                                    title={fila.activo ? 'Desactivar' : 'Reactivar'}
                                                >
                                                    {fila.activo ? (
                                                        <XCircleIcon className="size-3.5" />
                                                    ) : (
                                                        <CheckCircle2Icon className="size-3.5" />
                                                    )}
                                                </button>
                                            </div>
                                        </td>
                                    )}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>

                {filas.length > 0 && (
                    <div className="border-base-300 bg-base-200 text-base-content/70 border-t px-4 py-2 text-sm">
                        {activos} activo(s) de {filas.length}
                    </div>
                )}
            </div>

            {ayuda && (
                <p className="text-base-content/60 mt-4 flex gap-2 text-sm">
                    <TriangleAlertIcon className="text-base-content/40 mt-0.5 size-4 shrink-0" />
                    <span>{ayuda}</span>
                </p>
            )}
        </div>
    );
}
