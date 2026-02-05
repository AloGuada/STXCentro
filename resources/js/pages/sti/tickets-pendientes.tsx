import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AuthLayout from '@/layouts/auth-layout';
import type { Departamento, PaginatedData, StiStatus, StiTicket, StiTicketHistorial } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { ClockIcon, PlusIcon, TicketIcon } from 'lucide-react';

type Props = {
    tickets: PaginatedData<
        StiTicket & {
            departamento: Departamento;
            historial: (StiTicketHistorial & { status: StiStatus })[];
        }
    >;
};

export default function TicketsPendientes({ tickets }: Props) {
    return (
        <AuthLayout title="Tickets Pendientes" description="Estado de los tickets de soporte">
            <Head title="Tickets Pendientes - Soporte TI" />

            <div className="mx-auto w-full max-w-4xl">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <TicketIcon className="size-5" />
                                    Tickets de Soporte Pendientes
                                </CardTitle>
                                <CardDescription>Lista de tickets en proceso de atencion</CardDescription>
                            </div>
                            <Link href="/sti/ticket" className="btn btn-primary btn-sm">
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
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {tickets.data.map((ticket) => {
                                            const currentStatus = ticket.historial?.[0]?.status;
                                            return (
                                                <tr key={ticket.id}>
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
                                                        <span className="badge badge-info gap-1">
                                                            <ClockIcon className="size-3" />
                                                            {currentStatus?.descripcion ?? 'Pendiente'}
                                                        </span>
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
        </AuthLayout>
    );
}
