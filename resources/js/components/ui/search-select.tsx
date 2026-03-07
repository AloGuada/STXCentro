import { cn } from '@/lib/utils';
import { useEffect, useRef, useState } from 'react';

type Option = {
    value: string;
    label: string;
};

type SearchSelectProps = {
    options: Option[];
    value?: string;
    onValueChange: (value: string) => void;
    placeholder?: string;
    className?: string;
};

export function SearchSelect({
    options,
    value,
    onValueChange,
    placeholder = 'Buscar...',
    className,
}: SearchSelectProps) {
    const selected = options.find((o) => o.value === value);
    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(false);
    const [highlightedIndex, setHighlightedIndex] = useState(0);
    const wrapperRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    const filtered = options.filter((o) =>
        o.label.toLowerCase().includes(query.toLowerCase()),
    );

    useEffect(() => {
        setHighlightedIndex(0);
    }, [query]);

    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (wrapperRef.current && !wrapperRef.current.contains(e.target as Node)) {
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
                onChange={(e) => {
                    setQuery(e.target.value);
                    setOpen(true);
                }}
                onFocus={() => {
                    setQuery('');
                    setOpen(true);
                }}
                onKeyDown={handleKeyDown}
            />
            {open && filtered.length > 0 && (
                <ul className="menu bg-base-100 border-base-300 absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded border shadow-lg">
                    {filtered.map((option, idx) => (
                        <li key={option.value}>
                            <button
                                type="button"
                                className={cn(
                                    'w-full text-left',
                                    idx === highlightedIndex && 'active',
                                    option.value === value && 'font-semibold',
                                )}
                                onMouseEnter={() => setHighlightedIndex(idx)}
                                onClick={() => handleSelect(option)}
                            >
                                {option.label}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
