import { useCallback, useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { Bold, Check, Highlighter, Italic, List, Loader2, Palette, Strikethrough, Type, Underline } from 'lucide-react';

type Props = {
    archivoId: number;
    initialHtml: string | null;
    readOnly?: boolean;
    onSaved?: (html: string) => void;
};

const COLORES = [
    { name: 'Negro', value: '#0f172a' },
    { name: 'Rojo', value: '#dc2626' },
    { name: 'Verde', value: '#16a34a' },
    { name: 'Azul', value: '#2563eb' },
    { name: 'Amarillo', value: '#ca8a04' },
    { name: 'Morado', value: '#9333ea' },
];

const RESALTADORES = [
    { name: 'Ninguno', value: 'transparent' },
    { name: 'Amarillo', value: '#fef08a' },
    { name: 'Verde', value: '#bbf7d0' },
    { name: 'Azul', value: '#bfdbfe' },
    { name: 'Rosa', value: '#fbcfe8' },
    { name: 'Naranja', value: '#fed7aa' },
];

function aplicarResaltador(color: string) {
    // hiliteColor = Firefox, backColor = Chrome/Safari
    if (!document.execCommand('hiliteColor', false, color)) {
        document.execCommand('backColor', false, color);
    }
}

const AUTOSAVE_MS = 1500;

type EstadoGuardado = 'guardado' | 'escribiendo' | 'guardando';

function exec(cmd: string, value?: string) {
    document.execCommand(cmd, false, value);
}

export default function NotasEditor({ archivoId, initialHtml, readOnly = false, onSaved }: Props) {
    const editorRef = useRef<HTMLDivElement>(null);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const ultimoGuardado = useRef<string>(initialHtml ?? '');
    const [estado, setEstado] = useState<EstadoGuardado>('guardado');
    const [showColors, setShowColors] = useState(false);
    const [showResaltadores, setShowResaltadores] = useState(false);

    useEffect(() => {
        if (editorRef.current) {
            editorRef.current.innerHTML = initialHtml ?? '';
            ultimoGuardado.current = initialHtml ?? '';
            setEstado('guardado');
        }
    }, [archivoId, initialHtml]);

    const guardar = useCallback(() => {
        if (!editorRef.current) return;
        const html = editorRef.current.innerHTML;
        if (html === ultimoGuardado.current) {
            setEstado('guardado');
            return;
        }
        setEstado('guardando');
        router.patch(
            `/admin/dg/archivos/${archivoId}/notas`,
            { notas: html },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    ultimoGuardado.current = html;
                    setEstado('guardado');
                    onSaved?.(html);
                },
                onError: () => setEstado('escribiendo'),
            },
        );
    }, [archivoId, onSaved]);

    const programarGuardado = useCallback(() => {
        setEstado('escribiendo');
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            guardar();
        }, AUTOSAVE_MS);
    }, [guardar]);

    // Al desmontar o cambiar de archivo: guardar pendientes de inmediato
    useEffect(() => {
        if (readOnly) return;

        return () => {
            if (debounceRef.current) {
                clearTimeout(debounceRef.current);
                debounceRef.current = null;
                // flush sync: si hay cambios sin guardar, disparar fetch
                if (editorRef.current && editorRef.current.innerHTML !== ultimoGuardado.current) {
                    const html = editorRef.current.innerHTML;
                    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
                    fetch(`/admin/dg/archivos/${archivoId}/notas`, {
                        method: 'PATCH',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'X-Requested-With': 'XMLHttpRequest',
                            Accept: 'application/json',
                        },
                        body: JSON.stringify({ notas: html }),
                    }).catch(() => {});
                }
            }
        };
    }, [archivoId, readOnly]);

    if (readOnly) {
        return (
            <div className="flex flex-col h-full">
                <div className="px-3 py-2 border-b border-base-300 bg-base-200/60 text-xs text-base-content/70">
                    Solo lectura — notas del Director General
                </div>
                <div
                    ref={editorRef}
                    className="flex-1 overflow-auto p-4 text-base leading-relaxed bg-white text-slate-900 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_s]:line-through [&_strike]:line-through [&_u]:underline [&_b]:font-bold [&_strong]:font-bold [&_i]:italic [&_em]:italic"
                    style={{ minHeight: '200px' }}
                />
            </div>
        );
    }

    return (
        <div className="flex flex-col h-full">
            <div className="flex flex-wrap items-center gap-1 p-2 border-b border-base-300 bg-base-100">
                <button type="button" onClick={() => { exec('bold'); programarGuardado(); }} className="btn btn-xs btn-ghost" title="Negrita">
                    <Bold className="size-3.5" />
                </button>
                <button type="button" onClick={() => { exec('italic'); programarGuardado(); }} className="btn btn-xs btn-ghost" title="Cursiva">
                    <Italic className="size-3.5" />
                </button>
                <button type="button" onClick={() => { exec('underline'); programarGuardado(); }} className="btn btn-xs btn-ghost" title="Subrayado">
                    <Underline className="size-3.5" />
                </button>
                <button type="button" onClick={() => { exec('strikeThrough'); programarGuardado(); }} className="btn btn-xs btn-ghost" title="Tachado">
                    <Strikethrough className="size-3.5" />
                </button>
                <div className="w-px h-5 bg-base-300 mx-1" />
                <button type="button" onClick={() => { exec('insertUnorderedList'); programarGuardado(); }} className="btn btn-xs btn-ghost" title="Viñetas">
                    <List className="size-3.5" />
                </button>
                <div className="w-px h-5 bg-base-300 mx-1" />
                <div className="flex items-center gap-1" title="Tamaño de letra">
                    <Type className="size-3.5 text-base-content/60" />
                    <select
                        defaultValue="4"
                        onChange={(e) => {
                            exec('fontSize', e.target.value);
                            programarGuardado();
                        }}
                        className="select select-xs select-ghost min-h-0 h-7 px-1"
                    >
                        <option value="2">Pequeño</option>
                        <option value="3">Normal</option>
                        <option value="4">Mediano</option>
                        <option value="5">Grande</option>
                        <option value="6">Muy grande</option>
                        <option value="7">Enorme</option>
                    </select>
                </div>
                <div className="w-px h-5 bg-base-300 mx-1" />
                <div className="relative">
                    <button type="button" onClick={() => { setShowColors((v) => !v); setShowResaltadores(false); }} className="btn btn-xs btn-ghost" title="Color de texto">
                        <Palette className="size-3.5" />
                    </button>
                    {showColors && (
                        <div className="absolute left-0 top-full mt-1 z-20 bg-base-100 border border-base-300 rounded-md shadow-lg p-2 flex gap-1">
                            {COLORES.map((c) => (
                                <button
                                    key={c.value}
                                    type="button"
                                    title={c.name}
                                    onClick={() => {
                                        exec('foreColor', c.value);
                                        setShowColors(false);
                                        programarGuardado();
                                    }}
                                    className="size-5 rounded border border-base-300"
                                    style={{ backgroundColor: c.value }}
                                />
                            ))}
                        </div>
                    )}
                </div>
                <div className="relative">
                    <button type="button" onClick={() => { setShowResaltadores((v) => !v); setShowColors(false); }} className="btn btn-xs btn-ghost" title="Resaltador">
                        <Highlighter className="size-3.5" />
                    </button>
                    {showResaltadores && (
                        <div className="absolute left-0 top-full mt-1 z-20 bg-base-100 border border-base-300 rounded-md shadow-lg p-2 flex gap-1">
                            {RESALTADORES.map((c) => (
                                <button
                                    key={c.value}
                                    type="button"
                                    title={c.name}
                                    onClick={() => {
                                        aplicarResaltador(c.value);
                                        setShowResaltadores(false);
                                        programarGuardado();
                                    }}
                                    className="size-5 rounded border border-base-300"
                                    style={{
                                        backgroundColor: c.value === 'transparent' ? '#fff' : c.value,
                                        backgroundImage: c.value === 'transparent' ? 'linear-gradient(45deg, transparent 45%, #ef4444 45%, #ef4444 55%, transparent 55%)' : undefined,
                                    }}
                                />
                            ))}
                        </div>
                    )}
                </div>
                <div className="ml-auto flex items-center gap-2 text-xs">
                    {estado === 'guardando' && (
                        <span className="flex items-center gap-1 text-base-content/70">
                            <Loader2 className="size-3 animate-spin" /> Guardando...
                        </span>
                    )}
                    {estado === 'escribiendo' && (
                        <span className="text-base-content/60">Sin guardar</span>
                    )}
                    {estado === 'guardado' && (
                        <span className="flex items-center gap-1 text-success">
                            <Check className="size-3" /> Guardado
                        </span>
                    )}
                </div>
            </div>

            <div
                ref={editorRef}
                contentEditable
                suppressContentEditableWarning
                onInput={programarGuardado}
                onBlur={() => {
                    if (debounceRef.current) {
                        clearTimeout(debounceRef.current);
                        debounceRef.current = null;
                    }
                    guardar();
                }}
                className="flex-1 overflow-auto p-4 text-base leading-relaxed bg-white text-slate-900 focus:outline-none [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_s]:line-through [&_strike]:line-through [&_u]:underline [&_b]:font-bold [&_strong]:font-bold [&_i]:italic [&_em]:italic"
                style={{ minHeight: '200px' }}
            />
        </div>
    );
}
