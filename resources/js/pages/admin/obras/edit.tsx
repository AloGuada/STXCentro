import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Concepto, Obra } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon, UploadIcon } from 'lucide-react';
import { useState, type FormEvent } from 'react';

type Props = {
    obra: Obra & { conceptos: Concepto[] };
};

export default function ObrasEdit({ obra }: Props) {
    const [activeTab, setActiveTab] = useState<'datos' | 'conceptos'>('datos');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Obras', href: '/admin/obras' },
        { title: obra.no, href: `/admin/obras/${obra.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        no: obra.no,
        descripcion: obra.descripcion,
    });

    const csvForm = useForm<{ csv_file: File | null }>({
        csv_file: null,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/obras/${obra.id}`);
    };

    const handleCsvImport = (e: FormEvent) => {
        e.preventDefault();
        if (!csvForm.data.csv_file) return;

        csvForm.post(`/admin/obras/${obra.id}/import-conceptos`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => csvForm.reset('csv_file'),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${obra.no}`} />

            <div className="space-y-6 p-6">
                <div className="tabs tabs-boxed w-3/4">
                    <button type="button" className={`tab ${activeTab === 'datos' ? 'tab-active' : ''}`} onClick={() => setActiveTab('datos')}>
                        Datos
                    </button>
                    <button type="button" className={`tab ${activeTab === 'conceptos' ? 'tab-active' : ''}`} onClick={() => setActiveTab('conceptos')}>
                        Conceptos ({obra.conceptos?.length ?? 0})
                    </button>
                </div>

                {activeTab === 'datos' && (
                    <div className="w-3/4">
                        <div className="mb-6 flex items-center justify-between">
                            <h1 className="text-2xl font-semibold">Editar Obra</h1>
                            <DeleteDialog
                                title="Eliminar obra"
                                description={`¿Estas seguro de eliminar la obra "${obra.no}"? Esta accion no se puede deshacer.`}
                                deleteUrl={`/admin/obras/${obra.id}`}
                            />
                        </div>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <FormField label="Numero" htmlFor="no" error={errors.no} required>
                                <Input
                                    id="no"
                                    value={data.no}
                                    onChange={(e) => setData('no', e.target.value)}
                                    placeholder="Ej: OBR-001"
                                />
                            </FormField>

                            <FormField
                                label="Descripcion"
                                htmlFor="descripcion"
                                error={errors.descripcion}
                                required
                            >
                                <Input
                                    id="descripcion"
                                    value={data.descripcion}
                                    onChange={(e) => setData('descripcion', e.target.value)}
                                    placeholder="Descripcion de la obra"
                                />
                            </FormField>

                            <div className="flex justify-end gap-2">
                                <Button variant="outline" asChild>
                                    <Link href="/admin/obras">Cancelar</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Loader2Icon className="size-4 animate-spin" />}
                                    Guardar
                                </Button>
                            </div>
                        </form>
                    </div>
                )}

                {activeTab === 'conceptos' && (
                    <div className="space-y-6">
                        <h2 className="text-lg font-semibold">Conceptos de la Obra</h2>

                        {obra.conceptos && obra.conceptos.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="table table-zebra w-full">
                                    <thead>
                                        <tr>
                                            <th>Marca</th>
                                            <th>Descripcion</th>
                                            <th className="text-right">Peso Unit. (kg)</th>
                                            <th className="text-right">Version</th>
                                            <th>Activo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {obra.conceptos.map((concepto) => (
                                            <tr key={concepto.id} className="hover cursor-pointer" onClick={() => window.location.href = `/admin/prod/conceptos/${concepto.id}/edit`}>
                                                <td className="font-medium">{concepto.marca}</td>
                                                <td>{concepto.descripcion}</td>
                                                <td className="text-right font-mono text-sm">{Number(concepto.peso_unitario).toLocaleString('es-MX', { minimumFractionDigits: 3 })}</td>
                                                <td className="text-right font-mono text-sm">{concepto.version}</td>
                                                <td>
                                                    <span className={`badge badge-sm ${concepto.activo ? 'badge-success' : 'badge-ghost'}`}>
                                                        {concepto.activo ? 'Si' : 'No'}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500">No hay conceptos registrados para esta obra.</p>
                        )}

                        <div className="divider" />

                        <h2 className="text-lg font-semibold">Importar Conceptos desde CSV</h2>
                        <p className="text-sm text-gray-500">
                            Formato esperado: PLANO (marca), CONCEPTO (descripcion), KG.UNIT. (peso_unitario), OBSERVACIONES (version).
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
                )}
            </div>
        </AppLayout>
    );
}
