import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { SearchIcon, XIcon } from 'lucide-react';
import { useEffect, useState } from 'react';

type SearchInputProps = {
    placeholder?: string;
    paramName?: string;
    defaultValue?: string;
    className?: string;
};

export function SearchInput({
    placeholder = 'Buscar...',
    paramName = 'search',
    defaultValue = '',
    className,
}: SearchInputProps) {
    const [value, setValue] = useState(defaultValue);

    useEffect(() => {
        const timeout = setTimeout(() => {
            router.get(
                window.location.pathname,
                { [paramName]: value || undefined },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [value, paramName]);

    return (
        <div className={cn('relative', className)}>
            <SearchIcon className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
            <Input
                type="text"
                placeholder={placeholder}
                value={value}
                onChange={(e) => setValue(e.target.value)}
                className="pl-9 pr-9"
            />
            {value && (
                <button
                    type="button"
                    onClick={() => setValue('')}
                    className="text-muted-foreground hover:text-foreground absolute top-1/2 right-3 -translate-y-1/2"
                >
                    <XIcon className="size-4" />
                </button>
            )}
        </div>
    );
}
