import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

type InputProps = ComponentProps<'input'> & {
    error?: boolean;
};

function Input({ className, error, ...props }: InputProps) {
    return (
        <input
            className={cn(
                'input input-bordered w-full',
                error && 'input-error',
                className,
            )}
            {...props}
        />
    );
}

export { Input };
