import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { cn } from '@/lib/utils';

type Option = {
    value: string;
    label: string;
};

type CreatableComboboxProps = {
    options: Option[];
    placeholder?: string;
    creatableLabel?: string;
    onSelect: (option: Option) => void;
    onCreate: (text: string) => void;
    className?: string;
};

export function CreatableCombobox({
    options,
    placeholder = 'Buscar...',
    creatableLabel = 'Crear',
    onSelect,
    onCreate,
    className,
}: CreatableComboboxProps) {
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

    const exactMatch = options.some(
        (o) => o.label.toLowerCase() === query.trim().toLowerCase(),
    );
    const showCreate = query.trim().length > 0 && !exactMatch;

    const totalItems = filtered.length + (showCreate ? 1 : 0);

    // Posiciona el dropdown (portal) bajo el input para que no lo recorte ningún
    // contenedor con overflow; se realinea al hacer scroll/resize.
    const updateCoords = () => {
        const rect = inputRef.current?.getBoundingClientRect();
        if (rect) {
            setCoords({ top: rect.bottom, left: rect.left, width: rect.width });
        }
    };

    const openDropdown = () => {
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
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const handleSelect = (option: Option) => {
        onSelect(option);
        setQuery('');
        setOpen(false);
        inputRef.current?.blur();
    };

    const handleCreate = () => {
        if (!query.trim()) return;
        onCreate(query.trim());
        setQuery('');
        setOpen(false);
        inputRef.current?.blur();
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
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
                setHighlightedIndex((i) => Math.min(i + 1, totalItems - 1));
                break;
            case 'ArrowUp':
                e.preventDefault();
                setHighlightedIndex((i) => Math.max(i - 1, 0));
                break;
            case 'Enter':
                e.preventDefault();
                if (highlightedIndex < filtered.length) {
                    handleSelect(filtered[highlightedIndex]);
                } else if (showCreate) {
                    handleCreate();
                }
                break;
            case 'Escape':
                setOpen(false);
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
                placeholder={placeholder}
                value={query}
                onChange={(e) => {
                    setQuery(e.target.value);
                    setHighlightedIndex(0);
                    openDropdown();
                }}
                onFocus={openDropdown}
                onKeyDown={handleKeyDown}
            />
            {open && totalItems > 0 && coords && createPortal(
                <ul
                    ref={dropdownRef}
                    className="menu bg-base-100 border-base-300 fixed z-[100] max-h-60 overflow-auto rounded border shadow-lg"
                    style={{ top: coords.top, left: coords.left, width: coords.width }}
                >
                    {filtered.map((option, idx) => (
                        <li key={option.value}>
                            <button
                                type="button"
                                className={cn('w-full text-left', idx === highlightedIndex && 'active')}
                                onMouseEnter={() => setHighlightedIndex(idx)}
                                onClick={() => handleSelect(option)}
                            >
                                {option.label}
                            </button>
                        </li>
                    ))}
                    {showCreate && (
                        <li>
                            <button
                                type="button"
                                className={cn(
                                    'text-primary w-full text-left font-medium',
                                    highlightedIndex === filtered.length && 'active',
                                )}
                                onMouseEnter={() => setHighlightedIndex(filtered.length)}
                                onClick={handleCreate}
                            >
                                + {creatableLabel} "{query.trim()}"
                            </button>
                        </li>
                    )}
                </ul>,
                document.body,
            )}
        </div>
    );
}
