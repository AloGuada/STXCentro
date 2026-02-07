import { Button } from '@/components/ui/button';
import type { Departamento, StiStatus, StiTicket, StiTicketComentario, StiTicketHistorial } from '@/types/models';
import { router } from '@inertiajs/react';
import { CheckCircleIcon, ClockIcon, Loader2Icon, MessageSquareIcon, SendIcon, UserIcon, WrenchIcon, XIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type TicketHistorialPublicoModalProps = {
    open: boolean;
    onClose: () => void;
    ticket:
        | (StiTicket & {
              departamento: Departamento;
              historial: (StiTicketHistorial & { status: StiStatus })[];
              comentarios?: StiTicketComentario[];
          })
        | null;
};

export function TicketHistorialPublicoModal({ open, onClose, ticket }: TicketHistorialPublicoModalProps) {
    const [activeTab, setActiveTab] = useState<'historial' | 'chat'>('chat');
    const [mensaje, setMensaje] = useState('');
    const [enviando, setEnviando] = useState(false);
    const chatContainerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (chatContainerRef.current && activeTab === 'chat') {
            chatContainerRef.current.scrollTop = chatContainerRef.current.scrollHeight;
        }
    }, [ticket?.comentarios, activeTab]);

    if (!open || !ticket) return null;

    const currentStatus = ticket.historial?.[0]?.status;
    const isCompleted = currentStatus && currentStatus.orden >= 8;
    const comentarios = ticket.comentarios ?? [];

    const handleEnviarMensaje = () => {
        if (!mensaje.trim()) return;

        setEnviando(true);
        router.post(
            `/sti/ticket/${ticket.id}/comentario`,
            {
                comentario: mensaje,
                autor: ticket.nombre_solicitante,
            },
            {
                preserveScroll: true,
                onSuccess: () => setMensaje(''),
                onFinish: () => setEnviando(false),
            }
        );
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleEnviarMensaje();
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={onClose}>
            <div
                className="flex h-[85vh] w-full max-w-2xl flex-col rounded-lg bg-white shadow-xl dark:bg-gray-800"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="flex items-center justify-between border-b px-6 py-4">
                    <h3 className="text-lg font-semibold">Ticket #{ticket.id}</h3>
                    <button type="button" onClick={onClose} className="btn btn-ghost btn-sm btn-circle">
                        <XIcon className="size-5" />
                    </button>
                </div>

                {/* Ticket Info Summary */}
                <div className="border-b bg-gray-50 px-6 py-3 dark:bg-gray-700">
                    <div className="flex flex-wrap items-center gap-4 text-sm">
                        <span><strong>Solicitante:</strong> {ticket.nombre_solicitante}</span>
                        <span><strong>Departamento:</strong> {ticket.departamento?.descripcion}</span>
                        <span
                            className={`badge gap-1 ${isCompleted ? 'badge-success' : 'badge-info'}`}
                        >
                            {isCompleted ? <CheckCircleIcon className="size-3" /> : <ClockIcon className="size-3" />}
                            {currentStatus?.descripcion ?? 'Pendiente'}
                        </span>
                    </div>
                </div>

                {/* Tabs */}
                <div className="border-b px-6">
                    <div className="flex gap-4">
                        <button
                            type="button"
                            onClick={() => setActiveTab('chat')}
                            className={`flex items-center gap-2 border-b-2 px-2 py-3 text-sm font-medium transition-colors ${
                                activeTab === 'chat'
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-gray-500 hover:text-gray-700'
                            }`}
                        >
                            <MessageSquareIcon className="size-4" />
                            Mensajes
                            {comentarios.length > 0 && (
                                <span className="badge badge-sm badge-primary">{comentarios.length}</span>
                            )}
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('historial')}
                            className={`flex items-center gap-2 border-b-2 px-2 py-3 text-sm font-medium transition-colors ${
                                activeTab === 'historial'
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-gray-500 hover:text-gray-700'
                            }`}
                        >
                            <ClockIcon className="size-4" />
                            Historial
                        </button>
                    </div>
                </div>

                {/* Content */}
                <div className="flex-1 overflow-hidden">
                    {activeTab === 'chat' ? (
                        <div className="flex h-full flex-col">
                            {/* Descripcion inicial */}
                            <div className="border-b bg-blue-50 px-4 py-3 dark:bg-blue-900/20">
                                <p className="text-xs font-medium text-gray-500">Descripcion inicial del problema:</p>
                                <p className="mt-1 text-sm">{ticket.comentario}</p>
                            </div>

                            {/* Chat messages */}
                            <div ref={chatContainerRef} className="flex-1 space-y-3 overflow-y-auto p-4">
                                {comentarios.length === 0 ? (
                                    <p className="py-8 text-center text-sm text-gray-400">
                                        No hay mensajes aun. Escribe un mensaje para comunicarte con el técnico.
                                    </p>
                                ) : (
                                    comentarios.map((comentario) => (
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
                                <div className="flex gap-2 border-t p-3">
                                    <textarea
                                        value={mensaje}
                                        onChange={(e) => setMensaje(e.target.value)}
                                        onKeyDown={handleKeyDown}
                                        placeholder="Escribe un mensaje... (Enter para enviar)"
                                        className="textarea textarea-bordered min-h-10 flex-1 resize-none py-2"
                                        rows={1}
                                    />
                                    <Button type="button" onClick={handleEnviarMensaje} disabled={enviando || !mensaje.trim()}>
                                        {enviando ? <Loader2Icon className="size-4 animate-spin" /> : <SendIcon className="size-4" />}
                                    </Button>
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="h-full overflow-y-auto p-6">
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
                                                            isFirst
                                                                ? statusCompleted
                                                                    ? 'bg-success'
                                                                    : 'bg-primary'
                                                                : 'bg-gray-300 dark:bg-gray-500'
                                                        }`}
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

                {/* Footer */}
                <div className="border-t px-6 py-4">
                    <div className="flex justify-end">
                        <button type="button" onClick={onClose} className="btn btn-sm">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
