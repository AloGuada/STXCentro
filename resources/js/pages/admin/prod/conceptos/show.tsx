import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Concepto, Obra } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon, PlusIcon, UploadIcon } from 'lucide-react';
import { type FormEvent, useMemo, useState } from 'react';

const fmt = (n: number) => Number(n).toLocaleString('es-MX', { minimumFractionDigits: 3 });

type Props = {
    obra: Obra;
    conceptos: Concepto[];
    filters: { search?: string };
};

export default function ConceptosShow({ obra, conceptos, filters }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/cortes' },
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
                <div className="mb-4 flex items-center justify-between">
                    <div className="w-72">
                        <Input
                            placeholder="Buscar por marca o descripcion..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>
                    <Button asChild>
                        <Link href={`/admin/prod/conceptos/create?obra_id=${obra.id}`}>
                            <PlusIcon className="mr-1 size-4" />
                            Nuevo Concepto
                        </Link>
                    </Button>
                </div>

                <div className="overflow-hidden rounded-lg border border-base-300">
                    <div className="max-h-[60vh] overflow-auto">
                        <table className="table table-sm w-full">
                            <thead className="sticky top-0 z-10 bg-base-200">
                                <tr>
                                    <th>Marca</th>
                                    <th>Descripcion</th>
                                    <th className="text-right">Peso Unit. (kg)</th>
                                    <th className="text-center">Version</th>
                                    <th className="text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filtered.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="py-8 text-center text-gray-500">
                                            No hay conceptos para esta obra
                                        </td>
                                    </tr>
                                ) : (
                                    filtered.map((c) => (
                                        <tr key={c.id} className="hover cursor-pointer" onClick={() => window.location.href = `/admin/prod/conceptos/${c.id}/edit`}>
                                            <td className="font-medium">{c.marca}</td>
                                            <td>{c.descripcion}</td>
                                            <td className="text-right font-mono text-sm">{fmt(c.peso_unitario)}</td>
                                            <td className="text-center font-mono text-sm">{c.version}</td>
                                            <td className="text-center">
                                                <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${c.activo ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'}`}>
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
                    <h2 className="text-lg font-semibold">Importar Conceptos desde CSV</h2>
                    <p className="text-sm text-gray-500">
                        Columnas: Marca, Descripcion, Peso(T) (se convierte a kg), Revision de documentos (ej. REV 2).
                        Si la marca ya existe, solo se actualiza si la version importada es mayor.
                    </p>

                    <form onSubmit={handleCsvImport} className="flex items-end gap-4">
                        <FormField label="Archivo CSV" htmlFor="csv_file" error={csvForm.errors.csv_file}>
                            <input
                                id="csv_file"
                                type="file"
                                accept=".csv,.txt"
                                className="file-input file-input-bordered w-full"
                                onChange={(e) => csvForm.setData('csv_file', e.target.files?.[0] ?? null)}
                            />
                        </FormField>
                        <Button type="submit" disabled={csvForm.processing || !csvForm.data.csv_file}>
                            {csvForm.processing ? <Loader2Icon className="size-4 animate-spin" /> : <UploadIcon className="size-4" />}
                            Importar CSV
                        </Button>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
