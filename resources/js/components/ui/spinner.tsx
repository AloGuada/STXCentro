import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

type SpinnerSize = 'xs' | 'sm' | 'md' | 'lg';

type SpinnerProps = ComponentProps<'span'> & {
    size?: SpinnerSize;
};

function Spinner({ className, size = 'md', ...props }: SpinnerProps) {
    return (
        <span
            role="status"
            aria-label="Loading"
            className={cn(
                'loading loading-spinner',
                size && `loading-${size}`,
                className,
            )}
            {...props}
        />
    );
}

export { Spinner };
