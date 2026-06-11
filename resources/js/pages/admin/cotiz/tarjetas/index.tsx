import { FormField } from '@/components/form';
import { Button, ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CotizGeneradora, CotizObra, CotizTarjeta } from '@/types/models';
import { Head, router, useForm } from '@inertiajs/react';
import { LockIcon, Loader2Icon, Trash2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = {
    obra: CotizObra;
    tarjetas: CotizTarjeta[];
    generadoras: Pick<CotizGeneradora, 'id' | 'titulo' | 'orden'>[];
};

const fmtMoney = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    maximumFractionDigits: 0,
});

export default function TarjetasIndex({ obra, tarjetas, generadoras }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cotización', href: '/admin/cotiz/obras' },
        { title: 'Obras', href: '/admin/cotiz/obras' },
        {
            title: obra.nombre,
            href: `/admin/cotiz/obras/${obra.id}/generadoras`,
        },
        {
            title: 'Tarjetas',
            href: `/admin/cotiz/obras/${obra.id}/tarjetas`,
        },
    ];

    const { data, setData, post, processing, errors, reset } = useForm({
        obra_id: obra.id,
        descripcion: '',
        orden: String(tarjetas.length + 1),
        generadora_id: '' as number | '',
    });

    const handleCreate = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/cotiz/tarjetas', {
            preserveScroll: true,
            onSuccess: () => reset('descripcion', 'generadora_id'),
        });
    };

    const handleDelete = (tarjeta: CotizTarjeta) => {
        if (tarjeta.is_locked) {
            return;
        }
        if (confirm(`¿Eliminar la tarjeta "${tarjeta.descripcion}"?`)) {
            router.delete(`/admin/cotiz/tarjetas/${tarjeta.id}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Tarjetas — ${obra.nombre}`} />

            <div className="space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Tarjetas</h1>
                    <p className="text-sm text-base-content/60">
                        Obra: {obra.nombre}
                        {obra.op ? ` · OP ${obra.op}` : ''} · {tarjetas.length}{' '}
                        tarjetas. Una tarjeta consolida los registros de una o
                        varias generadoras.
                    </p>
                </div>

                <form
                    onSubmit={handleCreate}
                    className="flex flex-wrap items-end gap-3 rounded-box border border-base-300 p-4"
                >
                    <div className="min-w-64 flex-1">
                        <FormField
                            label="Nueva tarjeta"
                            htmlFor="descripcion"
                            error={errors.descripcion}
                            required
                        >
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) =>
                                    setData('descripcion', e.target.value)
                                }
                                placeholder="Descripción (ej. Columnas 4PLS)"
                            />
                        </FormField>
                    </div>
                    <div className="w-64">
                        <FormField
                            label="Generadora inicial (opcional)"
                            htmlFor="generadora_id"
                            error={errors.generadora_id}
                        >
                            <select
                                id="generadora_id"
                                className="select w-full select-bordered"
                                value={data.generadora_id}
                                onChange={(e) =>
                                    setData(
                                        'generadora_id',
                                        e.target.value === ''
                                            ? ''
                                            : Number(e.target.value),
                                    )
                                }
                            >
                                <option value="">Sin vincular…</option>
                                {generadoras.map((g) => (
                                    <option key={g.id} value={g.id}>
                                        {g.titulo}
                                    </option>
                                ))}
                            </select>
                        </FormField>
                    </div>
                    <div className="w-24">
                        <FormField
                            label="Orden"
                            htmlFor="orden"
                            error={errors.orden}
                        >
                            <Input
                                id="orden"
                                type="number"
                                value={data.orden}
                                onChange={(e) =>
                                    setData('orden', e.target.value)
                                }
                            />
                        </FormField>
                    </div>
                    <Button type="submit" variant="primary" disabled={processing}>
                        {processing && (
                            <Loader2Icon className="size-4 animate-spin" />
                        )}
                        Agregar
                    </Button>
                </form>

                {tarjetas.length === 0 ? (
                    <div className="rounded-box border border-dashed border-base-300 p-8 text-center text-base-content/60">
                        No hay tarjetas. Crea la primera arriba.
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-box border border-base-300">
                        <table className="table">
                            <thead>
                                <tr>
                                    <th className="w-16">#</th>
                                    <th>Descripción</th>
                                    <th className="text-right">Registros</th>
                                    <th className="text-right">Generadoras</th>
                                    <th className="text-right">
                                        Importe (cache)
                                    </th>
                                    <th className="w-40"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {tarjetas.map((tarjeta) => (
                                    <tr key={tarjeta.id} className="hover">
                                        <td>{tarjeta.orden}</td>
                                        <td>
                                            <div className="flex items-center gap-2">
                                                <span className="font-medium">
                                                    {tarjeta.descripcion}
                                                </span>
                                                {tarjeta.is_locked && (
                                                    <span
                                                        className="flex items-center gap-1 text-xs text-warning"
                                                        title={`Editando: ${tarjeta.locked_by?.name ?? 'otro usuario'}`}
                                                    >
                                                        <LockIcon className="size-3.5" />
                                                        {tarjeta.locked_by
                                                            ?.name ?? 'Bloqueada'}
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="text-right">
                                            {tarjeta.registros_count ?? 0}
                                        </td>
                                        <td className="text-right">
                                            {tarjeta.generadoras_count ?? 0}
                                        </td>
                                        <td className="text-right">
                                            {tarjeta.importe_materiales == null
                                                ? '—'
                                                : fmtMoney.format(
                                                      Number(
                                                          tarjeta.importe_materiales,
                                                      ),
                                                  )}
                                        </td>
                                        <td>
                                            <div className="flex justify-end gap-1">
                                                <button
                                                    type="button"
                                                    className="btn text-error btn-ghost btn-xs"
                                                    title="Eliminar tarjeta"
                                                    disabled={tarjeta.is_locked}
                                                    onClick={() =>
                                                        handleDelete(tarjeta)
                                                    }
                                                >
                                                    <Trash2Icon className="size-4" />
                                                </button>
                                                <ButtonLink
                                                    variant="primary"
                                                    className="btn-xs"
                                                    href={`/admin/cotiz/tarjetas/${tarjeta.id}/edit`}
                                                >
                                                    Editar
                                                </ButtonLink>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
