import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { cn } from '@/lib/utils';

type Option = {
    value: string;
    label: string;
    /** Pinta la opción en rojo (ej. centro de costo sobregirado / sin presupuesto). */
    danger?: boolean;
};

type SearchSelectProps = {
    options: Option[];
    value?: string;
    onValueChange: (value: string) => void;
    placeholder?: string;
    className?: string;
    disabled?: boolean;
};

export function SearchSelect({
    options,
    value,
    onValueChange,
    placeholder = 'Buscar...',
    className,
    disabled = false,
}: SearchSelectProps) {
    const selected = options.find((o) => o.value === value);
    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(false);
    const [highlightedIndex, setHighlightedIndex] = useState(0);
    const [coords, setCoords] = useState<{ top: number; left: number; width: number } | null>(null);
    const wrapperRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const dropdownRef = useRef<HTMLUListElement>(null);

    const filtered = options.filter((o) =>
        o.label.toLowerCase().includes(query.toLowerCase()),
    );

    // Posiciona el dropdown (portal) bajo el input para que no lo recorte ningún
    // contenedor con overflow; se realinea al hacer scroll/resize.
    const updateCoords = () => {
        const rect = inputRef.current?.getBoundingClientRect();
        if (rect) {
            setCoords({ top: rect.bottom, left: rect.left, width: rect.width });
        }
    };

    const openDropdown = () => {
        setQuery('');
        setHighlightedIndex(0);
        setOpen(true);
        updateCoords();
    };

    useEffect(() => {
        if (!open) return;
        const handler = () => updateCoords();
        window.addEventListener('scroll', handler, true);
        window.addEventListener('resize', handler);
        return () => {
            window.removeEventListener('scroll', handler, true);
            window.removeEventListener('resize', handler);
        };
    }, [open]);

    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            const target = e.target as Node;
            const inWrapper = wrapperRef.current?.contains(target);
            const inDropdown = dropdownRef.current?.contains(target);
            if (!inWrapper && !inDropdown) {
                setOpen(false);
                setQuery('');
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const handleSelect = (option: Option) => {
        onValueChange(option.value);
        setQuery('');
        setOpen(false);
        inputRef.current?.blur();
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (disabled) {
            return;
        }
        if (!open) {
            if (e.key === 'ArrowDown' || e.key === 'Enter') {
                openDropdown();
                e.preventDefault();
            }
            return;
        }

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                setHighlightedIndex((i) => Math.min(i + 1, filtered.length - 1));
                break;
            case 'ArrowUp':
                e.preventDefault();
                setHighlightedIndex((i) => Math.max(i - 1, 0));
                break;
            case 'Enter':
                e.preventDefault();
                if (filtered[highlightedIndex]) {
                    handleSelect(filtered[highlightedIndex]);
                }
                break;
            case 'Escape':
                setOpen(false);
                setQuery('');
                inputRef.current?.blur();
                break;
        }
    };

    return (
        <div ref={wrapperRef} className={cn('relative min-w-0', className)}>
            <input
                ref={inputRef}
                type="text"
                className="input input-bordered w-full"
                placeholder={selected ? selected.label : placeholder}
                value={open ? query : selected?.label ?? ''}
                disabled={disabled}
                onChange={(e) => {
                    setQuery(e.target.value);
                    setHighlightedIndex(0);
                    setOpen(true);
                    updateCoords();
                }}
                onFocus={openDropdown}
                onKeyDown={handleKeyDown}
            />
            {open && !disabled && filtered.length > 0 && coords && createPortal(
                <ul
                    ref={dropdownRef}
                    className="menu bg-base-100 border-base-300 fixed z-[1000] max-h-60 overflow-auto rounded border shadow-lg"
                    style={{ top: coords.top, left: coords.left, width: coords.width }}
                >
                    {filtered.map((option, idx) => (
                        <li key={option.value}>
                            <button
                                type="button"
                                className={cn(
                                    'w-full text-left',
                                    idx === highlightedIndex && 'active',
                                    option.value === value && 'font-semibold',
                                    option.danger && 'text-error',
                                )}
                                onMouseEnter={() => setHighlightedIndex(idx)}
                                onClick={() => handleSelect(option)}
                            >
                                {option.label}
                            </button>
                        </li>
                    ))}
                </ul>,
                document.body,
            )}
        </div>
    );
}
