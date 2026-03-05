import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';
import type { Departamento, StiStatus, StiTicket, StiTicketComentario, StiTicketHistorial } from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeftIcon, CheckCircleIcon, ClockIcon, Loader2Icon, MessageSquareIcon, SendIcon, UserIcon, WrenchIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type TicketWithRelations = StiTicket & {
    departamento: Departamento;
    historial: (StiTicketHistorial & { status: StiStatus })[];
    comentarios: StiTicketComentario[];
};

type Props = {
    ticket: TicketWithRelations;
};

export default function TicketShow({ ticket }: Props) {
    const [activeTab, setActiveTab] = useState<'chat' | 'historial'>('chat');
    const chatContainerRef = useRef<HTMLDivElement>(null);

    const currentStatus = ticket.historial?.[0]?.status;
    const isCompleted = currentStatus && currentStatus.orden >= 8;

    const { data, setData, post, processing, reset } = useForm({
        comentario: '',
        autor: ticket.nombre_solicitante,
    });

    useEffect(() => {
        if (chatContainerRef.current && activeTab === 'chat') {
            chatContainerRef.current.scrollTop = chatContainerRef.current.scrollHeight;
        }
    }, [ticket.comentarios, activeTab]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!data.comentario.trim()) return;

        post(`/sti/ticket/${ticket.id}/comentario`, {
            preserveScroll: true,
            onSuccess: () => reset('comentario'),
        });
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSubmit(e);
        }
    };

    return (
        <AuthLayout title={`Ticket #${ticket.id}`} description="Detalle del ticket de soporte">
            <Head title={`Ticket #${ticket.id} - Soporte TI`} />

            <div className="mx-auto w-full max-w-3xl">
                {/* Header */}
                <div className="mb-4 flex items-center gap-4">
                    <Link href="/sti/tickets" className="btn btn-ghost btn-sm">
                        <ArrowLeftIcon className="size-4" />
                        Volver
                    </Link>
                </div>

                {/* Ticket Info */}
                <div className="card bg-base-100 shadow">
                    <div className="card-body">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 className="card-title">Ticket #{ticket.id}</h2>
                                <p className="text-sm text-gray-500">
                                    Creado el{' '}
                                    {new Date(ticket.created_at).toLocaleDateString('es-MX', {
                                        day: 'numeric',
                                        month: 'long',
                                        year: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    })}
                                </p>
                            </div>
                            <span
                                className="badge gap-1 text-white"
                                style={currentStatus?.color ? { backgroundColor: currentStatus.color } : undefined}
                            >
                                {isCompleted ? <CheckCircleIcon className="size-3" /> : <ClockIcon className="size-3" />}
                                {currentStatus?.descripcion ?? 'Pendiente'}
                            </span>
                        </div>

                        <div className="divider my-2" />

                        <div className="grid gap-4 text-sm md:grid-cols-2">
                            <div>
                                <span className="font-medium text-gray-500">Solicitante:</span>
                                <p>{ticket.nombre_solicitante}</p>
                            </div>
                            <div>
                                <span className="font-medium text-gray-500">Departamento:</span>
                                <p>{ticket.departamento?.descripcion}</p>
                            </div>
                        </div>

                        <div className="mt-4">
                            <span className="font-medium text-gray-500">Descripcion del problema:</span>
                            <p className="mt-1 whitespace-pre-wrap rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                                {ticket.comentario}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Tabs */}
                <div className="tabs tabs-boxed mt-6">
                    <button
                        type="button"
                        onClick={() => setActiveTab('chat')}
                        className={`tab gap-2 ${activeTab === 'chat' ? 'tab-active' : ''}`}
                    >
                        <MessageSquareIcon className="size-4" />
                        Mensajes
                        {ticket.comentarios.length > 0 && (
                            <span className="badge badge-sm badge-primary">{ticket.comentarios.length}</span>
                        )}
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('historial')}
                        className={`tab gap-2 ${activeTab === 'historial' ? 'tab-active' : ''}`}
                    >
                        <ClockIcon className="size-4" />
                        Historial
                    </button>
                </div>

                {/* Tab Content */}
                <div className="card mt-4 bg-base-100 shadow">
                    <div className="card-body p-0">
                        {activeTab === 'chat' ? (
                            <div className="flex h-96 flex-col">
                                {/* Chat messages */}
                                <div ref={chatContainerRef} className="flex-1 space-y-3 overflow-y-auto p-4">
                                    {ticket.comentarios.length === 0 ? (
                                        <p className="py-8 text-center text-sm text-gray-400">
                                            No hay mensajes aun. Escribe un mensaje para comunicarte con el tecnico.
                                        </p>
                                    ) : (
                                        ticket.comentarios.map((comentario) => (
                                            <div
                                                key={comentario.id}
                                                className={`flex ${comentario.tipo === 'tecnico' ? 'justify-start' : 'justify-end'}`}
                                            >
                                                <div
                                                    className={`max-w-[75%] rounded-lg px-4 py-2 ${
                                                        comentario.tipo === 'tecnico'
                                                            ? 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-gray-100'
                                                            : 'bg-primary text-primary-content'
                                                    }`}
                                                >
                                                    <div className="mb-1 flex items-center gap-1">
                                                        {comentario.tipo === 'tecnico' ? (
                                                            <WrenchIcon className="size-3" />
                                                        ) : (
                                                            <UserIcon className="size-3" />
                                                        )}
                                                        <span className="text-xs font-medium">{comentario.autor}</span>
                                                    </div>
                                                    <p className="whitespace-pre-wrap text-sm">{comentario.comentario}</p>
                                                    <p
                                                        className={`mt-1 text-xs ${
                                                            comentario.tipo === 'tecnico'
                                                                ? 'text-gray-500 dark:text-gray-400'
                                                                : 'text-primary-content/70'
                                                        }`}
                                                    >
                                                        {new Date(comentario.created_at).toLocaleString('es-MX', {
                                                            day: 'numeric',
                                                            month: 'short',
                                                            hour: '2-digit',
                                                            minute: '2-digit',
                                                        })}
                                                    </p>
                                                </div>
                                            </div>
                                        ))
                                    )}
                                </div>

                                {/* Input area */}
                                {!isCompleted && (
                                    <form onSubmit={handleSubmit} className="flex gap-2 border-t p-3">
                                        <textarea
                                            value={data.comentario}
                                            onChange={(e) => setData('comentario', e.target.value)}
                                            onKeyDown={handleKeyDown}
                                            placeholder="Escribe un mensaje... (Enter para enviar)"
                                            className="textarea textarea-bordered min-h-10 flex-1 resize-none py-2"
                                            rows={1}
                                        />
                                        <Button type="submit" disabled={processing || !data.comentario.trim()}>
                                            {processing ? (
                                                <Loader2Icon className="size-4 animate-spin" />
                                            ) : (
                                                <SendIcon className="size-4" />
                                            )}
                                        </Button>
                                    </form>
                                )}

                                {isCompleted && (
                                    <div className="border-t bg-gray-50 p-4 text-center text-sm text-gray-500 dark:bg-gray-800">
                                        Este ticket ha sido completado. No se pueden enviar mas mensajes.
                                    </div>
                                )}
                            </div>
                        ) : (
                            <div className="p-6">
                                {ticket.historial && ticket.historial.length > 0 ? (
                                    <div className="relative">
                                        {/* Timeline line */}
                                        <div className="absolute left-4 top-0 h-full w-0.5 bg-gray-200 dark:bg-gray-600" />

                                        <div className="space-y-4">
                                            {ticket.historial.map((h, index) => {
                                                const isFirst = index === 0;
                                                const statusCompleted = h.status && h.status.orden >= 8;

                                                return (
                                                    <div key={h.id} className="relative flex items-start gap-4 pl-8">
                                                        {/* Timeline dot */}
                                                        <div
                                                            className={`absolute left-2 top-1 flex size-5 -translate-x-1/2 items-center justify-center rounded-full ${
                                                                !h.status?.color && !isFirst ? 'bg-gray-300 dark:bg-gray-500' : ''
                                                            }`}
                                                            style={h.status?.color ? { backgroundColor: h.status.color, opacity: isFirst ? 1 : 0.5 } : undefined}
                                                        >
                                                            {statusCompleted ? (
                                                                <CheckCircleIcon className="size-3 text-white" />
                                                            ) : (
                                                                <ClockIcon className="size-3 text-white" />
                                                            )}
                                                        </div>

                                                        {/* Content */}
                                                        <div
                                                            className={`flex-1 rounded-lg p-3 ${
                                                                isFirst
                                                                    ? 'border-2 border-primary/20 bg-primary/5'
                                                                    : 'bg-gray-50 dark:bg-gray-700'
                                                            }`}
                                                        >
                                                            <p className="font-medium">{h.status?.descripcion}</p>
                                                            <p className="text-xs text-gray-500">
                                                                {new Date(h.created_at).toLocaleString('es-MX', {
                                                                    day: 'numeric',
                                                                    month: 'short',
                                                                    year: 'numeric',
                                                                    hour: '2-digit',
                                                                    minute: '2-digit',
                                                                })}
                                                            </p>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                ) : (
                                    <p className="text-sm text-gray-500">No hay historial de estados.</p>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthLayout>
    );
}
