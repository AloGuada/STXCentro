import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Cliente, CobContacto } from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Loader2Icon, PencilIcon, PlusIcon, SaveIcon, TrashIcon, XIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';

type Props = {
    cliente: Cliente;
};

export default function ClientesEdit({ cliente }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cobranza', href: '/admin/cob/dashboard' },
        { title: 'Clientes', href: '/admin/cob/clientes' },
        { title: cliente.nombre, href: `/admin/cob/clientes/${cliente.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        nombre: cliente.nombre,
        rfc: cliente.rfc ?? '',
        direccion: cliente.direccion ?? '',
        telefono: cliente.telefono ?? '',
        email: cliente.email ?? '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/cob/clientes/${cliente.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${cliente.nombre}`} />

            <div className="p-6">
                <div className="grid gap-8 lg:grid-cols-2">
                    {/* Formulario de cliente */}
                    <div>
                        <div className="mb-6 flex items-center justify-between">
                            <h1 className="text-2xl font-semibold">Editar Cliente</h1>
                            <DeleteDialog
                                title="Eliminar cliente"
                                description={`¿Estas seguro de eliminar "${cliente.nombre}"? Esta accion no se puede deshacer.`}
                                deleteUrl={`/admin/cob/clientes/${cliente.id}`}
                            />
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            <FormField label="Nombre" htmlFor="nombre" error={errors.nombre} required>
                                <Input
                                    id="nombre"
                                    value={data.nombre}
                                    onChange={(e) => setData('nombre', e.target.value)}
                                    placeholder="Nombre del cliente"
                                />
                            </FormField>

                            <FormField label="RFC" htmlFor="rfc" error={errors.rfc}>
                                <Input
                                    id="rfc"
                                    value={data.rfc}
                                    onChange={(e) => setData('rfc', e.target.value)}
                                    placeholder="RFC del cliente"
                                />
                            </FormField>

                            <FormField label="Direccion" htmlFor="direccion" error={errors.direccion}>
                                <Input
                                    id="direccion"
                                    value={data.direccion}
                                    onChange={(e) => setData('direccion', e.target.value)}
                                    placeholder="Direccion del cliente"
                                />
                            </FormField>

                            <FormField label="Telefono" htmlFor="telefono" error={errors.telefono}>
                                <Input
                                    id="telefono"
                                    value={data.telefono}
                                    onChange={(e) => setData('telefono', e.target.value)}
                                    placeholder="Telefono de contacto"
                                />
                            </FormField>

                            <FormField label="Email" htmlFor="email" error={errors.email}>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder="Correo electronico"
                                />
                            </FormField>

                            <div className="flex justify-end gap-2">
                                <Button variant="outline" asChild>
                                    <Link href="/admin/cob/clientes">Cancelar</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Loader2Icon className="size-4 animate-spin" />}
                                    Guardar
                                </Button>
                            </div>
                        </form>
                    </div>

                    {/* Contactos */}
                    <div>
                        <h2 className="mb-4 text-lg font-medium">Contactos</h2>
                        <ContactosManager clienteId={cliente.id} contactos={cliente.contactos ?? []} />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

type ContactosManagerProps = {
    clienteId: number;
    contactos: CobContacto[];
};

