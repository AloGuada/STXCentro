import { FormField } from '@/components/form';
import { Button, ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CotizGeneradora, CotizObra } from '@/types/models';
import { Head, router, useForm } from '@inertiajs/react';
import { LockIcon, Loader2Icon, Trash2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = {
    obra: CotizObra;
    generadoras: CotizGeneradora[];
};

export default function GeneradorasIndex({ obra, generadoras }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cotización', href: '/admin/cotiz/obras' },
        { title: 'Obras', href: '/admin/cotiz/obras' },
        {
            title: obra.nombre,
            href: `/admin/cotiz/obras/${obra.id}/generadoras`,
        },
    ];

    const { data, setData, post, processing, errors, reset } = useForm({
        obra_id: obra.id,
        titulo: '',
        orden: String(generadoras.length + 1),
    });

    const handleCreate = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/cotiz/generadoras', {
            preserveScroll: true,
            onSuccess: () => reset('titulo'),
        });
    };

    const handleDelete = (generadora: CotizGeneradora) => {
        if (generadora.is_locked) {
            return;
        }
        if (confirm(`¿Eliminar la generadora "${generadora.titulo}"?`)) {
            router.delete(`/admin/cotiz/generadoras/${generadora.id}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Generadoras — ${obra.nombre}`} />

            <div className="space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Generadoras</h1>
                    <p className="text-sm text-base-content/60">
                        Obra: {obra.nombre}
                        {obra.op ? ` · OP ${obra.op}` : ''} ·{' '}
                        {generadoras.length} generadoras
                    </p>
                </div>

                <form
                    onSubmit={handleCreate}
                    className="flex items-end gap-3 rounded-box border border-base-300 p-4"
                >
                    <div className="flex-1">
                        <FormField
                            label="Nueva generadora"
                            htmlFor="titulo"
                            error={errors.titulo}
                            required
                        >
                            <Input
                                id="titulo"
                                value={data.titulo}
                                onChange={(e) =>
                                    setData('titulo', e.target.value)
                                }
                                placeholder="Título de la generadora"
                            />
                        </FormField>
                    </div>
                    <div className="w-28">
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
                    <Button
                        type="submit"
                        variant="primary"
                        disabled={processing}
                    >
                        {processing && (
                            <Loader2Icon className="size-4 animate-spin" />
                        )}
                        Agregar
                    </Button>
                </form>

                {generadoras.length === 0 ? (
                    <div className="rounded-box border border-dashed border-base-300 p-8 text-center text-base-content/60">
                        No hay generadoras. Crea la primera arriba.
                    </div>
                ) : (
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {generadoras.map((generadora) => (
                            <div
                                key={generadora.id}
                                className="card border border-base-300 bg-base-100"
                            >
                                <div className="card-body gap-3 p-4">
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <h2 className="card-title text-base">
                                                {generadora.titulo}
                                            </h2>
                                            <p className="text-xs text-base-content/60">
                                                #{generadora.orden} ·{' '}
                                                {generadora.registros_count ??
                                                    0}{' '}
                                                registros
                                            </p>
                                        </div>
                                        <span className="badge badge-ghost badge-sm">
                                            #{generadora.id}
                                        </span>
                                    </div>

                                    {generadora.is_locked && (
                                        <div className="flex items-center gap-1 text-xs text-warning">
                                            <LockIcon className="size-3.5" />
                                            <span>
                                                Editando:{' '}
                                                {generadora.locked_by?.name ??
                                                    'Otro usuario'}
                                            </span>
                                        </div>
                                    )}

                                    <div className="card-actions justify-end">
                                        <button
                                            type="button"
                                            className="btn text-error btn-ghost btn-xs"
                                            title="Eliminar generadora"
                                            disabled={generadora.is_locked}
                                            onClick={() =>
                                                handleDelete(generadora)
                                            }
                                        >
                                            <Trash2Icon className="size-4" />
                                        </button>
                                        <ButtonLink
                                            variant="primary"
                                            className="btn-sm"
                                            href={`/admin/cotiz/generadoras/${generadora.id}/edit`}
                                        >
                                            Editar
                                        </ButtonLink>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
