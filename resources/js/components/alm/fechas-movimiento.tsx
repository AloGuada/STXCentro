import { FormField } from '@/components/form';
import { Input } from '@/components/ui/input';
import { HOY_DEMO } from '@/lib/alm/demo';

type Props = {
    fecha: string;
    onChange: (fecha: string) => void;
    /** Cómo se llama la fecha en este documento: "Salida", "Fecha del conteo"... */
    label?: string;
    /** Prefijo de los ids, por si la pantalla lleva más de una fecha. */
    id?: string;
    descripcion?: string;
};

/**
 * Las dos fechas que lleva todo movimiento.
 *
 * Se separan porque no son la misma: el material se recibe el viernes y se
 * captura el lunes. La de registro la sella el sistema y no se toca —es la que
 * deja rastro de cuándo se hizo el asiento—; la del movimiento la elige el
 * almacenista y puede ir hacia atrás, para que el kardex quede en el día en que
 * de verdad pasó. Hacia adelante no: nada se mueve antes de que ocurra.
 */
export function FechasMovimiento({
    fecha,
    onChange,
    label = 'Fecha del movimiento',
    id = 'fecha',
    descripcion,
}: Props) {
    return (
        <>
            <FormField
                label={label}
                htmlFor={id}
                description={descripcion ?? 'Cuándo pasó. Puede ser antes de hoy, nunca después.'}
                required
            >
                <Input
                    id={id}
                    type="date"
                    value={fecha}
                    max={HOY_DEMO}
                    onChange={(e) => onChange(e.target.value)}
                />
            </FormField>

            <FormField
                label="Registrado"
                htmlFor={`${id}_registro`}
                description="Lo sella el sistema al guardar; no se edita."
            >
                <Input id={`${id}_registro`} type="date" value={HOY_DEMO} readOnly disabled />
            </FormField>
        </>
    );
}
