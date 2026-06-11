import { Button, ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCotizEditLock } from '@/hooks/use-cotiz-edit-lock';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CotizGeneradora, CotizObra } from '@/types/models';
import { Head, router, useForm } from '@inertiajs/react';
import { LockIcon, Loader2Icon, Trash2Icon, UnlinkIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';

type TarjetaProp = {
    id: number;
    obra_id: number;
    descripcion: string;
    orden: number;
    importe_materiales: string | null;
    kilos_reales: string | null;
    obra: CotizObra;
    generadoras: Pick<CotizGeneradora, 'id' | 'titulo' | 'orden'>[];
    registros_count: number;
};

type Totales = {
    total_importe: number;
    total_registros: number;
    total_factores: number;
    kg_fab: number;
    area_pintura: number;
    kg_reales_total: number;
};

type Props = {
    tarjeta: TarjetaProp;
    totales: Totales;
    generadorasDisponibles: Pick<CotizGeneradora, 'id' | 'titulo' | 'orden'>[];
    lock: {
        is_locked: boolean;
        locked_by: { id: string; name: string } | null;
        locked_at: string | null;
    };
};

const fmtMoney = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});
const fmtNum = (n: number, d = 2) => Number(n).toFixed(d);

function formatDesde(iso: string | null): string {
    if (!iso) {
        return '';
    }
    const diff = (Date.now() - new Date(iso).getTime()) / 60000;
    if (diff < 1) {
        return 'hace un momento';
    }
    if (diff < 60) {
        return `hace ${Math.round(diff)} min`;
    }
    return `hace ${Math.round(diff / 60)} h`;
}

