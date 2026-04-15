import { useState } from 'react';
import { StickyNote } from 'lucide-react';

type Props = {
    notas: string | null;
    /** Tamaño del botón. Default 'xs'. */
    size?: 'xs' | 'sm';
};

export default function NotasPopover({ notas, size = 'xs' }: Props) {
    const [show, setShow] = useState(false);
    const tieneNotas = !!notas && notas.trim() !== '' && notas !== '<br>';

    if (!tieneNotas) return null;

    const btnClass = size === 'sm' ? 'btn btn-sm btn-circle btn-ghost text-amber-600' : 'btn btn-xs btn-circle btn-ghost text-amber-600';
    const iconClass = size === 'sm' ? 'size-4' : 'size-3.5';

    return (
        <div
            className="relative inline-block"
            onMouseEnter={() => setShow(true)}
            onMouseLeave={() => setShow(false)}
        >
            <button
                type="button"
                onClick={() => setShow((v) => !v)}
                className={btnClass}
                title="Ver notas"
                aria-label="Ver notas"
            >
                <StickyNote className={iconClass} />
            </button>
            {show && (
                <div className="absolute right-0 top-full mt-1 z-30 w-80 bg-base-100 border border-base-300 rounded-lg shadow-xl p-3">
                    <div className="flex items-center gap-2 pb-2 mb-2 border-b border-base-300">
                        <StickyNote className="size-4 text-amber-600" />
                        <span className="font-semibold text-sm">Notas</span>
                    </div>
                    <div
                        className="text-sm text-slate-900 max-h-64 overflow-auto bg-white [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_u]:underline [&_b]:font-bold [&_strong]:font-bold [&_i]:italic [&_em]:italic [&_s]:line-through"
                        dangerouslySetInnerHTML={{ __html: notas ?? '' }}
                    />
                </div>
            )}
        </div>
    );
}
