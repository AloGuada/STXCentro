import { cn } from '@/lib/utils';
import type { AlmUsuarioDemo } from '@/types/models';
import { CheckIcon, ChevronDownIcon, SearchIcon, XIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type Props = {
    usuarios: AlmUsuarioDemo[];
    seleccionados: number[];
    onChange: (ids: number[]) => void;
    placeholder?: string;
};

/**
 * Selección múltiple de usuarios que pueden firmar un documento. Los elegidos
 * quedan como chips porque lo normal es que sean pocos y hay que verlos todos
 * de un golpe: la regla es que basta con que firme cualquiera de ellos.
 */
export function UsuariosMultiselect({
    usuarios,
    seleccionados,
    onChange,
    placeholder = 'Sin aprobadores',
}: Props) {
    const [abierto, setAbierto] = useState(false);
    const [query, setQuery] = useState('');
    const contenedor = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const alClicFuera = (e: MouseEvent) => {
            if (!contenedor.current?.contains(e.target as Node)) {
                setAbierto(false);
            }
        };
        document.addEventListener('mousedown', alClicFuera);

        return () => document.removeEventListener('mousedown', alClicFuera);
    }, []);

    const alternar = (id: number) =>
        onChange(seleccionados.includes(id) ? seleccionados.filter((x) => x !== id) : [...seleccionados, id]);

    const elegidos = usuarios.filter((u) => seleccionados.includes(u.id));
    const filtrados = usuarios.filter((u) =>
        `${u.nombre} ${u.puesto}`.toLowerCase().includes(query.trim().toLowerCase()),
    );

    return (
        <div ref={contenedor} className="relative">
            <div
                className="rounded-box border-base-300 flex min-h-10 cursor-pointer flex-wrap items-center gap-1.5 border p-1.5"
                onClick={() => setAbierto((v) => !v)}
            >
                {elegidos.length === 0 ? (
                    <span className="text-base-content/50 px-1 text-sm">{placeholder}</span>
                ) : (
                    elegidos.map((u) => (
                        <span key={u.id} className="badge badge-primary badge-sm gap-1 py-2.5">
                            {u.nombre}
                            <button
                                type="button"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    alternar(u.id);
                                }}
                                aria-label={`Quitar ${u.nombre}`}
                            >
                                <XIcon className="size-3" />
                            </button>
                        </span>
                    ))
                )}
                <ChevronDownIcon
                    className={cn('text-base-content/50 ml-auto size-4 shrink-0', abierto && 'rotate-180')}
                />
            </div>

            {abierto && (
                <div className="rounded-box border-base-300 bg-base-100 absolute z-50 mt-1 w-full border shadow-lg">
                    <div className="border-base-300 flex items-center gap-2 border-b px-3 py-2">
                        <SearchIcon className="text-base-content/50 size-4 shrink-0" />
                        <input
                            type="text"
                            className="w-full bg-transparent text-sm outline-none"
                            placeholder="Buscar por nombre o puesto..."
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            autoFocus
                        />
                    </div>

                    <ul className="max-h-60 overflow-auto py-1">
                        {filtrados.length === 0 ? (
                            <li className="text-base-content/50 px-3 py-4 text-center text-sm">Sin coincidencias.</li>
                        ) : (
                            filtrados.map((u) => {
                                const activo = seleccionados.includes(u.id);

                                return (
                                    <li key={u.id}>
                                        <button
                                            type="button"
                                            className={cn(
                                                'hover:bg-base-200 flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm',
                                                activo && 'font-medium',
                                            )}
                                            onClick={() => alternar(u.id)}
                                        >
                                            <CheckIcon
                                                className={cn('size-4 shrink-0', activo ? 'text-primary' : 'opacity-0')}
                                            />
                                            <span>{u.nombre}</span>
                                            <span className="text-base-content/50 ml-auto text-xs">{u.puesto}</span>
                                        </button>
                                    </li>
                                );
                            })
                        )}
                    </ul>
                </div>
            )}
        </div>
    );
}
