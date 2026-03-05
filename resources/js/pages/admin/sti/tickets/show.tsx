import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { StiTicket } from '@/types/models';
import { Head, Link } from '@inertiajs/react';

type Props = {
    ticket: StiTicket;
};

export default function TicketsShow({ ticket }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'STI', href: '/admin/sti/equipos' },
        { title: 'Tickets', href: '/admin/sti/tickets' },
        { title: `Ticket #${ticket.id}`, href: `/admin/sti/tickets/${ticket.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Ticket #${ticket.id}`} />

            <div className="mx-auto max-w-3xl p-6 space-y-6">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Ticket #{ticket.id}</CardTitle>
                        <Button asChild>
                            <Link href={`/admin/sti/tickets/${ticket.id}/edit`}>Editar</Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <span className="text-sm text-gray-500">Solicitante</span>
                                <p className="font-medium">{ticket.nombre_solicitante}</p>
                            </div>
                            <div>
                                <span className="text-sm text-gray-500">Departamento</span>
                                <p className="font-medium">{ticket.departamento?.descripcion ?? '-'}</p>
                            </div>
                            <div>
                                <span className="text-sm text-gray-500">Técnico</span>
                                <p className="font-medium">{ticket.tecnico?.descripcion ?? 'Sin asignar'}</p>
                            </div>
                            <div>
                                <span className="text-sm text-gray-500">Equipo</span>
                                <p className="font-medium">{ticket.equipo?.descripcion ?? '-'}</p>
                            </div>
                            <div>
                                <span className="text-sm text-gray-500">Fecha de creación</span>
                                <p className="font-medium">
                                    {new Date(ticket.created_at).toLocaleDateString('es-MX', {
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    })}
                                </p>
                            </div>
                        </div>
                        <div>
                            <span className="text-sm text-gray-500">Comentario</span>
                            <p className="mt-1 p-3 bg-gray-50 rounded-lg whitespace-pre-wrap">{ticket.comentario}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Historial de Estados</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {ticket.historial && ticket.historial.length > 0 ? (
                            <div className="relative">
                                <div className="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-200" />
                                <ul className="space-y-4">
                                    {ticket.historial.map((item, index) => (
                                        <li key={item.id} className="relative pl-10">
                                            <div
                                                className="absolute left-2.5 top-1.5 w-3 h-3 rounded-full border-2 border-white"
                                                style={{ backgroundColor: item.status?.color ?? undefined }}
                                            />
                                            <div className="flex items-center gap-2">
                                                <span
                                                    className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium text-white ${index !== 0 ? 'opacity-60' : ''}`}
                                                    style={{ backgroundColor: item.status?.color ?? '#6b7280' }}
                                                >
                                                    {item.status?.descripcion}
                                                </span>
                                                <span className="text-sm text-gray-500">
                                                    {new Date(item.created_at).toLocaleDateString('es-MX', {
                                                        year: 'numeric',
                                                        month: 'short',
                                                        day: 'numeric',
                                                        hour: '2-digit',
                                                        minute: '2-digit',
                                                    })}
                                                </span>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ) : (
                            <p className="text-gray-500">No hay historial de estados</p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
