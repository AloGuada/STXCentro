import { Link, router, useForm } from '@inertiajs/react';
import { ArrowDownIcon, ArrowUpIcon, PencilIcon, PenToolIcon, Trash2Icon, TriangleAlertIcon } from 'lucide-react';
import { useState } from 'react';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { QalFirmante, QalOpcionOrigenFirmante, QalOrigenFirmante, QalUsuarioFirmante } from '@/types/models';

type Props = {
    firmantes: QalFirmante[];
    usuarios: QalUsuarioFirmante[];
    origenes: QalOpcionOrigenFirmante[];
    puedeEditar: boolean;
};

type Formulario = {
    etiqueta: string;
    cargo: string;
    origen: QalOrigenFirmante;
    usuario_id: string;
};

const BASE = '/admin/calidad/catalogos/firmantes';
const VACIO: Formulario = { etiqueta: '', cargo: '', origen: 'usuario', usuario_id: '' };

/**
 * Quién firma los formatos PDF de Calidad y en qué orden.
 *
 * Es un solo arreglo para todos los formatos. Lo normal son dos lugares: quien
 * elaboró el documento —cambia con cada hoja— y la jefatura de calidad —una
 * persona fija—. La rúbrica la dibuja cada quien en «Mi firma»; sin ella, o
 * sin persona elegida, la raya sale en blanco para firmarse a mano.
 */