export default function TarjetaEdit({
    tarjeta,
    totales,
    generadorasDisponibles,
    lock,
}: Props) {
    const lockState = useCotizEditLock('tarjeta', tarjeta.id);
    const readOnly = lockState.status !== 'owned';

    const obra = tarjeta.obra;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cotización', href: '/admin/cotiz/obras' },
        { title: 'Obras', href: '/admin/cotiz/obras' },
        {
            title: obra.nombre,
            href: `/admin/cotiz/obras/${obra.id}/tarjetas`,
        },
        { title: 'Tarjetas', href: `/admin/cotiz/obras/${obra.id}/tarjetas` },
        {
            title: tarjeta.descripcion,
            href: `/admin/cotiz/tarjetas/${tarjeta.id}/edit`,
        },
    ];

    const { data, setData, put, processing, errors } = useForm({
        descripcion: tarjeta.descripcion,
        orden: String(tarjeta.orden),
    });

    const guardarCabecera = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/cotiz/tarjetas/${tarjeta.id}`, { preserveScroll: true });
    };

    const [generadoraId, setGeneradoraId] = useState<number | ''>('');

    const vincular = () => {
        if (generadoraId === '') {
            return;
        }
        router.post(
            `/admin/cotiz/tarjetas/${tarjeta.id}/generadoras`,
            { generadora_id: generadoraId },
            {
                preserveScroll: true,
                onSuccess: () => setGeneradoraId(''),
            },
        );
    };

    const desvincular = (g: Pick<CotizGeneradora, 'id' | 'titulo'>) => {
        if (
            !confirm(
                `¿Desvincular "${g.titulo}"? Se quitarán sus registros importados de esta tarjeta.`,
            )
        ) {
            return;
        }
        router.delete(
            `/admin/cotiz/tarjetas/${tarjeta.id}/generadoras/${g.id}`,
            { preserveScroll: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar — ${tarjeta.descripcion}`} />

            <div className="space-y-4 p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        {tarjeta.descripcion}
                    </h1>
                    <p className="text-sm text-base-content/60">
                        Obra: {obra.nombre} · {tarjeta.registros_count} registros
                        · {tarjeta.generadoras.length} generadoras vinculadas
                    </p>
                </div>

                <LockBanner state={lockState} fallback={lock} />

                <form
                    onSubmit={guardarCabecera}
                    className="flex flex-wrap items-end gap-3 rounded-box border border-base-300 p-4"
                >
                    <div className="min-w-64 flex-1">
                        <label className="label" htmlFor="descripcion">
                            <span className="label-text">Descripción</span>
                        </label>
                        <Input
                            id="descripcion"
                            value={data.descripcion}
                            disabled={readOnly}
                            onChange={(e) =>
                                setData('descripcion', e.target.value)
                            }
                        />
                        {errors.descripcion && (
                            <p className="mt-1 text-xs text-error">
                                {errors.descripcion}
                            </p>
                        )}
                    </div>
                    <div className="w-24">
                        <label className="label" htmlFor="orden">
                            <span className="label-text">Orden</span>
                        </label>
                        <Input
                            id="orden"
                            type="number"
                            value={data.orden}
                            disabled={readOnly}
                            onChange={(e) => setData('orden', e.target.value)}
                        />
                    </div>
                    <Button
                        type="submit"
                        variant="primary"
                        disabled={readOnly || processing}
                    >
                        {processing && (
                            <Loader2Icon className="size-4 animate-spin" />
                        )}
                        Guardar
                    </Button>
                </form>

                <div className="rounded-box border border-base-300 p-4">
                    <h2 className="mb-3 font-medium">Generadoras vinculadas</h2>

                    {tarjeta.generadoras.length === 0 ? (
                        <p className="text-sm text-base-content/60">
                            Aún no hay generadoras vinculadas.
                        </p>
                    ) : (
                        <ul className="divide-y divide-base-200">
                            {tarjeta.generadoras.map((g) => (
                                <li
                                    key={g.id}
                                    className="flex items-center justify-between py-2"
                                >
                                    <span>{g.titulo}</span>
                                    <button
                                        type="button"
                                        className="btn text-error btn-ghost btn-xs"
                                        disabled={readOnly}
                                        onClick={() => desvincular(g)}
                                        title="Desvincular generadora"
                                    >
                                        <UnlinkIcon className="size-4" />
                                        Desvincular
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}

                    {generadorasDisponibles.length > 0 && (
                        <div className="mt-4 flex items-end gap-2">
                            <select
                                className="select select-bordered"
                                value={generadoraId}
                                disabled={readOnly}
                                onChange={(e) =>
                                    setGeneradoraId(
                                        e.target.value === ''
                                            ? ''
                                            : Number(e.target.value),
                                    )
                                }
                            >
                                <option value="">
                                    Vincular generadora…
                                </option>
                                {generadorasDisponibles.map((g) => (
                                    <option key={g.id} value={g.id}>
                                        {g.titulo}
                                    </option>
                                ))}
                            </select>
                            <Button
                                type="button"
                                variant="secondary"
                                disabled={readOnly || generadoraId === ''}
                                onClick={vincular}
                            >
                                Vincular
                            </Button>
                        </div>
                    )}
                </div>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    <TotalCard
                        label="Total importe"
                        value={fmtMoney.format(totales.total_importe)}
                        accent
                    />
                    <TotalCard
                        label="Materiales"
                        value={fmtMoney.format(totales.total_registros)}
                    />
                    <TotalCard
                        label="Factores"
                        value={fmtMoney.format(totales.total_factores)}
                    />
                    <TotalCard
                        label="Kg fab."
                        value={fmtNum(totales.kg_fab)}
                    />
                    <TotalCard
                        label="Área pintura (m²)"
                        value={fmtNum(totales.area_pintura)}
                    />
                    <TotalCard
                        label="Kg reales"
                        value={fmtNum(totales.kg_reales_total)}
                    />
                </div>

                <div className="rounded-box border border-dashed border-base-300 p-6 text-center text-sm text-base-content/60">
                    La grilla editable (registros, factores, pintura, kilos
                    reales) llega en la siguiente entrega (Fase 3c). Los totales
                    de arriba ya se calculan en el backend.
                </div>

                <div className="flex justify-end">
                    <ButtonLink
                        variant="ghost"
                        href={`/admin/cotiz/obras/${obra.id}/tarjetas`}
                    >
                        ← Volver a tarjetas
                    </ButtonLink>
                </div>
            </div>
        </AppLayout>
    );
}

function TotalCard({
    label,
    value,
    accent,
}: {
    label: string;
    value: string;
    accent?: boolean;
}) {
    return (
        <div
            className={`rounded-box border p-3 ${
                accent
                    ? 'border-primary/40 bg-primary/5'
                    : 'border-base-300'
            }`}
        >
            <p className="text-xs text-base-content/60">{label}</p>
            <p
                className={`mt-1 font-semibold ${accent ? 'text-primary' : ''}`}
            >
                {value}
            </p>
        </div>
    );
}

type LockBannerProps = {
    state: ReturnType<typeof useCotizEditLock>;
    fallback: Props['lock'];
};

function LockBanner({ state, fallback }: LockBannerProps) {
    if (state.status === 'taking') {
        return (
            <div className="alert">
                <Loader2Icon className="size-5 animate-spin" />
                <span>Iniciando sesión de edición…</span>
            </div>
        );
    }

    if (state.status === 'owned') {
        return null;
    }

    if (state.status === 'error') {
        return (
            <div className="alert alert-error">
                <LockIcon className="size-5" />
                <span>
                    {state.message} Puede ver los datos pero no guardar cambios.
                </span>
            </div>
        );
    }

    const nombre =
        state.lockedBy?.name ?? fallback.locked_by?.name ?? 'Otro usuario';
    const desde = formatDesde(state.lockedAt ?? fallback.locked_at);

    return (
        <div className="alert alert-warning">
            <LockIcon className="size-5" />
            <div>
                <p className="font-medium">La está editando {nombre}.</p>
                <p className="text-xs opacity-80">
                    {desde ? `Inició ${desde}. ` : ''}
                    Puede ver la tarjeta pero no guardar cambios hasta que
                    termine o su sesión expire.
                </p>
            </div>
        </div>
    );
}
