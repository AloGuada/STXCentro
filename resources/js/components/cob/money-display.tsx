export function formatearMXN(valor: number | null | undefined): string {
    if (valor === null || valor === undefined) {
        return '$0.00';
    }

    return Number(valor).toLocaleString('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

type Props = {
    valor: number | null | undefined;
    className?: string;
};

export function MoneyDisplay({ valor, className }: Props) {
    return <span className={className}>{formatearMXN(valor)}</span>;
}
