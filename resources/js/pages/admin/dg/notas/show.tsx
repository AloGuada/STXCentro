import { useEffect, useRef, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { DgNota } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { Check, ChevronLeft, Loader2, Trash2 } from 'lucide-react';
import NotasEditor from '@/components/dg/notas-editor';
import AppLogoIcon from '@/components/app-logo-icon';

type Props = {
    nota: Pick<DgNota, 'id' | 'titulo' | 'contenido' | 'created_at' | 'updated_at'>;
};

const TITULO_AUTOSAVE_MS = 1500;

export default function NotaShow({ nota }: Props) {
    const [titulo, setTitulo] = useState(nota.titulo);
    const [estadoTitulo, setEstadoTitulo] = useState<'guardado' | 'escribiendo' | 'guardando'>('guardado');
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const ultimoTitulo = useRef<string>(nota.titulo);

    useEffect(() => {
        setTitulo(nota.titulo);
        ultimoTitulo.current = nota.titulo;
    }, [nota.id, nota.titulo]);

    const guardarTitulo = (valor: string) => {
        const limpio = valor.trim() || 'Sin título';
        if (limpio === ultimoTitulo.current) {
            setEstadoTitulo('guardado');
            return;
        }
        setEstadoTitulo('guardando');
        router.patch(
            `/admin/dg/notas/${nota.id}`,
            { titulo: limpio },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    ultimoTitulo.current = limpio;
                    setEstadoTitulo('guardado');
                },
                onError: () => setEstadoTitulo('escribiendo'),
            },
        );
    };

    const onTituloChange = (valor: string) => {
        setTitulo(valor);
        setEstadoTitulo('escribiendo');
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => guardarTitulo(valor), TITULO_AUTOSAVE_MS);
    };

    const eliminar = () => {
        if (!confirm(`¿Eliminar el apunte "${titulo}"?`)) return;
        router.delete(`/admin/dg/notas/${nota.id}`);
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Dirección General', href: '/admin/dg' },
        { title: 'Mi Libreta', href: '/admin/dg/notas' },
        { title: nota.titulo, href: `/admin/dg/notas/${nota.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${titulo} · Mi Libreta`} />

            <div className="p-6 space-y-4">
                <div className="flex flex-wrap items-center gap-3 justify-between">
                    <div className="flex items-center gap-3 flex-1 min-w-0">
                        <Link href="/admin/dg/notas" className="btn btn-ghost btn-sm">
                            <ChevronLeft className="size-4" /> Volver
                        </Link>
                        <input
                            type="text"
                            value={titulo}
                            onChange={(e) => onTituloChange(e.target.value)}
                            onBlur={() => {
                                if (debounceRef.current) clearTimeout(debounceRef.current);
                                guardarTitulo(titulo);
                            }}
                            placeholder="Sin título"
                            className="input input-lg input-ghost font-semibold text-xl flex-1 min-w-0 focus:bg-base-200"
                            maxLength={255}
                        />
                        <div className="text-xs">
                            {estadoTitulo === 'guardando' && (
                                <span className="flex items-center gap-1 text-base-content/70">
                                    <Loader2 className="size-3 animate-spin" />
                                </span>
                            )}
                            {estadoTitulo === 'escribiendo' && (
                                <span className="text-base-content/60">sin guardar</span>
                            )}
                            {estadoTitulo === 'guardado' && (
                                <span className="flex items-center gap-1 text-success">
                                    <Check className="size-3" />
                                </span>
                            )}
                        </div>
                    </div>
                    <button type="button" onClick={eliminar} className="btn btn-sm btn-ghost text-error">
                        <Trash2 className="size-4" /> Eliminar
                    </button>
                </div>

                {/* Libreta con rayas y logo watermark */}
                <div className="relative rounded-lg overflow-hidden shadow-lg border border-base-300 bg-white">
                    {/* Logo watermark */}
                    <div
                        className="absolute inset-0 flex items-center justify-center pointer-events-none select-none"
                        aria-hidden="true"
                    >
                        <AppLogoIcon className="h-64 text-slate-300" style={{ opacity: 0.08 }} />
                    </div>

                    <NotasEditor
                        saveUrl={`/admin/dg/notas/${nota.id}`}
                        payloadKey="contenido"
                        initialHtml={nota.contenido}
                        placeholder="Escribe tu apunte aquí…"
                        contentClassName="relative z-10 bg-transparent"
                        contentStyle={{
                            minHeight: '60vh',
                            lineHeight: '2rem',
                            padding: '1.5rem 2rem',
                            backgroundImage:
                                'repeating-linear-gradient(to bottom, transparent 0, transparent calc(2rem - 1px), rgba(148, 163, 184, 0.35) calc(2rem - 1px), rgba(148, 163, 184, 0.35) 2rem)',
                            backgroundAttachment: 'local',
                        }}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
