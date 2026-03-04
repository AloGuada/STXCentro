import { cn } from '@/lib/utils';
import { useEffect, useRef, useState } from 'react';

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
    const wrapperRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    const filtered = options.filter((o) =>
        o.label.toLowerCase().includes(query.toLowerCase()),
    );

    const exactMatch = options.some(
        (o) => o.label.toLowerCase() === query.trim().toLowerCase(),
    );
    const showCreate = query.trim().length > 0 && !exactMatch;

    const totalItems = filtered.length + (showCreate ? 1 : 0);

    useEffect(() => {
        setHighlightedIndex(0);
    }, [query]);

    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (wrapperRef.current && !wrapperRef.current.contains(e.target as Node)) {
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
                setOpen(true);
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
                    setOpen(true);
                }}
                onFocus={() => setOpen(true)}
                onKeyDown={handleKeyDown}
            />
            {open && totalItems > 0 && (
                <ul className="menu bg-base-100 border-base-300 absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded border shadow-lg">
                    {filtered.map((option, idx) => (
                        <li key={option.value}>
                            <button
                                type="button"
                                className={cn(
                                    'w-full text-left',
                                    idx === highlightedIndex && 'active',
                                )}
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
                </ul>
            )}
        </div>
    );
}
