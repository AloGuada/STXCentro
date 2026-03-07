type FormattedDateProps = {
    value: string | null | undefined;
    format?: 'short' | 'medium' | 'long';
    fallback?: string;
};

const formatters: Record<string, Intl.DateTimeFormat> = {
    short: new Intl.DateTimeFormat('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' }),
    medium: new Intl.DateTimeFormat('es-MX', { day: 'numeric', month: 'short', year: 'numeric' }),
    long: new Intl.DateTimeFormat('es-MX', { day: 'numeric', month: 'long', year: 'numeric' }),
};

export function formatDate(value: string | null | undefined, format: 'short' | 'medium' | 'long' = 'short'): string | null {
    if (!value) return null;
    const date = new Date(value + (value.length === 10 ? 'T12:00:00' : ''));
    if (isNaN(date.getTime())) return null;
    return formatters[format].format(date);
}

export function FormattedDate({ value, format = 'short', fallback = '-' }: FormattedDateProps) {
    const formatted = formatDate(value, format);
    return <>{formatted ?? fallback}</>;
}
