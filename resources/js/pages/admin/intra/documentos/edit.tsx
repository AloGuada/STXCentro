import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Area, Documento, TipoDocumento } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { ChangeEvent, FormEvent } from 'react';

type Props = {
    documento: Documento;
    areas: Pick<Area, 'id' | 'descripcion'>[];
    tipos: Record<TipoDocumento, string>;
};

export default function DocumentosEdit({ documento, areas, tipos }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Intranet', href: '/admin/intra/secciones' },
        { title: 'Documentos', href: '/admin/intra/documentos' },
        { title: documento.descripcion, href: `/admin/intra/documentos/${documento.id}/edit` },
    ];

    const { data, setData, post, processing, errors } = useForm<{
        _method: string;
        area_id: string;
        descripcion: string;
        codigo: string;
        tipo: string;
        activo: boolean;
        file: File | null;
    }>({
        _method: 'PUT',
        area_id: documento.area_id.toString(),
        descripcion: documento.descripcion,
        codigo: documento.codigo ?? '',
        tipo: documento.tipo,
        activo: documento.activo,
        file: null,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(`/admin/intra/documentos/${documento.id}`);
    };

    const handleFileChange = (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] || null;
        setData('file', file);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${documento.descripcion}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <div className="mb-6 flex items-center justify-between">
                        <h1 className="text-2xl font-semibold">Editar Documento</h1>
                        <DeleteDialog
                            title="Eliminar documento"
                            description={`¿Estás seguro de eliminar el documento "${documento.descripcion}"? Esta acción no se puede deshacer.`}
                            deleteUrl={`/admin/intra/documentos/${documento.id}`}
                        />
                    </div>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Descripción" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                placeholder="Nombre del documento"
                            />
                        </FormField>

                        <FormField label="Código" htmlFor="codigo" error={errors.codigo}>
                            <Input
                                id="codigo"
                                value={data.codigo}
                                onChange={(e) => setData('codigo', e.target.value)}
                                placeholder="Ej: PG-STX-PR-1T-01"
                            />
                        </FormField>

                        <FormField label="Área" htmlFor="area_id" error={errors.area_id} required>
                            <Select
                                id="area_id"
                                value={data.area_id}
                                onValueChange={(value) => setData('area_id', value)}
                            >
                                <option value="">Seleccionar área</option>
                                {areas.map((area) => (
                                    <option key={area.id} value={area.id}>
                                        {area.descripcion}
                                    </option>
                                ))}
                            </Select>
                        </FormField>

                        <FormField
                            label="Tipo de Documento"
                            htmlFor="tipo"
                            error={errors.tipo}
                            required
                            description="El orden de visualización se determina por el tipo seleccionado"
                        >
                            <Select
                                id="tipo"
                                value={data.tipo}
                                onValueChange={(value) => setData('tipo', value)}
                            >
                                <option value="">Seleccionar tipo</option>
                                {Object.entries(tipos).map(([value, label]) => (
                                    <option key={value} value={value}>
                                        {label}
                                    </option>
                                ))}
                            </Select>
                        </FormField>

                        <FormField
                            label="Archivo PDF"
                            htmlFor="file"
                            error={errors.file}
                            description={documento.media ? `Archivo actual: ${documento.media.path.split('/').pop()}` : undefined}
                        >
                            <Input
                                id="file"
                                type="file"
                                accept=".pdf"
                                onChange={handleFileChange}
                                className="file:btn file:btn-sm file:btn-ghost"
                            />
                        </FormField>

                        <FormField label="" htmlFor="activo" error={errors.activo}>
                            <label className="flex items-center gap-2 cursor-pointer">
                                <Checkbox
                                    id="activo"
                                    checked={data.activo}
                                    onCheckedChange={(checked) => setData('activo', !!checked)}
                                />
                                <span>Activo</span>
                            </label>
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/intra/documentos">Cancelar</Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Guardar
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
