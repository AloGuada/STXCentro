import { useEffect, useRef, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { DgNota } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { CalendarDays, FilePlus2, NotebookPen, Trash2 } from 'lucide-react';

type NotaCard = Pick<DgNota, 'id' | 'titulo' | 'created_at' | 'updated_at'>;

type Props = {
    notas: NotaCard[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Dirección General', href: '/admin/dg' },
    { title: 'Mi Libreta', href: '/admin/dg/libreta' },
];

function formatearFecha(iso: string | null): string {
    if (!iso) return '';
    try {
        return new Date(iso).toLocaleString('es-MX', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return '';
    }
}

export default function NotasIndex({ notas }: Props) {
    const crear = () => {
        router.post('/admin/dg/notas', {}, { preserveScroll: true });
    };

    const eliminar = (nota: NotaCard) => {
        if (!confirm(`¿Eliminar la nota "${nota.titulo}"?`)) return;
        router.delete(`/admin/dg/notas/${nota.id}`, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mi Libreta · DG" />

            <div className="p-6 space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold flex items-center gap-2">
                            <NotebookPen className="size-6 text-primary" />
                            Mi Libreta
                        </h1>
                     
                    </div>
                    <button type="button" onClick={crear} className="btn btn-primary">
                        <FilePlus2 className="size-4" /> Nuevo apunte
                    </button>
                </div>

                {notas.length === 0 ? (
                    <div className="bg-base-200 rounded-lg p-12 text-center text-base-content/60">
                        Aún no tienes apuntes. Crea uno para empezar.
                    </div>
                ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        {notas.map((nota) => (
                            <NotaCard key={nota.id} nota={nota} onEliminar={eliminar} />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function NotaCard({ nota, onEliminar }: { nota: NotaCard; onEliminar: (n: NotaCard) => void }) {
    const [titulo, setTitulo] = useState(nota.titulo);
    const [editando, setEditando] = useState(false);
    const ultimoRef = useRef(nota.titulo);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        setTitulo(nota.titulo);
        ultimoRef.current = nota.titulo;
    }, [nota.titulo]);

    useEffect(() => {
        if (editando) {
            inputRef.current?.focus();
            inputRef.current?.select();
        }
    }, [editando]);

    const guardarTitulo = () => {
        const limpio = titulo.trim() || 'Sin título';
        setEditando(false);
        if (limpio === ultimoRef.current) return;
        ultimoRef.current = limpio;
        router.patch(
            `/admin/dg/notas/${nota.id}`,
            { titulo: limpio },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <div className="card bg-base-100 border border-base-300 hover:border-primary hover:shadow-lg transition relative group">
            <div className="card-body p-5">
                <div className="flex items-start gap-2">
                    {editando ? (
                        <input
                            ref={inputRef}
                            type="text"
                            value={titulo}
                            onChange={(e) => setTitulo(e.target.value)}
                            onBlur={guardarTitulo}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') guardarTitulo();
                                if (e.key === 'Escape') {
                                    setTitulo(ultimoRef.current);
                                    setEditando(false);
                                }
                            }}
                            className="input input-sm input-bordered w-full font-semibold"
                            maxLength={255}
                        />
                    ) : (
                        <h2
                            className="font-semibold truncate flex-1 cursor-text hover:bg-base-200 rounded px-1 -mx-1"
                            title="Clic para editar"
                            onClick={(e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                setEditando(true);
                            }}
                        >
                            {titulo || 'Sin título'}
                        </h2>
                    )}
                </div>

                <Link
                    href={`/admin/dg/notas/${nota.id}`}
                    className="mt-3 flex items-center gap-1.5 text-xs text-base-content/60 hover:text-primary"
                >
                    <CalendarDays className="size-3" />
                    Editada {formatearFecha(nota.updated_at)}
                </Link>

                <div className="mt-3 flex justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    <Link href={`/admin/dg/notas/${nota.id}`} className="btn btn-xs btn-primary">
                        Abrir
                    </Link>
                    <button
                        type="button"
                        onClick={() => onEliminar(nota)}
                        className="btn btn-xs btn-ghost text-error"
                        title="Eliminar"
                    >
                        <Trash2 className="size-3.5" />
                    </button>
                </div>
            </div>
        </div>
    );
}
