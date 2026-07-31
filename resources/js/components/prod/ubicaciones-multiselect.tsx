import { cn } from '@/lib/utils';
import type { ProdUbicacion } from '@/types/models';
import { CheckIcon, ChevronDownIcon, SearchIcon, XIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type Props = {
    ubicaciones: Pick<ProdUbicacion, 'id' | 'nombre'>[];
    seleccionadas: number[];
    onChange: (ids: number[]) => void;
};

/**
 * Selección múltiple de ubicaciones. El catálogo pasa del centenar de módulos,
 * así que se buscan en un desplegable y las elegidas quedan como chips.
 */
export function UbicacionesMultiselect({ ubicaciones, seleccionadas, onChange }: Props) {
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

    if (ubicaciones.length === 0) {
        return (
            <p className="text-base-content/60 text-sm">
                No hay ubicaciones en el catálogo todavía. Créalas en Producción → Ubicaciones.
            </p>
        );
    }

    const alternar = (id: number) => {
        onChange(seleccionadas.includes(id) ? seleccionadas.filter((x) => x !== id) : [...seleccionadas, id]);
    };

    const elegidas = ubicaciones.filter((u) => seleccionadas.includes(u.id));
    const filtradas = ubicaciones.filter((u) => u.nombre.toLowerCase().includes(query.trim().toLowerCase()));

    return (
        <div ref={contenedor} className="relative">
            <div
                className="rounded-box border-base-300 flex min-h-12 cursor-pointer flex-wrap items-center gap-1.5 border p-2"
                onClick={() => setAbierto((v) => !v)}
            >
                {elegidas.length === 0 ? (
                    <span className="text-base-content/50 px-1 text-sm">Selecciona una o más ubicaciones...</span>
                ) : (
                    elegidas.map((u) => (
                        <span key={u.id} className="badge badge-primary gap-1 py-3">
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
                <ChevronDownIcon className={cn('text-base-content/50 ml-auto size-4 shrink-0', abierto && 'rotate-180')} />
            </div>

            {abierto && (
                <div className="rounded-box border-base-300 bg-base-100 absolute z-50 mt-1 w-full border shadow-lg">
                    <div className="border-base-300 flex items-center gap-2 border-b px-3 py-2">
                        <SearchIcon className="text-base-content/50 size-4 shrink-0" />
                        <input
                            type="text"
                            className="w-full bg-transparent text-sm outline-none"
                            placeholder="Buscar ubicación..."
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            autoFocus
                        />
                    </div>

                    <ul className="max-h-60 overflow-auto py-1">
                        {filtradas.length === 0 ? (
                            <li className="text-base-content/50 px-3 py-4 text-center text-sm">Sin coincidencias.</li>
                        ) : (
                            filtradas.map((u) => {
                                const activa = seleccionadas.includes(u.id);

                                return (
                                    <li key={u.id}>
                                        <button
                                            type="button"
                                            className={cn(
                                                'hover:bg-base-200 flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm',
                                                activa && 'font-medium',
                                            )}
                                            onClick={() => alternar(u.id)}
                                        >
                                            <CheckIcon
                                                className={cn('size-4 shrink-0', activa ? 'text-primary' : 'opacity-0')}
                                            />
                                            {u.nombre}
                                        </button>
                                    </li>
                                );
                            })
                        )}
                    </ul>

                    {elegidas.length > 0 && (
                        <div className="border-base-300 flex items-center justify-between border-t px-3 py-2">
                            <span className="text-base-content/60 text-xs">{elegidas.length} seleccionadas</span>
                            <button
                                type="button"
                                className="link link-error text-xs"
                                onClick={() => onChange([])}
                            >
                                Limpiar
                            </button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