function ContactosManager({ clienteId, contactos }: ContactosManagerProps) {
    const [showForm, setShowForm] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editingData, setEditingData] = useState({ nombre: '', email: '', telefono: '', cargo: '' });
    const [newData, setNewData] = useState({ nombre: '', email: '', telefono: '', cargo: '' });
    const [processing, setProcessing] = useState(false);

    const handleAdd = () => {
        if (!newData.nombre.trim()) return;
        setProcessing(true);
        router.post(
            `/admin/cob/clientes/${clienteId}/contactos`,
            newData,
            {
                preserveScroll: true,
                onSuccess: () => {
                    setNewData({ nombre: '', email: '', telefono: '', cargo: '' });
                    setShowForm(false);
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    const handleUpdate = (contactoId: number) => {
        if (!editingData.nombre.trim()) return;
        setProcessing(true);
        router.put(
            `/admin/cob/clientes/${clienteId}/contactos/${contactoId}`,
            editingData,
            {
                preserveScroll: true,
                onSuccess: () => {
                    setEditingId(null);
                    setEditingData({ nombre: '', email: '', telefono: '', cargo: '' });
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    const handleDelete = (contactoId: number) => {
        if (!confirm('¿Eliminar este contacto?')) return;
        setProcessing(true);
        router.delete(`/admin/cob/clientes/${clienteId}/contactos/${contactoId}`, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    };

    const startEditing = (contacto: CobContacto) => {
        setEditingId(contacto.id);
        setEditingData({
            nombre: contacto.nombre,
            email: contacto.email ?? '',
            telefono: contacto.telefono ?? '',
            cargo: contacto.cargo ?? '',
        });
    };

    const cancelEditing = () => {
        setEditingId(null);
        setEditingData({ nombre: '', email: '', telefono: '', cargo: '' });
    };

    return (
        <div className="space-y-4">
            <div className="flex justify-end">
                <Button size="sm" onClick={() => setShowForm(!showForm)} disabled={processing}>
                    <PlusIcon className="size-4" />
                    Nuevo Contacto
                </Button>
            </div>

            <div className="overflow-x-auto rounded-box border border-base-300">
                <table className="table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Telefono</th>
                            <th>Cargo</th>
                            <th className="w-24">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {showForm && (
                            <tr>
                                <td>
                                    <Input
                                        value={newData.nombre}
                                        onChange={(e) => setNewData({ ...newData, nombre: e.target.value })}
                                        placeholder="Nombre"
                                        className="input-sm"
                                    />
                                </td>
                                <td>
                                    <Input
                                        value={newData.email}
                                        onChange={(e) => setNewData({ ...newData, email: e.target.value })}
                                        placeholder="Email"
                                        className="input-sm"
                                    />
                                </td>
                                <td>
                                    <Input
                                        value={newData.telefono}
                                        onChange={(e) => setNewData({ ...newData, telefono: e.target.value })}
                                        placeholder="Telefono"
                                        className="input-sm"
                                    />
                                </td>
                                <td>
                                    <Input
                                        value={newData.cargo}
                                        onChange={(e) => setNewData({ ...newData, cargo: e.target.value })}
                                        placeholder="Cargo"
                                        className="input-sm"
                                    />
                                </td>
                                <td>
                                    <div className="flex gap-1">
                                        <Button variant="ghost" size="icon" onClick={handleAdd} disabled={processing || !newData.nombre.trim()}>
                                            <SaveIcon className="size-4 text-green-600" />
                                        </Button>
                                        <Button variant="ghost" size="icon" onClick={() => setShowForm(false)}>
                                            <XIcon className="size-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        )}

                        {contactos.length === 0 && !showForm ? (
                            <tr>
                                <td colSpan={5} className="text-base-content/60 py-8 text-center">
                                    No hay contactos registrados
                                </td>
                            </tr>
                        ) : (
                            contactos.map((contacto) => (
                                <tr key={contacto.id}>
                                    {editingId === contacto.id ? (
                                        <>
                                            <td>
                                                <Input
                                                    value={editingData.nombre}
                                                    onChange={(e) => setEditingData({ ...editingData, nombre: e.target.value })}
                                                    className="input-sm"
                                                />
                                            </td>
                                            <td>
                                                <Input
                                                    value={editingData.email}
                                                    onChange={(e) => setEditingData({ ...editingData, email: e.target.value })}
                                                    className="input-sm"
                                                />
                                            </td>
                                            <td>
                                                <Input
                                                    value={editingData.telefono}
                                                    onChange={(e) => setEditingData({ ...editingData, telefono: e.target.value })}
                                                    className="input-sm"
                                                />
                                            </td>
                                            <td>
                                                <Input
                                                    value={editingData.cargo}
                                                    onChange={(e) => setEditingData({ ...editingData, cargo: e.target.value })}
                                                    className="input-sm"
                                                />
                                            </td>
                                            <td>
                                                <div className="flex gap-1">
                                                    <Button variant="ghost" size="icon" onClick={() => handleUpdate(contacto.id)} disabled={processing}>
                                                        <SaveIcon className="size-4 text-green-600" />
                                                    </Button>
                                                    <Button variant="ghost" size="icon" onClick={cancelEditing}>
                                                        <XIcon className="size-4" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </>
                                    ) : (
                                        <>
                                            <td>{contacto.nombre}</td>
                                            <td>{contacto.email ?? '-'}</td>
                                            <td>{contacto.telefono ?? '-'}</td>
                                            <td>{contacto.cargo ?? '-'}</td>
                                            <td>
                                                <div className="flex gap-1">
                                                    <Button variant="ghost" size="icon" onClick={() => startEditing(contacto)} disabled={processing}>
                                                        <PencilIcon className="size-4 text-gray-500" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() => handleDelete(contacto.id)}
                                                        disabled={processing}
                                                        className="text-red-500 hover:bg-red-50 hover:text-red-600"
                                                    >
                                                        <TrashIcon className="size-4" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </>
                                    )}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
