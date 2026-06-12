import { Head, router, useForm } from '@inertiajs/react';
import { HistoryIcon, RotateCcwIcon, Trash2Icon } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Version = {
    id: number;
    nombre: string;
    nota: string | null;
    auto: boolean;
    creado_por: string | null;
    created_at: string | null;
};

type Diff = {
    obra: Record<string, { de: unknown; a: unknown }>;
    grupos: Record<string, { de: number; a: number; cambio: boolean }>;
};

type Props = {
    obra: { id: number; nombre: string };
    versiones: Version[];
    diff: Diff | null;
    comparando: {
        a: number;
        b: number;
        a_nombre: string;
        b_nombre: string;
    } | null;
};

function fmtFecha(iso: string | null): string {
    if (!iso) return '';
    return new Date(iso).toLocaleString('es-MX', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

export default function VersionesIndex({
    obra,
    versiones,
    diff,
    comparando,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cotización', href: '/admin/cotiz/obras' },
        { title: 'Obras', href: '/admin/cotiz/obras' },
        {
            title: obra.nombre,
            href: `/admin/cotiz/obras/${obra.id}/generadoras`,
        },
        { title: 'Versiones', href: `/admin/cotiz/obras/${obra.id}/versiones` },
    ];

    const form = useForm({ nombre: '', nota: '' });
    const [a, setA] = useState<number | ''>(comparando?.a ?? '');
    const [b, setB] = useState<number | ''>(comparando?.b ?? '');

    const crear = () => {
        form.post(`/admin/cotiz/obras/${obra.id}/versiones`, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const restaurar = (v: Version) => {
        if (
            confirm(
                `Restaurar «${v.nombre}» SOBRESCRIBE el estado actual de la obra. ` +
                    `Se guardará un respaldo automático del estado actual antes de hacerlo. ¿Continuar?`,
            )
        ) {
            router.post(
                `/admin/cotiz/obras/${obra.id}/versiones/${v.id}/restaurar`,
                {},
                { preserveScroll: true },
            );
        }
    };

    const comparar = () => {
        if (a === '' || b === '' || a === b) return;
        router.get(
            `/admin/cotiz/obras/${obra.id}/versiones`,
            { a, b },
            { preserveScroll: true, preserveState: true },
        );
    };

    const gruposCambiados = diff
        ? Object.entries(diff.grupos).filter(
              ([, g]) => g.cambio || g.de !== g.a,
          )
        : [];
    const obraCambios = diff ? Object.entries(diff.obra) : [];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Versiones — ${obra.nombre}`} />

            <div className="space-y-4 p-6">
                <div>
                    <h1 className="flex items-center gap-2 text-2xl font-semibold">
                        <HistoryIcon className="size-6" /> Versiones
                    </h1>
                    <p className="text-sm text-base-content/60">
                        Obra: {obra.nombre} · snapshots continuos de los datos
                        de entrada (historial lineal).
                    </p>
                </div>

                <div className="card border border-base-300 bg-base-100 p-4">
                    <h3 className="mb-2 text-sm font-semibold">
                        Crear versión
                    </h3>
                    <div className="flex flex-wrap items-end gap-2">
                        <label className="flex flex-col gap-1 text-sm">
                            <span className="opacity-70">Nombre</span>
                            <input
                                className="input-bordered input input-sm w-56"
                                value={form.data.nombre}
                                onChange={(e) =>
                                    form.setData('nombre', e.target.value)
                                }
                                placeholder="p. ej. Revisión cliente"
                            />
                        </label>
                        <label className="flex flex-col gap-1 text-sm">
                            <span className="opacity-70">Nota (opcional)</span>
                            <input
                                className="input-bordered input input-sm w-72"
                                value={form.data.nota}
                                onChange={(e) =>
                                    form.setData('nota', e.target.value)
                                }
                            />
                        </label>
                        <Button
                            type="button"
                            variant="primary"
                            disabled={!form.data.nombre || form.processing}
                            onClick={crear}
                        >
                            Guardar snapshot
                        </Button>
                    </div>
                    {form.errors.nombre && (
                        <p className="mt-1 text-xs text-error">
                            {form.errors.nombre}
                        </p>
                    )}
                </div>

                {versiones.length >= 2 && (
                    <div className="card border border-base-300 bg-base-100 p-4">
                        <h3 className="mb-2 text-sm font-semibold">
                            Comparar dos versiones
                        </h3>
                        <div className="flex flex-wrap items-end gap-2">
                            <VersionSelect
                                label="Desde"
                                value={a}
                                onChange={setA}
                                versiones={versiones}
                            />
                            <VersionSelect
                                label="Hasta"
                                value={b}
                                onChange={setB}
                                versiones={versiones}
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                disabled={a === '' || b === '' || a === b}
                                onClick={comparar}
                            >
                                Comparar
                            </Button>
                        </div>

                        {diff && comparando && (
                            <div className="mt-3 text-sm">
                                <p className="mb-2 opacity-70">
                                    «{comparando.a_nombre}» → «
                                    {comparando.b_nombre}»
                                </p>
                                {obraCambios.length === 0 &&
                                gruposCambiados.length === 0 ? (
                                    <p className="italic opacity-60">
                                        Sin diferencias en los inputs.
                                    </p>
                                ) : (
                                    <div className="space-y-2">
                                        {obraCambios.map(([campo, c]) => (
                                            <div
                                                key={campo}
                                                className="text-xs"
                                            >
                                                <span className="font-mono">
                                                    {campo}
                                                </span>
                                                : {String(c.de)} → {String(c.a)}
                                            </div>
                                        ))}
                                        {gruposCambiados.length > 0 && (
                                            <table className="table table-xs">
                                                <thead>
                                                    <tr>
                                                        <th>Grupo</th>
                                                        <th className="text-right">
                                                            Antes
                                                        </th>
                                                        <th className="text-right">
                                                            Después
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {gruposCambiados.map(
                                                        ([grupo, g]) => (
                                                            <tr key={grupo}>
                                                                <td className="font-mono text-xs">
                                                                    {grupo}
                                                                </td>
                                                                <td className="text-right">
                                                                    {g.de}
                                                                </td>
                                                                <td className="text-right font-semibold">
                                                                    {g.a}
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )}
                                                </tbody>
                                            </table>
                                        )}
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                )}

                <div className="card border border-base-300 bg-base-100 p-4">
                    <h3 className="mb-2 text-sm font-semibold">
                        Historial ({versiones.length})
                    </h3>
                    {versiones.length === 0 ? (
                        <p className="text-sm italic opacity-60">
                            Aún no hay versiones. Crea la primera arriba.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Versión</th>
                                        <th>Nota</th>
                                        <th>Autor</th>
                                        <th>Fecha</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {versiones.map((v) => (
                                        <tr key={v.id}>
                                            <td className="font-medium">
                                                {v.nombre}
                                                {v.auto && (
                                                    <span className="ml-2 badge badge-ghost badge-sm">
                                                        auto
                                                    </span>
                                                )}
                                            </td>
                                            <td className="text-xs opacity-70">
                                                {v.nota ?? '—'}
                                            </td>
                                            <td className="text-xs">
                                                {v.creado_por ?? '—'}
                                            </td>
                                            <td className="text-xs">
                                                {fmtFecha(v.created_at)}
                                            </td>
                                            <td className="text-right">
                                                <div className="flex justify-end gap-1">
                                                    <button
                                                        type="button"
                                                        className="btn btn-ghost btn-xs"
                                                        title="Restaurar (sobrescribe el estado actual)"
                                                        onClick={() =>
                                                            restaurar(v)
                                                        }
                                                    >
                                                        <RotateCcwIcon className="size-4" />{' '}
                                                        Restaurar
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="btn text-error btn-ghost btn-xs"
                                                        title="Eliminar versión"
                                                        onClick={() => {
                                                            if (
                                                                confirm(
                                                                    `¿Eliminar la versión «${v.nombre}»?`,
                                                                )
                                                            ) {
                                                                router.delete(
                                                                    `/admin/cotiz/obras/${obra.id}/versiones/${v.id}`,
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                );
                                                            }
                                                        }}
                                                    >
                                                        <Trash2Icon className="size-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

function VersionSelect({
    label,
    value,
    onChange,
    versiones,
}: {
    label: string;
    value: number | '';
    onChange: (v: number | '') => void;
    versiones: Version[];
}) {
    return (
        <label className="flex flex-col gap-1 text-sm">
            <span className="opacity-70">{label}</span>
            <select
                className="select-bordered select w-56 select-sm"
                value={value}
                onChange={(e) =>
                    onChange(
                        e.target.value === '' ? '' : Number(e.target.value),
                    )
                }
            >
                <option value="">Selecciona…</option>
                {versiones.map((v) => (
                    <option key={v.id} value={v.id}>
                        {v.nombre}
                    </option>
                ))}
            </select>
        </label>
    );
}
