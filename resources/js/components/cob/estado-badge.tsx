import {
    COB_ESTIMACION_ESTADO_COLORS,
    COB_ESTIMACION_ESTADO_LABELS,
    type CobEstimacionEstado,
} from '@/types/models';

type Props = {
    estado: CobEstimacionEstado;
};

export function EstadoBadge({ estado }: Props) {
    return (
        <span className={`badge badge-sm ${COB_ESTIMACION_ESTADO_COLORS[estado] ?? ''}`}>
            {COB_ESTIMACION_ESTADO_LABELS[estado] ?? estado}
        </span>
    );
}
