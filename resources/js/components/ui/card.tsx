import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

function Card({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            className={cn('card bg-base-100 shadow-sm', className)}
            {...props}
        />
    );
}

function CardHeader({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            className={cn('card-body pb-0', className)}
            {...props}
        />
    );
}

function CardTitle({ className, ...props }: ComponentProps<'h2'>) {
    return (
        <h2
            className={cn('card-title', className)}
            {...props}
        />
    );
}

function CardDescription({ className, ...props }: ComponentProps<'p'>) {
    return (
        <p
            className={cn('text-base-content/70 text-sm', className)}
            {...props}
        />
    );
}

function CardContent({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            className={cn('card-body pt-4', className)}
            {...props}
        />
    );
}

function CardFooter({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            className={cn('card-actions justify-end px-6 pb-6', className)}
            {...props}
        />
    );
}

export { Card, CardHeader, CardFooter, CardTitle, CardDescription, CardContent };
