import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { StickyNote } from 'lucide-react';

type Props = {
    notas: string | null;
    /** Tamaño del botón. Default 'xs'. */
    size?: 'xs' | 'sm';
};

export default function NotasPopover({ notas, size = 'xs' }: Props) {
    const [show, setShow] = useState(false);
    const [coords, setCoords] = useState<{ top: number; left: number } | null>(null);
    const btnRef = useRef<HTMLButtonElement>(null);
    const popRef = useRef<HTMLDivElement>(null);
    const closeTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const tieneNotas = !!notas && notas.trim() !== '' && notas !== '<br>';

    const calcularCoords = () => {
        if (!btnRef.current) return;
        const rect = btnRef.current.getBoundingClientRect();
        const popWidth = 320; // w-80
        const margin = 8;
        // Alinear por la derecha del botón, pero si se sale por la izquierda, alinear por la izquierda
        let left = rect.right - popWidth;
        if (left < margin) left = margin;
        // Si se sale por la derecha del viewport, empujar
        if (left + popWidth > window.innerWidth - margin) {
            left = window.innerWidth - popWidth - margin;
        }
        setCoords({ top: rect.bottom + 4, left });
    };

    useEffect(() => {
        if (!show) return;
        calcularCoords();
        const onScroll = () => calcularCoords();
        const onResize = () => calcularCoords();
        window.addEventListener('scroll', onScroll, true);
        window.addEventListener('resize', onResize);
        return () => {
            window.removeEventListener('scroll', onScroll, true);
            window.removeEventListener('resize', onResize);
        };
    }, [show]);

    const abrir = () => {
        if (closeTimer.current) clearTimeout(closeTimer.current);
        setShow(true);
    };

    const cerrarConDelay = () => {
        if (closeTimer.current) clearTimeout(closeTimer.current);
        closeTimer.current = setTimeout(() => setShow(false), 150);
    };

    if (!tieneNotas) return null;

    const btnClass = size === 'sm'
        ? 'btn btn-sm btn-circle btn-ghost text-green-600'
        : 'btn btn-xs btn-circle btn-ghost text-green-600';
    const iconClass = size === 'sm' ? 'size-4' : 'size-3.5';

    return (
        <>
            <button
                ref={btnRef}
                type="button"
                onMouseEnter={abrir}
                onMouseLeave={cerrarConDelay}
                onClick={() => setShow((v) => !v)}
                className={btnClass}
                title="Ver notas"
                aria-label="Ver notas"
            >
                <StickyNote className={iconClass} />
            </button>

            {show && coords && createPortal(
                <div
                    ref={popRef}
                    onMouseEnter={abrir}
                    onMouseLeave={cerrarConDelay}
                    className="fixed z-[100] w-80 bg-base-100 border border-base-300 rounded-lg shadow-xl p-3"
                    style={{ top: coords.top, left: coords.left }}
                >
                    <div className="flex items-center gap-2 pb-2 mb-2 border-b border-base-300">
                        <StickyNote className="size-4 text-green-600" />
                        <span className="font-semibold text-sm">Notas</span>
                    </div>
                    <div
                        className="text-sm text-slate-900 max-h-64 overflow-auto bg-white [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_u]:underline [&_b]:font-bold [&_strong]:font-bold [&_i]:italic [&_em]:italic [&_s]:line-through"
                        dangerouslySetInnerHTML={{ __html: notas ?? '' }}
                    />
                </div>,
                document.body,
            )}
        </>
    );
}
