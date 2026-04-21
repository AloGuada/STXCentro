import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Departamento, PaginatedData, StiStatus, StiTicket, StiTicketComentario, StiTicketHistorial } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { ClockIcon, PlusIcon, TicketIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tickets de Soporte', href: '/sti/tickets' },
];

type TicketWithRelations = StiTicket & {
    departamento: Departamento;
    historial: (StiTicketHistorial & { status: StiStatus })[];
    comentarios: StiTicketComentario[];
};

type Props = {
    tickets: PaginatedData<TicketWithRelations>;
};

export default function TicketsPendientes({ tickets }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tickets Pendientes - Soporte TI" />

            <div className="mx-auto w-full max-w-5xl p-6 md:p-10">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <TicketIcon className="size-5" />
                                    Tickets de Soporte Pendientes
                                </CardTitle>
                                <CardDescription>Lista de tickets en proceso de atencion. Haz clic en un ticket para ver su historial.</CardDescription>
                            </div>
                            <Link href="/sti/ticket/nuevo" className="btn btn-primary btn-sm">
                                <PlusIcon className="size-4" />
                                Nuevo Ticket
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {tickets.data.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="table w-full">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Fecha</th>
                                            <th>Solicitante</th>
                                            <th>Departamento</th>
                                            <th>Estado</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {tickets.data.map((ticket) => {
                                            const currentStatus = ticket.historial?.[0]?.status;
                                            return (
                                                <tr key={ticket.id} className="hover:bg-base-200">
                                                    <td className="font-mono">#{ticket.id}</td>
                                                    <td>
                                                        {new Date(ticket.created_at).toLocaleDateString('es-MX', {
                                                            day: 'numeric',
                                                            month: 'short',
                                                            year: 'numeric',
                                                        })}
                                                    </td>
                                                    <td>{ticket.nombre_solicitante}</td>
                                                    <td>{ticket.departamento?.descripcion}</td>
                                                    <td>
                                                        <span
                                                            className="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium text-white"
                                                            style={{ backgroundColor: currentStatus?.color ?? '#6b7280' }}
                                                        >
                                                            <ClockIcon className="size-3" />
                                                            {currentStatus?.descripcion ?? 'Pendiente'}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <Link href={`/sti/ticket/${ticket.id}`} className="btn btn-ghost btn-xs">
                                                            Ver
                                                        </Link>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <div className="py-8 text-center text-gray-500">
                                <TicketIcon className="mx-auto mb-4 size-12 text-gray-300" />
                                <p>No hay tickets pendientes en este momento.</p>
                            </div>
                        )}

                        {/* Paginacion */}
                        {tickets.last_page > 1 && (
                            <div className="mt-4 flex justify-center gap-2">
                                {tickets.links.map((link, index) => (
                                    <Link
                                        key={index}
                                        href={link.url ?? '#'}
                                        className={`btn btn-sm ${link.active ? 'btn-primary' : 'btn-outline'} ${!link.url ? 'btn-disabled' : ''}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
