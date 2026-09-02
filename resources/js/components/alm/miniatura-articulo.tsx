import { ImageIcon } from 'lucide-react';
import { useState } from 'react';

type Props = {
    /** Foto del artículo. Sin ella queda el hueco marcado. */
    url: string | null;
    /** Va al `alt` y al título de la ampliación. */
    descripcion: string;
    /** Tamaño del recuadro: chico en las tablas, más grande en la ficha. */
    className?: string;
    iconClassName?: string;
};

/** Lado de la ampliación en píxeles. Se necesita el número para no sacarla de la ventana. */
const LADO_AMPLIACION = 256;

/**
 * Miniatura del artículo, con la foto completa al pasar el mouse.
 *
 * En la tabla la miniatura es de 40 px: alcanza para distinguir un disco de un
 * guante, pero no para leer una etiqueta ni ver de qué modelo es la pulidora.
 *
 * La ampliación se dibuja en posición fija, calculada del recuadro, en vez de
 * absoluta dentro de la celda: la tabla vive en un contenedor con scroll, y ahí
 * cualquier cosa absoluta se recorta justo cuando se quiere ver la foto entera.
 */
export function MiniaturaArticulo({ url, descripcion, className = 'size-10', iconClassName = 'size-4' }: Props) {
    const [ancla, setAncla] = useState<{ top: number; left: number } | null>(null);

    if (!url) {
        return (
            <div
                className={`border-base-300 text-base-content/30 flex items-center justify-center rounded border border-dashed ${className}`}
                title="Sin imagen"
            >
                <ImageIcon className={iconClassName} />
            </div>
        );
    }

    return (
        <>
            <img
                src={url}
                alt={descripcion}
                className={`border-base-300 cursor-zoom-in rounded border object-cover ${className}`}
                onMouseEnter={(e) => {
                    const caja = e.currentTarget.getBoundingClientRect();

                    // Al lado del recuadro, y si no cabe abajo se sube: en los
                    // últimos renglones de la tabla saldría de la pantalla.
                    setAncla({
                        top: Math.max(8, Math.min(caja.top, window.innerHeight - LADO_AMPLIACION - 8)),
                        left: caja.right + 8,
                    });
                }}
                onMouseLeave={() => setAncla(null)}
            />

            {ancla && (
                <img
                    src={url}
                    alt=""
                    aria-hidden
                    className="border-base-300 bg-base-100 pointer-events-none fixed z-50 rounded border object-contain p-1 shadow-xl"
                    style={{ top: ancla.top, left: ancla.left, width: LADO_AMPLIACION, height: LADO_AMPLIACION }}
                />
            )}
        </>
    );
}
