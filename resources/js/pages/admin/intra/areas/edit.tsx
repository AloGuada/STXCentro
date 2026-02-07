import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Area } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = {
    area: Area;
    parentAreas: Pick<Area, 'id' | 'descripcion'>[];
};

export default function AreasEdit({ area, parentAreas }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Intranet', href: '/admin/intra/secciones' },
        { title: 'Áreas', href: '/admin/intra/areas' },
        { title: area.descripcion, href: `/admin/intra/areas/${area.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        descripcion: area.descripcion,
        parent_id: area.parent_id?.toString() ?? '',
        activo: area.activo,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/intra/areas/${area.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${area.descripcion}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <div className="mb-6 flex items-center justify-between">
                        <h1 className="text-2xl font-semibold">Editar Área</h1>
                        <DeleteDialog
                            title="Eliminar área"
                            description={`¿Estás seguro de eliminar el área "${area.descripcion}"? Esta acción no se puede deshacer.`}
                            deleteUrl={`/admin/intra/areas/${area.id}`}
                        />
                    </div>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Descripción" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                placeholder="Nombre del área"
                            />
                        </FormField>

                        <FormField label="Área Padre" htmlFor="parent_id" error={errors.parent_id}>
                            <Select
                                id="parent_id"
                                value={data.parent_id}
                                onValueChange={(value) => setData('parent_id', value)}
                            >
                                <option value="">Sin área padre (raíz)</option>
                                {parentAreas.map((parentArea) => (
                                    <option key={parentArea.id} value={parentArea.id}>
                                        {parentArea.descripcion}
                                    </option>
                                ))}
                            </Select>
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
                                <Link href="/admin/intra/areas">Cancelar</Link>
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
