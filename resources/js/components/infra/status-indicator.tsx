import type { InfraEstadoIndicador } from '@/types/models';

type Props = {
    indicador: InfraEstadoIndicador;
};

const colorMap = {
    true: 'bg-success',
    false: 'bg-error',
    null: 'bg-base-content/20',
} as const;

export default function StatusIndicator({ indicador }: Props) {
    const key = String(indicador.estado) as keyof typeof colorMap;

    return (
        <div className="tooltip" data-tip={indicador.tooltip}>
            <div className="flex items-center gap-1.5">
                <span className={`inline-block size-3 rounded-full ${colorMap[key]}`} />
                <span className="text-sm">{indicador.label}</span>
            </div>
        </div>
    );
}
