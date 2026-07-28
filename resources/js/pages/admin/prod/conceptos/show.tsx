import { FormField } from '@/components/form';
import { Button, ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Concepto, Obra } from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { DownloadIcon, PlusIcon, UploadIcon } from 'lucide-react';
import { type FormEvent, useMemo, useState } from 'react';

const fmt = (n: number) => Number(n).toLocaleString('es-MX', { minimumFractionDigits: 3, maximumFractionDigits: 3 });

type Props = {
    obra: Obra;
    conceptos: Concepto[];
    filters: { search?: string };
};

export default function ConceptosShow({ obra, conceptos, filters }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/destajos' },
        { title: 'Conceptos', href: '/admin/prod/conceptos' },
        { title: `${obra.no} - ${obra.descripcion}`, href: `/admin/prod/conceptos/obra/${obra.id}` },
    ];

    const [search, setSearch] = useState(filters.search ?? '');

    const filtered = useMemo(() => {
        if (!search) return conceptos;
        const s = search.toLowerCase();
        return conceptos.filter(
            (c) => c.marca.toLowerCase().includes(s) || c.descripcion.toLowerCase().includes(s),
        );
    }, [conceptos, search]);

    const totals = useMemo(() => ({
        count: filtered.length,
        activos: filtered.filter((c) => c.activo).length,
        pesoTotal: filtered.reduce((sum, c) => sum + Number(c.peso_unitario), 0),
    }), [filtered]);

    const csvForm = useForm<{ csv_file: File | null }>({
        csv_file: null,
    });

    const handleCsvImport = (e: FormEvent) => {
        e.preventDefault();
        if (!csvForm.data.csv_file) return;

        csvForm.post(`/admin/prod/conceptos/obra/${obra.id}/import-csv`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => csvForm.reset('csv_file'),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Conceptos - Obra ${obra.no}`} />

            <div className="p-6">
                <div className="mb-6 flex items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Obra {obra.no}</h1>
                        <p className="mt-1 text-sm text-base-content/60">{obra.descripcion}</p>
                    </div>
                    <ButtonLink href={`/admin/prod/conceptos/create?obra_id=${obra.id}`} variant="primary">
                        <PlusIcon className="size-4" />
                        Nueva pieza
                    </ButtonLink>
                </div>

                <div className="mb-4 w-full max-w-xs">
                    <Input
                        placeholder="Buscar por marca o descripcion..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </div>

                <div className="rounded-box border border-base-300 overflow-hidden">
                    <div className="max-h-[60vh] overflow-auto">
                        <table className="table table-sm">
                            <thead className="sticky top-0 z-10 bg-base-200">
                                <tr>
                                    <th>Marca</th>
                                    <th>Descripcion</th>
                                    <th>Categoria</th>
                                    <th className="text-right">Longitud (mm)</th>
                                    <th className="text-right">Peso Unit. (kg)</th>
                                    <th className="text-center">Version</th>
                                    <th className="text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filtered.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="text-center text-base-content/50 py-6">
                                            No hay conceptos para esta obra
                                        </td>
                                    </tr>
                                ) : (
                                    filtered.map((c) => (
                                        <tr
                                            key={c.id}
                                            className="hover cursor-pointer"
                                            onClick={() => router.visit(`/admin/prod/conceptos/${c.id}/edit`)}
                                        >
                                            <td className="font-medium">{c.marca}</td>
                                            <td>{c.descripcion}</td>
                                            <td>
                                                {c.categoria ? (
                                                    <span className="badge badge-sm badge-ghost">{c.categoria.nombre}</span>
                                                ) : (
                                                    <span className="text-base-content/40">—</span>
                                                )}
                                            </td>
                                            <td className="text-right font-mono">
                                                {c.longitud != null ? c.longitud.toLocaleString('es-MX') : '—'}
                                            </td>
                                            <td className="text-right font-mono">{fmt(c.peso_unitario)}</td>
                                            <td className="text-center font-mono">{c.version}</td>
                                            <td className="text-center">
                                                <span className={`badge badge-sm ${c.activo ? 'badge-success' : 'badge-ghost'}`}>
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
                        <div className="flex items-center justify-between border-t border-base-300 bg-base-200 px-4 py-2 text-sm">
                            <span>{totals.count} conceptos ({totals.activos} activos)</span>
                            <span className="font-mono">Peso total: {fmt(totals.pesoTotal)} kg</span>
                        </div>
                    )}
                </div>

                <div className="mt-8 space-y-3">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="text-lg font-semibold">Importar conceptos desde CSV</h2>
                        <a href="/admin/prod/conceptos/layout" className="btn btn-sm btn-outline">
                            <DownloadIcon className="size-4" />
                            Descargar layout
                        </a>
                    </div>
                    <p className="text-sm text-base-content/60">
                        Columnas: Marca, Descripcion, Categoria, Cantidad, PesoKg, Area, LongitudMm.
                        La categoria se crea automaticamente si no existe. Si la marca ya existe en la obra,
                        se sobrescriben sus datos.
                    </p>

                    <form onSubmit={handleCsvImport} className="flex items-end gap-4">
                        <FormField label="Archivo CSV" htmlFor="csv_file" error={csvForm.errors.csv_file} className="max-w-md">
                            <input
                                id="csv_file"
                                type="file"
                                accept=".csv,.txt"
                                className="file-input file-input-bordered w-full"
                                onChange={(e) => csvForm.setData('csv_file', e.target.files?.[0] ?? null)}
                            />
                        </FormField>
                        <Button type="submit" disabled={csvForm.processing || !csvForm.data.csv_file} loading={csvForm.processing}>
                            {!csvForm.processing && <UploadIcon className="size-4" />}
                            Importar CSV
                        </Button>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