export function CatalogoFirmantes({ firmantes, usuarios, origenes, puedeEditar }: Props) {
    const [editando, setEditando] = useState<number | null>(null);
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm<Formulario>(VACIO);

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
            post(BASE, opciones);
        } else {
            put(`${BASE}/${editando}`, opciones);
        }
    };

    const editar = (firmante: QalFirmante) => {
        setEditando(firmante.id);
        setData({
            etiqueta: firmante.etiqueta,
            cargo: firmante.cargo,
            origen: firmante.origen,
            usuario_id: firmante.usuario_id ?? '',
        });
        clearErrors();
    };

    const cancelar = () => {
        setEditando(null);
        reset();
        clearErrors();
    };

    const mover = (indice: number, paso: -1 | 1) => {
        const ids = firmantes.map((f) => f.id);
        const destino = indice + paso;
        [ids[indice], ids[destino]] = [ids[destino], ids[indice]];

        router.put(`${BASE}/orden`, { ids }, { preserveScroll: true });
    };

    const quitar = (firmante: QalFirmante) => {
        if (!confirm(`¿Quitar «${firmante.etiqueta} · ${firmante.cargo}» de las firmas?`)) {
            return;
        }

        router.delete(`${BASE}/${firmante.id}`, { preserveScroll: true });
    };

    const elegido = usuarios.find((u) => u.id === data.usuario_id);

    return (
        <div>
            <p className="text-base-content/60 mb-4 text-sm">
                Quién firma los formatos PDF de Calidad y en qué orden. Lo normal: quien elaboró el documento y la
                jefatura de calidad. La rúbrica la dibuja cada quien en{' '}
                <Link href="/admin/mi-firma" className="link link-primary">
                    Mi firma
                </Link>
                ; sin ella la raya sale en blanco para firmarse a mano.
            </p>

            <div className="rounded-box border-base-300 mb-4 border p-4">
                <div className="text-base-content/50 mb-3 text-xs font-semibold uppercase">Así sale en la hoja</div>
                {firmantes.length === 0 ? (
                    <div className="text-base-content/50 text-sm">Sin firmas: los formatos saldrán sin espacio para firmar.</div>
                ) : (
                    <div className="grid gap-4" style={{ gridTemplateColumns: `repeat(${firmantes.length}, minmax(0, 1fr))` }}>
                        {firmantes.map((f) => (
                            <div key={f.id} className="text-center">
                                <div className="text-base-content/60 text-[10px] font-bold uppercase">{f.etiqueta}</div>
                                <div className="flex h-10 items-end justify-center">
                                    {f.tiene_rubrica || f.origen === 'creador' ? (
                                        <PenToolIcon className="text-base-content/30 size-5" />
                                    ) : null}
                                </div>
                                <div className="border-base-content/60 border-t pt-1 text-xs font-semibold">
                                    {f.origen === 'creador' ? 'quien elaboró' : (f.usuario ?? ' ')}
                                </div>
                                <div className="text-base-content/60 text-[11px]">{f.cargo}</div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {puedeEditar && (
                <form onSubmit={enviar} className="rounded-box border-base-300 mb-4 border p-4">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                        <FormField label="Sobre la firma" htmlFor="firmante-etiqueta" required error={errors.etiqueta}>
                            <Input
                                id="firmante-etiqueta"
                                value={data.etiqueta}
                                onChange={(e) => setData('etiqueta', e.target.value)}
                                placeholder="Revisó"
                                maxLength={20}
                                error={!!errors.etiqueta}
                            />
                        </FormField>
                        <FormField label="Cargo" htmlFor="firmante-cargo" required error={errors.cargo}>
                            <Input
                                id="firmante-cargo"
                                value={data.cargo}
                                onChange={(e) => setData('cargo', e.target.value)}
                                placeholder="Jefatura de calidad"
                                maxLength={80}
                                error={!!errors.cargo}
                            />
                        </FormField>
                        <FormField label="Quién firma" htmlFor="firmante-origen" required error={errors.origen}>
                            <select
                                id="firmante-origen"
                                className="select w-full"
                                value={data.origen}
                                onChange={(e) => setData('origen', e.target.value as QalOrigenFirmante)}
                            >
                                {origenes.map((o) => (
                                    <option key={o.valor} value={o.valor}>
                                        {o.etiqueta}
                                    </option>
                                ))}
                            </select>
                        </FormField>
                        {data.origen === 'usuario' && (
                            <FormField label="Persona" htmlFor="firmante-usuario" error={errors.usuario_id}>
                                <select
                                    id="firmante-usuario"
                                    className="select w-full"
                                    value={data.usuario_id}
                                    onChange={(e) => setData('usuario_id', e.target.value)}
                                >
                                    <option value="">Sin elegir · raya en blanco</option>
                                    {usuarios.map((u) => (
                                        <option key={u.id} value={u.id}>
                                            {u.nombre}
                                            {u.tiene_rubrica ? '' : ' · sin rúbrica'}
                                        </option>
                                    ))}
                                </select>
                            </FormField>
                        )}
                    </div>

                    {data.origen === 'usuario' && elegido && !elegido.tiene_rubrica && (
                        <p className="text-warning mt-3 flex gap-2 text-sm">
                            <TriangleAlertIcon className="mt-0.5 size-4 shrink-0" />
                            {elegido.nombre} todavía no dibuja su firma: su nombre sale impreso y la raya en blanco hasta
                            que lo haga en «Mi firma».
                        </p>
                    )}

                    <div className="mt-4 flex flex-wrap gap-2">
                        <Button type="submit" variant="primary" disabled={processing}>
                            {editando === null ? 'Añadir firma' : 'Guardar cambios'}
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
                            <th className="w-12">Orden</th>
                            <th>Sobre la firma</th>
                            <th>Cargo</th>
                            <th>Quién firma</th>
                            <th>Rúbrica</th>
                            {puedeEditar && <th className="w-40 text-right">Acciones</th>}
                        </tr>
                    </thead>
                    <tbody>
                        {firmantes.length === 0 ? (
                            <tr>
                                <td colSpan={puedeEditar ? 6 : 5} className="text-base-content/50 py-6 text-center">
                                    No hay firmas configuradas.
                                </td>
                            </tr>
                        ) : (
                            firmantes.map((f, i) => (
                                <tr key={f.id} className="hover">
                                    <td className="font-mono">{i + 1}</td>
                                    <td className="font-semibold">{f.etiqueta}</td>
                                    <td>{f.cargo}</td>
                                    <td>
                                        {f.origen === 'creador' ? (
                                            <span className="text-base-content/70">Quien elaboró el documento</span>
                                        ) : (
                                            (f.usuario ?? <span className="text-base-content/40">Sin elegir · raya en blanco</span>)
                                        )}
                                    </td>
                                    <td>
                                        {f.origen === 'creador' ? (
                                            <span className="text-base-content/50 text-xs">la de quien elaboró</span>
                                        ) : !f.usuario ? (
                                            <span className="text-base-content/30">—</span>
                                        ) : f.tiene_rubrica ? (
                                            <span className="badge badge-sm badge-success">con rúbrica</span>
                                        ) : (
                                            <span className="badge badge-sm badge-warning">sin rúbrica</span>
                                        )}
                                    </td>
                                    {puedeEditar && (
                                        <td>
                                            <div className="flex justify-end gap-1">
                                                <button
                                                    type="button"
                                                    className="btn btn-ghost btn-xs"
                                                    onClick={() => mover(i, -1)}
                                                    disabled={i === 0}
                                                    title="Subir"
                                                >
                                                    <ArrowUpIcon className="size-3.5" />
                                                </button>
                                                <button
                                                    type="button"
                                                    className="btn btn-ghost btn-xs"
                                                    onClick={() => mover(i, 1)}
                                                    disabled={i === firmantes.length - 1}
                                                    title="Bajar"
                                                >
                                                    <ArrowDownIcon className="size-3.5" />
                                                </button>
                                                <button
                                                    type="button"
                                                    className="btn btn-ghost btn-xs"
                                                    onClick={() => editar(f)}
                                                    title="Editar"
                                                >
                                                    <PencilIcon className="size-3.5" />
                                                </button>
                                                <button
                                                    type="button"
                                                    className="btn btn-ghost btn-xs text-error"
                                                    onClick={() => quitar(f)}
                                                    title="Quitar"
                                                >
                                                    <Trash2Icon className="size-3.5" />
                                                </button>
                                            </div>
                                        </td>
                                    )}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
