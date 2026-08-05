import { FormField } from '@/components/form';
import { Button, ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { etiquetaDePieza } from '@/lib/prod/piezas';
import type { Concepto, ProdCatalogo } from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { CopyPlusIcon, DownloadIcon, GitCompareIcon, PlusIcon, UploadIcon } from 'lucide-react';
import { type FormEvent, useMemo, useState } from 'react';

const fmt = (n: number) => Number(n).toLocaleString('es-MX', { minimumFractionDigits: 3, maximumFractionDigits: 3 });

type VersionRow = ProdCatalogo & { conceptos_count: number };

type Props = {
    catalogo: ProdCatalogo;
    conceptos: Concepto[];
    versiones: VersionRow[];
    filters: { search?: string };
};

export default function CatalogoShow({ catalogo, conceptos, versiones, filters }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/destajos' },
        { title: 'Catalogos', href: '/admin/prod/catalogos' },
        { title: `${catalogo.nombre} v${catalogo.version}`, href: `/admin/prod/catalogos/${catalogo.id}` },
    ];

    const [search, setSearch] = useState(filters.search ?? '');

    const filtered = useMemo(() => {
        if (!search) return conceptos;
        const s = search.toLowerCase();
        return conceptos.filter((c) =>
            [c.qs, c.marca, c.etapa, c.descripcion].some((campo) => (campo ?? '').toLowerCase().includes(s)),
        );
    }, [conceptos, search]);

    const totals = useMemo(
        () => ({
            count: filtered.length,
            activos: filtered.filter((c) => c.activo).length,
            piezas: filtered.reduce((sum, c) => sum + Number(c.cantidad), 0),
            pagadas: filtered.reduce((sum, c) => sum + Number(c.capturado ?? 0), 0),
            faltan: filtered.reduce((sum, c) => sum + Number(c.disponible ?? 0), 0),
            pesoTotal: filtered.reduce((sum, c) => sum + Number(c.cantidad) * Number(c.peso_unitario), 0),
        }),
        [filtered],
    );

    const csvForm = useForm<{ csv_file: File | null }>({ csv_file: null });

    const handleCsvImport = (e: FormEvent) => {
        e.preventDefault();
        if (!csvForm.data.csv_file) return;

        csvForm.post(`/admin/prod/catalogos/${catalogo.id}/import-csv`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => csvForm.reset('csv_file'),
        });
    };

    const nuevaVersion = () => {
        if (
            confirm(
                `¿Crear la versión ${catalogo.version + 1}? Se copian las ${conceptos.length} piezas y sus precios; ` +
                    `la v${catalogo.version} queda congelada como histórico.`,
            )
        ) {
            router.post(`/admin/prod/catalogos/${catalogo.id}/nueva-version`);
        }
    };

    const versionAnterior = versiones.find((v) => v.version === catalogo.version - 1);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${catalogo.nombre} v${catalogo.version}`} />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-semibold">
                            {catalogo.nombre}
                            <span className="badge badge-ghost font-mono">v{catalogo.version}</span>
                            <span className={`badge ${catalogo.vigente ? 'badge-success' : 'badge-neutral'}`}>
                                {catalogo.vigente ? 'Vigente' : 'Histórico'}
                            </span>
                        </h1>
                        <p className="text-base-content/60 mt-1 text-sm">
                            {catalogo.obra ? `Obra ${catalogo.obra.no} — ${catalogo.obra.descripcion}` : 'Sin obra'}
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {versionAnterior && (
                            <ButtonLink
                                href={`/admin/prod/catalogos/${versionAnterior.id}/comparar/${catalogo.id}`}
                                variant="outline"
                            >
                                <GitCompareIcon className="size-4" />
                                Comparar con v{versionAnterior.version}
                            </ButtonLink>
                        )}
                        {catalogo.vigente && (
                            <>
                                <Button variant="outline" onClick={nuevaVersion}>
                                    <CopyPlusIcon className="size-4" />
                                    Nueva versión
                                </Button>
                                <ButtonLink
                                    href={`/admin/prod/conceptos/create?catalogo_id=${catalogo.id}`}
                                    variant="primary"
                                >
                                    <PlusIcon className="size-4" />
                                    Nueva pieza
                                </ButtonLink>
                            </>
                        )}
                    </div>
                </div>

                {!catalogo.vigente && (
                    <div className="alert alert-info mb-4">
                        <span>
                            Esta es una versión histórica: se conserva para consulta y para sostener la producción que
                            se capturó con ella, pero ya no se edita.
                        </span>
                    </div>
                )}

                {versiones.length > 1 && (
                    <div className="mb-4 flex flex-wrap items-center gap-2">
                        <span className="text-base-content/60 text-sm">Versiones:</span>
                        {versiones.map((v) => (
                            <Link
                                key={v.id}
                                href={`/admin/prod/catalogos/${v.id}`}
                                className={`badge gap-1 ${v.id === catalogo.id ? 'badge-primary' : 'badge-ghost'}`}
                            >
                                v{v.version}
                                <span className="opacity-60">({v.conceptos_count})</span>
                            </Link>
                        ))}
                    </div>
                )}

                <div className="mb-4 w-full max-w-xs">
                    <Input
                        placeholder="Buscar por QS, marca, etapa o descripcion..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </div>

                <div className="rounded-box border-base-300 overflow-hidden border">
                    <div className="max-h-[60vh] overflow-auto">
                        <table className="table table-sm">
                            <thead className="bg-base-200 sticky top-0 z-10">
                                <tr>
                                    <th>QS</th>
                                    <th>Marca</th>
                                    <th>Etapa</th>
                                    <th>Descripcion</th>
                                    <th>Categoria</th>
                                    <th className="text-right">Cantidad</th>
                                    <th className="text-right">Pagadas</th>
                                    <th className="text-right">Faltan</th>
                                    <th className="text-right">Longitud (mm)</th>
                                    <th className="text-right">Peso Unit. (kg)</th>
                                    <th className="text-right">Peso Total (kg)</th>
                                    <th className="text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filtered.length === 0 ? (
                                    <tr>
                                        <td colSpan={12} className="text-base-content/50 py-6 text-center">
                                            Este catálogo no tiene piezas todavía
                                        </td>
                                    </tr>
                                ) : (
                                    filtered.map((c) => (
                                        <tr
                                            key={c.id}
                                            className={`hover ${catalogo.vigente ? 'cursor-pointer' : ''}`}
                                            onClick={() =>
                                                catalogo.vigente && router.visit(`/admin/prod/conceptos/${c.id}/edit`)
                                            }
                                        >
                                            <td className="text-base-content/60 font-mono text-xs">
                                                {c.qs ?? '—'}
                                            </td>
                                            <td className="font-medium">{c.marca}</td>
                                            <td>
                                                {c.etapa ? (
                                                    <span className="badge badge-sm badge-ghost">{c.etapa}</span>
                                                ) : (
                                                    <span className="text-base-content/40">—</span>
                                                )}
                                            </td>
                                            <td>{c.descripcion}</td>
                                            <td>
                                                {c.categoria ? (
                                                    <span className="badge badge-sm badge-ghost">
                                                        {c.categoria.nombre}
                                                    </span>
                                                ) : (
                                                    <span className="text-base-content/40">—</span>
                                                )}
                                            </td>
                                            <td className="text-right font-mono">
                                                {Number(c.cantidad).toLocaleString('es-MX')}
                                            </td>
                                            <td className="text-right font-mono">
                                                {Number(c.capturado ?? 0).toLocaleString('es-MX')}
                                            </td>
                                            <td className="text-right font-mono">
                                                {(c.disponible ?? 0) === 0 ? (
                                                    <span className="badge badge-sm badge-success">Completa</span>
                                                ) : (
                                                    Number(c.disponible).toLocaleString('es-MX')
                                                )}
                                            </td>
                                            <td className="text-right font-mono">
                                                {c.longitud != null ? c.longitud.toLocaleString('es-MX') : '—'}
                                            </td>
                                            <td className="text-right font-mono">{fmt(c.peso_unitario)}</td>
                                            <td className="text-right font-mono">
                                                {fmt(Number(c.cantidad) * Number(c.peso_unitario))}
                                            </td>
                                            <td className="text-center">
                                                <span
                                                    className={`badge badge-sm ${c.activo ? 'badge-success' : 'badge-ghost'}`}
                                                >
                                                    {c.activo ? 'Activo' : 'Inactivo'}
                                                </span>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {filtered.length > 0 && (
                        <div className="border-base-300 bg-base-200 flex items-center justify-between border-t px-4 py-2 text-sm">
                            <span>
                                {totals.count} modelos ({totals.activos} activos) ·{' '}
                                <span className="font-mono">{totals.piezas.toLocaleString('es-MX')}</span> piezas ·{' '}
                                <span className="font-mono">{totals.pagadas.toLocaleString('es-MX')}</span> pagadas ·{' '}
                                <span className="font-mono">{totals.faltan.toLocaleString('es-MX')}</span> por pagar
                            </span>
                            <span className="font-mono">Peso total: {fmt(totals.pesoTotal)} kg</span>
                        </div>
                    )}
                </div>

                {catalogo.vigente && (
                    <div className="mt-8 space-y-3">
                        <div className="flex items-center justify-between gap-3">
                            <h2 className="text-lg font-semibold">Importar piezas desde CSV</h2>
                            <a href="/admin/prod/conceptos/layout" className="btn btn-sm btn-outline">
                                <DownloadIcon className="size-4" />
                                Descargar layout
                            </a>
                        </div>
                        <p className="text-base-content/60 text-sm">
                            Columnas: QS, Marca, Etapa, Descripcion, Categoria, Cantidad, PesoKg, Area, LongitudMm. La
                            categoria se crea automaticamente si no existe. La pieza se identifica por{' '}
                            <strong>marca + etapa</strong>: si esa combinación ya existe en esta versión del catálogo se
                            sobrescriben sus datos, y si cambia la etapa entra como pieza nueva. QS es el id de planta,
                            se guarda como referencia pero no decide el emparejado.
                        </p>

                        <form onSubmit={handleCsvImport} className="flex items-end gap-4">
                            <FormField
                                label="Archivo CSV"
                                htmlFor="csv_file"
                                error={csvForm.errors.csv_file}
                                className="max-w-md"
                            >
                                <input
                                    id="csv_file"
                                    type="file"
                                    accept=".csv,.txt"
                                    className="file-input file-input-bordered w-full"
                                    onChange={(e) => csvForm.setData('csv_file', e.target.files?.[0] ?? null)}
                                />
                            </FormField>
                            <Button
                                type="submit"
                                disabled={csvForm.processing || !csvForm.data.csv_file}
                                loading={csvForm.processing}
                            >
                                {!csvForm.processing && <UploadIcon className="size-4" />}
                                Importar CSV
                            </Button>
                        </form>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
