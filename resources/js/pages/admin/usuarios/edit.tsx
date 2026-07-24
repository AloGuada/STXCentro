import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, SharedData } from '@/types';
import type { Departamento, Role, Usuario } from '@/types/models';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Loader2Icon, UserCheck, UserX } from 'lucide-react';
import { useState, type FormEvent } from 'react';

type Props = {
    usuario: Usuario;
    roles: Pick<Role, 'id' | 'name'>[];
    departamentos: Pick<Departamento, 'id' | 'descripcion'>[];
};

export default function UsuariosEdit({ usuario, roles, departamentos }: Props) {
    const { auth } = usePage<SharedData>().props;
    const esMiCuenta = String(auth.user?.id) === usuario.id;
    const [cambiandoEstado, setCambiandoEstado] = useState(false);

    const cambiarEstado = () => {
        const accion = usuario.activo ? 'dar de baja a' : 'reactivar a';
        if (!confirm(`¿Seguro que deseas ${accion} "${usuario.name}"?`)) {
            return;
        }
        router.patch(
            `/admin/usuarios/${usuario.id}/estado`,
            {},
            {
                preserveScroll: true,
                onStart: () => setCambiandoEstado(true),
                onFinish: () => setCambiandoEstado(false),
            },
        );
    };
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Usuarios', href: '/admin/usuarios' },
        { title: usuario.name, href: `/admin/usuarios/${usuario.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        empleado: usuario.empleado?.toString() ?? '',
        departamento_id: usuario.departamento_id?.toString() ?? '',
        name: usuario.name,
        email: usuario.email,
        password: '',
        roles: usuario.roles?.map((r) => r.id) ?? [],
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/usuarios/${usuario.id}`);
    };

    const toggleRole = (roleId: number) => {
        setData(
            'roles',
            data.roles.includes(roleId)
                ? data.roles.filter((id) => id !== roleId)
                : [...data.roles, roleId],
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${usuario.name}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <div className="mb-6 flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold">Editar Usuario</h1>
                            {usuario.activo ? (
                                <Badge variant="success">Activo</Badge>
                            ) : (
                                <Badge variant="error">Baja</Badge>
                            )}
                        </div>
                        <DeleteDialog
                            title="Eliminar usuario"
                            description={`¿Estas seguro de eliminar a "${usuario.name}"? Esta accion no se puede deshacer.`}
                            deleteUrl={`/admin/usuarios/${usuario.id}`}
                        />
                    </div>

                    {!esMiCuenta && (
                        <div className="mb-6 flex items-center justify-between rounded-lg border border-border p-4">
                            <div>
                                <p className="font-medium">
                                    {usuario.activo ? 'Dar de baja la cuenta' : 'Reactivar la cuenta'}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {usuario.activo
                                        ? 'El usuario no podrá iniciar sesión y se cerrará su sesión activa.'
                                        : 'El usuario podrá volver a iniciar sesión.'}
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant={usuario.activo ? 'destructive' : 'default'}
                                onClick={cambiarEstado}
                                disabled={cambiandoEstado}
                            >
                                {cambiandoEstado ? (
                                    <Loader2Icon className="size-4 animate-spin" />
                                ) : usuario.activo ? (
                                    <UserX className="size-4" />
                                ) : (
                                    <UserCheck className="size-4" />
                                )}
                                {usuario.activo ? 'Dar de baja' : 'Reactivar'}
                            </Button>
                        </div>
                    )}
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Nombre" htmlFor="name" error={errors.name} required>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Nombre completo"
                            />
                        </FormField>

                        <FormField label="Email" htmlFor="email" error={errors.email} required>
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder="correo@ejemplo.com"
                            />
                        </FormField>

                        <FormField
                            label="Contrasena"
                            htmlFor="password"
                            error={errors.password}
                            description="Dejar vacio para mantener la contrasena actual"
                        >
                            <Input
                                id="password"
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder="Nueva contrasena"
                            />
                        </FormField>

                        <FormField
                            label="ID Empleado"
                            htmlFor="empleado"
                            error={errors.empleado}
                            description="Identificador del empleado en sistema externo (opcional)"
                        >
                            <Input
                                id="empleado"
                                type="number"
                                value={data.empleado}
                                onChange={(e) => setData('empleado', e.target.value)}
                                placeholder="Ej: 12345"
                            />
                        </FormField>

                        <FormField
                            label="Departamento"
                            htmlFor="departamento_id"
                            error={errors.departamento_id}
                            description="Departamento al que pertenece el usuario (opcional)"
                        >
                            <Select
                                id="departamento_id"
                                value={data.departamento_id}
                                onValueChange={(v) => setData('departamento_id', v)}
                                error={!!errors.departamento_id}
                            >
                                <SelectItem value="">Sin departamento</SelectItem>
                                {departamentos.map((d) => (
                                    <SelectItem key={d.id} value={d.id}>
                                        {d.descripcion}
                                    </SelectItem>
                                ))}
                            </Select>
                        </FormField>

                        <FormField
                            label="Roles"
                            htmlFor="roles"
                            error={errors.roles}
                            description="Selecciona los roles del usuario"
                        >
                            <div className="grid grid-cols-2 gap-2">
                                {roles.map((role) => (
                                    <div
                                        key={role.id}
                                        className="flex items-center space-x-2"
                                    >
                                        <Checkbox
                                            id={`role-${role.id}`}
                                            checked={data.roles.includes(role.id)}
                                            onCheckedChange={() => toggleRole(role.id)}
                                        />
                                        <Label
                                            htmlFor={`role-${role.id}`}
                                            className="cursor-pointer"
                                        >
                                            {role.name}
                                        </Label>
                                    </div>
                                ))}
                            </div>
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/usuarios">Cancelar</Link>
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
