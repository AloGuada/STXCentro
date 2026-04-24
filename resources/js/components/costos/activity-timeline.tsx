import type { CostosActivity } from '@/types/models';
import { ActivityIcon } from 'lucide-react';

type Props = {
    activities: CostosActivity[];
};

const EVENT_LABELS: Record<string, string> = {
    created: 'Creación',
    updated: 'Actualización',
    deleted: 'Eliminación',
};

function formatValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '—';
    }
    if (typeof value === 'boolean') {
        return value ? 'Sí' : 'No';
    }
    if (typeof value === 'number') {
        return value.toLocaleString('es-MX');
    }
    return String(value);
}

export function ActivityTimeline({ activities }: Props) {
    if (!activities?.length) {
        return <p className="text-sm text-base-content/60">Aún no hay actividad registrada.</p>;
    }

    const ordenadas = [...activities].sort(
        (a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime(),
    );

    return (
        <ul className="timeline timeline-vertical timeline-compact">
            {ordenadas.map((activity, idx) => {
                const attrs = activity.attribute_changes?.attributes ?? {};
                const olds = activity.attribute_changes?.old ?? {};
                const keys = Object.keys(attrs);

                return (
                    <li key={activity.id}>
                        {idx > 0 && <hr />}
                        <div className="timeline-middle">
                            <ActivityIcon className="size-4 text-primary" />
                        </div>
                        <div className="timeline-end timeline-box">
                            <div className="flex items-baseline justify-between gap-3">
                                <span className="text-sm font-medium">
                                    {EVENT_LABELS[activity.event ?? ''] ?? activity.event ?? 'Evento'}
                                </span>
                                <span className="text-xs text-base-content/60">
                                    {new Date(activity.created_at).toLocaleString('es-MX', {
                                        dateStyle: 'short',
                                        timeStyle: 'short',
                                    })}
                                </span>
                            </div>
                            {activity.causer && (
                                <p className="text-xs text-base-content/70">
                                    Por <span className="font-medium">{activity.causer.name}</span>
                                </p>
                            )}
                            {keys.length > 0 && (
                                <dl className="mt-2 text-xs space-y-0.5">
                                    {keys.map((key) => (
                                        <div key={key} className="flex gap-2">
                                            <dt className="font-medium text-base-content/70">{key}:</dt>
                                            <dd className="text-base-content/80">
                                                {olds[key] !== undefined && (
                                                    <>
                                                        <span className="line-through text-base-content/50">
                                                            {formatValue(olds[key])}
                                                        </span>{' '}
                                                        →{' '}
                                                    </>
                                                )}
                                                <span>{formatValue(attrs[key])}</span>
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                            )}
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}
