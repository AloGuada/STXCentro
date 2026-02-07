import { Button } from '@/components/ui/button';
import type { StiTicketComentario } from '@/types/models';
import { router } from '@inertiajs/react';
import { Loader2Icon, SendIcon, UserIcon, WrenchIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type TicketChatProps = {
    comentarios: StiTicketComentario[];
    storeUrl: string;
    descripcionInicial: string;
    readOnly?: boolean;
};

export function TicketChat({ comentarios, storeUrl, descripcionInicial, readOnly = false }: TicketChatProps) {
    const [mensaje, setMensaje] = useState('');
    const [enviando, setEnviando] = useState(false);
    const chatContainerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (chatContainerRef.current) {
            chatContainerRef.current.scrollTop = chatContainerRef.current.scrollHeight;
        }
    }, [comentarios]);

    const handleEnviar = () => {
        if (!mensaje.trim()) return;

        setEnviando(true);
        router.post(
            storeUrl,
            { comentario: mensaje },
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
            handleEnviar();
        }
    };

    return (
        <div className="flex h-96 flex-col rounded-lg border">
            {/* Descripcion inicial */}
            <div className="border-b bg-gray-50 px-4 py-3 dark:bg-gray-800">
                <p className="text-xs font-medium text-gray-500">Descripcion inicial del problema:</p>
                <p className="mt-1 text-sm">{descripcionInicial}</p>
            </div>

            {/* Chat messages */}
            <div ref={chatContainerRef} className="flex-1 space-y-3 overflow-y-auto p-4">
                {comentarios.length === 0 ? (
                    <p className="py-8 text-center text-sm text-gray-400">No hay comentarios aun.</p>
                ) : (
                    comentarios.map((comentario) => (
                        <div
                            key={comentario.id}
                            className={`flex ${comentario.tipo === 'tecnico' ? 'justify-end' : 'justify-start'}`}
                        >
                            <div
                                className={`max-w-[75%] rounded-lg px-4 py-2 ${
                                    comentario.tipo === 'tecnico'
                                        ? 'bg-primary text-primary-content'
                                        : 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-gray-100'
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
                                            ? 'text-primary-content/70'
                                            : 'text-gray-500 dark:text-gray-400'
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
            {!readOnly && (
                <div className="flex gap-2 border-t p-3">
                    <textarea
                        value={mensaje}
                        onChange={(e) => setMensaje(e.target.value)}
                        onKeyDown={handleKeyDown}
                        placeholder="Escribe un comentario... (Enter para enviar)"
                        className="textarea textarea-bordered min-h-10 flex-1 resize-none py-2"
                        rows={1}
                    />
                    <Button type="button" onClick={handleEnviar} disabled={enviando || !mensaje.trim()}>
                        {enviando ? <Loader2Icon className="size-4 animate-spin" /> : <SendIcon className="size-4" />}
                    </Button>
                </div>
            )}
        </div>
    );
}
