import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Departamento, Usuario } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = {
    departamento: Departamento;
    usuarios: Pick<Usuario, 'id' | 'name' | 'email'>[];
};

export default function DepartamentosEdit({ departamento, usuarios }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Departamentos', href: '/admin/departamentos' },
        { title: departamento.descripcion, href: `/admin/departamentos/${departamento.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        descripcion: departamento.descripcion,
        manager: departamento.manager,
        manager_usuario_id: departamento.manager_usuario_id ?? '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/departamentos/${departamento.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${departamento.descripcion}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <div className="mb-6 flex items-center justify-between">
                        <h1 className="text-2xl font-semibold">Editar Departamento</h1>
                        <DeleteDialog
                            title="Eliminar departamento"
                            description={`¿Estas seguro de eliminar "${departamento.descripcion}"? Esta accion no se puede deshacer.`}
                            deleteUrl={`/admin/departamentos/${departamento.id}`}
                        />
                    </div>
                    <form onSubmit={handleSubmit} className="space-y-4">
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
                                placeholder="Nombre del departamento"
                            />
                        </FormField>

                        <FormField
                            label="Manager"
                            htmlFor="manager"
                            error={errors.manager}
                            required
                        >
                            <Input
                                id="manager"
                                value={data.manager}
                                onChange={(e) => setData('manager', e.target.value)}
                                placeholder="Nombre del responsable"
                            />
                        </FormField>

                        <FormField
                            label="Usuario Manager"
                            htmlFor="manager_usuario_id"
                            error={errors.manager_usuario_id}
                            description="Usuario del sistema asociado al manager (opcional)"
                        >
                            <Select
                                value={data.manager_usuario_id}
                                onValueChange={(value) => setData('manager_usuario_id', value)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar usuario" />
                                </SelectTrigger>
                                <SelectContent>
                                    {usuarios.map((usuario) => (
                                        <SelectItem key={usuario.id} value={usuario.id}>
                                            {usuario.name} ({usuario.email})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/departamentos">Cancelar</Link>
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
