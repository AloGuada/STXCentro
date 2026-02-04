import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Area } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Intranet', href: '/admin/intra/secciones' },
    { title: 'Áreas', href: '/admin/intra/areas' },
    { title: 'Nueva Área', href: '/admin/intra/areas/create' },
];

type Props = {
    parentAreas: Pick<Area, 'id' | 'descripcion'>[];
};

export default function AreasCreate({ parentAreas }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        descripcion: '',
        parent_id: '',
        activo: true,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/intra/areas');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva Área" />

            <div className="mx-auto max-w-2xl p-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Nueva Área</CardTitle>
                    </CardHeader>
                    <CardContent>
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
                                    {parentAreas.map((area) => (
                                        <option key={area.id} value={area.id}>
                                            {area.descripcion}
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
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
