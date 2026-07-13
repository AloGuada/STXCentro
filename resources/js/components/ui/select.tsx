import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

type SelectProps = Omit<ComponentProps<'select'>, 'value' | 'onChange'> & {
    value?: string;
    onValueChange?: (value: string) => void;
    placeholder?: string;
    error?: boolean;
};

function Select({
    className,
    value,
    onValueChange,
    placeholder,
    error,
    children,
    ...props
}: SelectProps) {
    return (
        <select
            className={cn(
                'select select-bordered w-full',
                error && 'select-error',
                className,
            )}
            value={value}
            onChange={(e) => onValueChange?.(e.target.value)}
            {...props}
        >
            {placeholder && (
                <option value="" disabled>
                    {placeholder}
                </option>
            )}
            {children}
        </select>
    );
}

type SelectItemProps = ComponentProps<'option'>;

function SelectItem({ children, ...props }: SelectItemProps) {
    return <option {...props}>{children}</option>;
}

export { Select, SelectItem };
