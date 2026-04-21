import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Departamento } from '@/types/models';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2Icon, Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tickets de Soporte', href: '/sti/tickets' },
    { title: 'Nuevo', href: '/sti/ticket/nuevo' },
];

type Props = {
    departamentos: Pick<Departamento, 'id' | 'descripcion'>[];
};

export default function TicketNuevo({ departamentos }: Props) {
    const { flash } = usePage<{ flash: { success?: string } }>().props;

    const { data, setData, post, processing, errors, reset } = useForm({
        nombre_solicitante: '',
        departamento_id: '',
        comentario: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/sti/ticket', {
            onSuccess: () => reset(),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Ticket - Soporte TI" />

            <div className="mx-auto w-full max-w-md p-6 md:p-10">
                <div className="mb-4 text-center">
                    <Link href="/sti/tickets" className="text-sm text-primary hover:underline">
                        ← Ver tickets pendientes
                    </Link>
                </div>

                {flash?.success ? (
                    <Card className="border-success">
                        <CardContent className="pt-6">
                            <div className="flex flex-col items-center text-center space-y-4">
                                <CheckCircle2Icon className="size-16 text-success" />
                                <h2 className="text-xl font-semibold">Ticket Enviado</h2>
                                <p className="text-gray-600">{flash.success}</p>
                                <Button onClick={() => window.location.reload()} variant="outline">
                                    Enviar otro ticket
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle>Nuevo Ticket de Soporte</CardTitle>
                            <CardDescription>
                                Completa el formulario para reportar un problema o solicitar ayuda técnica.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <FormField label="Tu Nombre" htmlFor="nombre_solicitante" error={errors.nombre_solicitante} required>
                                    <Input
                                        id="nombre_solicitante"
                                        value={data.nombre_solicitante}
                                        onChange={(e) => setData('nombre_solicitante', e.target.value)}
                                        placeholder="Tu nombre completo"
                                    />
                                </FormField>

                                <FormField label="Departamento" htmlFor="departamento_id" error={errors.departamento_id} required>
                                    <Select
                                        id="departamento_id"
                                        value={data.departamento_id}
                                        onValueChange={(value) => setData('departamento_id', value)}
                                    >
                                        <option value="">Selecciona tu departamento</option>
                                        {departamentos.map((depto) => (
                                            <option key={depto.id} value={depto.id}>
                                                {depto.descripcion}
                                            </option>
                                        ))}
                                    </Select>
                                </FormField>

                                <FormField label="Describe tu problema o solicitud" htmlFor="comentario" error={errors.comentario} required>
                                    <textarea
                                        id="comentario"
                                        className="textarea textarea-bordered w-full min-h-32"
                                        value={data.comentario}
                                        onChange={(e) => setData('comentario', e.target.value)}
                                        placeholder="Describe detalladamente el problema que tienes o la ayuda que necesitas..."
                                    />
                                </FormField>

                                <Button type="submit" className="w-full" disabled={processing}>
                                    {processing && <Loader2Icon className="size-4 animate-spin" />}
                                    Enviar Ticket
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
