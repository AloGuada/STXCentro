/**
 * Las piezas visuales de la captura.
 *
 * Vienen del `captura.html` original, pero ya **no traen su paleta**: usan los
 * tokens del tema del admin (`base-100`, `base-300`, `primary`, `success`,
 * `error`, `warning`) como el resto del panel. La versión anterior fijaba 33
 * colores en duro para imitar el formato de papel, y eso la dejaba fuera del
 * tema —el modo oscuro no la alcanzaba y cualquier cambio de marca había que
 * repetirlo a mano aquí—.
 *
 * Lo que sí se conserva del original es el **tamaño**: botones grandes, radios
 * convertidos en botonera y contadores con botonazos. Eso no es estilo, es que
 * la pantalla se usa de pie, en una tablet y con guantes.
 */

import type { ChangeEvent, ReactNode } from 'react';
import { cn } from '@/lib/utils';

/** El control de texto del panel, para que la captura no invente el suyo. */
const CAMPO = 'input input-bordered w-full text-base';

/** Botón de la botonera cuando no está elegido. Es el estado por defecto. */
const APAGADO = 'border-base-300 bg-base-100 text-base-content hover:border-primary/40';

/** Los tres veredictos, en su versión suave (fondo tenue, texto del color). */
const VEREDICTOS = {
    lib: 'border-success bg-success/10 text-success',
    rej: 'border-error bg-error/10 text-error',
    pen: 'border-warning bg-warning/10 text-warning',
} as const;

/** Bloque del formulario. El título va en versalitas, como el formato de papel. */
export function Tarjeta({ titulo, etiqueta, children }: { titulo: string; etiqueta?: string; children: ReactNode }) {
    return (
        <section className="mb-3.5 rounded-box border border-base-300 bg-base-100 p-4 shadow-sm">
            <h2 className="mb-3 flex items-center justify-between border-b border-base-300 pb-2 text-sm tracking-wider text-primary uppercase">
                <span className="font-semibold">{titulo}</span>
                {etiqueta && <span className="badge badge-sm badge-warning">{etiqueta}</span>}
            </h2>
            {children}
        </section>
    );
}

/** Texto de apoyo. En este formulario explica el porqué, no el cómo. */
export function Pista({ children, className }: { children: ReactNode; className?: string }) {
    return <p className={cn('text-base-content/60 mt-1 mb-2.5 text-xs', className)}>{children}</p>;
}

export function Rejilla({ cols = 2, children }: { cols?: 2 | 3; children: ReactNode }) {
    return <div className={cn('grid gap-3', cols === 3 ? 'grid-cols-2 sm:grid-cols-3' : 'grid-cols-2')}>{children}</div>;
}

export function Campo({
    label,
    req,
    ayuda,
    children,
}: {
    label?: string;
    req?: boolean;
    ayuda?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div>
            {label && (
                <label className="mb-1.5 block text-sm font-semibold">
                    {label}
                    {req && <span className="text-error"> *</span>}
                </label>
            )}
            {children}
            {ayuda && <div className="text-base-content/60 mt-1 text-xs">{ayuda}</div>}
        </div>
    );
}

type TextoProps = {
    value: string;
    onChange?: (valor: string) => void;
    placeholder?: string;
    readOnly?: boolean;
    tipo?: 'text' | 'number' | 'date';
    paso?: string;
    className?: string;
    onFocus?: () => void;
    onBlur?: () => void;
    mayusculas?: boolean;
    /** La tablet no debe abrir su teclado: el folio tiene el suyo en pantalla. */
    sinTeclado?: boolean;
};

export function Texto({
    value,
    onChange,
    placeholder,
    readOnly,
    tipo = 'text',
    paso,
    className,
    onFocus,
    onBlur,
    mayusculas,
    sinTeclado,
}: TextoProps) {
    return (
        <input
            type={tipo}
            step={paso}
            value={value}
            readOnly={readOnly}
            placeholder={placeholder}
            onFocus={onFocus}
            onBlur={onBlur}
            onClick={onFocus}
            inputMode={sinTeclado ? 'none' : tipo === 'number' ? 'decimal' : undefined}
            onChange={(e: ChangeEvent<HTMLInputElement>) =>
                onChange?.(mayusculas ? e.target.value.toUpperCase() : e.target.value)
            }
            className={cn(CAMPO, readOnly && 'bg-base-200 text-base-content/60', className)}
        />
    );
}

export function AreaTexto({
    value,
    onChange,
    placeholder,
    filas = 2,
}: {
    value: string;
    onChange: (valor: string) => void;
    placeholder?: string;
    filas?: number;
}) {
    return (
        <textarea
            rows={filas}
            value={value}
            placeholder={placeholder}
            onChange={(e) => onChange(e.target.value)}
            className="textarea textarea-bordered w-full text-base"
        />
    );
}

/**
 * Desplegable. `opciones` admite el par [valor, texto] para los catálogos que
 * guardan clave (soldadores, tipos de pieza).
 */
export function Selector({
    value,
    onChange,
    opciones,
    vacio = '—',
}: {
    value: string;
    onChange: (valor: string) => void;
    opciones: (string | [string, string])[];
    vacio?: string | null;
}) {
    return (
        <select
            value={value}
            onChange={(e) => onChange(e.target.value)}
            className="select select-bordered w-full text-base"
        >
            {vacio !== null && <option value="">{vacio}</option>}
            {opciones.map((opcion) => {
                const [valor, texto] = Array.isArray(opcion) ? opcion : [opcion, opcion];
                return (
                    <option key={valor} value={valor}>
                        {texto}
                    </option>
                );
            })}
        </select>
    );
}

/** Las tres respuestas que se repiten en casi todo el formulario. */
export const OK_DEFECTO = ['OK', 'Con defecto', 'n/a'];
export const OK_TOLERANCIA = ['OK', 'Fuera de tol.'];

/** Elección de transformación: manda sobre el formulario entero. */
export function SelectorFase({
    value,
    onChange,
    opciones,
}: {
    value: string;
    onChange: (valor: string) => void;
    opciones: { valor: string; titulo: string; nota: string }[];
}) {
    return (
        <div className="mt-1 grid grid-cols-3 gap-2.5">
            {opciones.map((opcion) => {
                const activo = value === opcion.valor;
                return (
                    <button
                        key={opcion.valor}
                        type="button"
                        onClick={() => onChange(opcion.valor)}
                        className={cn(
                            'rounded-box border-2 px-2 py-4 text-center text-[15px] leading-tight font-bold transition',
                            activo ? 'border-primary bg-primary text-primary-content' : APAGADO,
                        )}
                    >
                        {opcion.titulo}
                        <small
                            className={cn(
                                'mt-0.5 block text-[11px] font-medium',
                                activo ? 'text-primary-content/70' : 'text-base-content/60',
                            )}
                        >
                            {opcion.nota}
                        </small>
                    </button>
                );
            })}
        </div>
    );
}

type TonoSeg = 'acero' | 'estatus';

/** Botonera de una sola elección. */
export function Segmentado({
    value,
    onChange,
    opciones,
    tono = 'acero',
}: {
    value: string;
    onChange: (valor: string) => void;
    opciones: { valor: string; texto: string; color?: 'lib' | 'rej' | 'pen' }[];
    tono?: TonoSeg;
}) {
    return (
        <div className="mt-1 flex flex-nowrap gap-2">
            {opciones.map((opcion) => {
                const activo = value === opcion.valor;
                const activoClase =
                    tono === 'estatus' && opcion.color
                        ? VEREDICTOS[opcion.color]
                        : 'border-primary bg-primary text-primary-content';
                return (
                    <button
                        key={opcion.valor}
                        type="button"
                        onClick={() => onChange(opcion.valor)}
                        className={cn(
                            'rounded-box flex min-w-0 flex-1 items-center justify-center border-2 px-2 py-4 text-center text-base font-bold transition',
                            activo ? activoClase : APAGADO,
                        )}
                    >
                        {opcion.texto}
                    </button>
                );
            })}
        </div>
    );
}

/** Sí / no de dos botones, con el color del veredicto. */
export function SiNo({
    value,
    onChange,
    si,
    no,
}: {
    value: string;
    onChange: (valor: string) => void;
    si: { valor: string; texto: string };
    no: { valor: string; texto: string };
}) {
    return (
        <div className="mt-1 grid grid-cols-2 gap-2">
            {[si, no].map((opcion, indice) => {
                const activo = value === opcion.valor;
                return (
                    <button
                        key={opcion.valor}
                        type="button"
                        onClick={() => onChange(activo ? '' : opcion.valor)}
                        className={cn(
                            'rounded-box flex items-center justify-center border-2 p-3 font-bold transition',
                            activo ? (indice === 0 ? VEREDICTOS.lib : VEREDICTOS.rej) : APAGADO,
                        )}
                    >
                        {opcion.texto}
                    </button>
                );
            })}
        </div>
    );
}

/** Contador con botonazos: se usa de pie y con guantes, no se teclea. */
export function Contador({ value, onChange }: { value: string; onChange: (valor: string) => void }) {
    const mover = (delta: number) => {
        const actual = parseInt(value, 10);
        onChange(String(Math.max(0, (Number.isNaN(actual) ? 0 : actual) + delta)));
    };

    return (
        <div className="mt-1 flex items-stretch gap-2">
            <button type="button" onClick={() => mover(-1)} className="btn btn-outline w-12 px-0 text-xl font-extrabold">
                −
            </button>
            <input
                type="number"
                inputMode="numeric"
                min={0}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className={cn(CAMPO, 'min-w-0 flex-1 text-center font-extrabold')}
            />
            <button type="button" onClick={() => mover(1)} className="btn btn-outline w-12 px-0 text-xl font-extrabold">
                +
            </button>
        </div>
    );
}

/** Etiquetas de selección múltiple. */
export function Chips({
    opciones,
    valor,
    onChange,
}: {
    opciones: string[];
    valor: string[];
    onChange: (valor: string[]) => void;
}) {
    return (
        <div className="mt-1 flex flex-wrap gap-2">
            {opciones.map((opcion) => {
                const activo = valor.includes(opcion);
                return (
                    <button
                        key={opcion}
                        type="button"
                        onClick={() => onChange(activo ? valor.filter((x) => x !== opcion) : [...valor, opcion])}
                        className={cn(
                            'rounded-full border-2 px-3.5 py-2 text-sm font-semibold transition',
                            activo ? 'border-primary bg-primary text-primary-content' : APAGADO,
                        )}
                    >
                        {opcion}
                    </button>
                );
            })}
        </div>
    );
}

/**
 * Etiquetas con cantidad: un defecto no es sí/no, es cuántos. El contador sólo
 * aparece cuando la etiqueta está marcada, para no llenar la pantalla de ceros.
 */
export function ChipsContados({
    opciones,
    valor,
    onChange,
}: {
    opciones: string[];
    valor: Record<string, number>;
    onChange: (valor: Record<string, number>) => void;
}) {
    const alternar = (opcion: string) => {
        const copia = { ...valor };
        if (opcion in copia) {
            delete copia[opcion];
        } else {
            copia[opcion] = 1;
        }
        onChange(copia);
    };

    const contar = (opcion: string, cantidad: number) => {
        onChange({ ...valor, [opcion]: Math.max(0, cantidad) });
    };

    return (
        <div className="mt-1 flex flex-wrap gap-2">
            {opciones.map((opcion) => {
                const activo = opcion in valor;
                return (
                    <div key={opcion} className="flex items-center gap-1.5">
                        <button
                            type="button"
                            onClick={() => alternar(opcion)}
                            className={cn(
                                'rounded-full border-2 px-3.5 py-2 text-sm font-semibold transition',
                                activo ? 'border-primary bg-primary text-primary-content' : APAGADO,
                            )}
                        >
                            {opcion}
                        </button>
                        {activo && (
                            <div className="flex gap-1">
                                <button
                                    type="button"
                                    onClick={() => contar(opcion, valor[opcion] - 1)}
                                    className="btn btn-outline btn-primary btn-xs h-7 w-9 px-0 text-lg font-extrabold"
                                >
                                    −
                                </button>
                                <input
                                    type="number"
                                    inputMode="numeric"
                                    value={valor[opcion]}
                                    onChange={(e) => contar(opcion, parseInt(e.target.value, 10) || 0)}
                                    className="input input-bordered input-sm w-12 px-0.5 text-center text-base font-bold"
                                />
                                <button
                                    type="button"
                                    onClick={() => contar(opcion, valor[opcion] + 1)}
                                    className="btn btn-outline btn-primary btn-xs h-7 w-9 px-0 text-lg font-extrabold"
                                >
                                    +
                                </button>
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

/** Botón secundario del formulario (abrir panel, añadir a lista…). */
export function Boton({
    children,
    onClick,
    tono = 'acero',
    disabled,
    className,
}: {
    children: ReactNode;
    onClick: () => void;
    tono?: 'acero' | 'claro' | 'ok' | 'rechazo';
    disabled?: boolean;
    className?: string;
}) {
    const tonos = {
        acero: 'btn-primary',
        claro: 'btn-ghost border border-base-300',
        ok: 'btn-success',
        rechazo: 'btn-error',
    };

    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            className={cn('btn text-base font-bold', disabled ? 'btn-disabled' : tonos[tono], className)}
        >
            {children}
        </button>
    );
}

/** Barra de progreso del muestreo. */
export function Progreso({ hechas, total }: { hechas: number; total: number }) {
    const porcentaje = total > 0 ? Math.min(100, Math.round((hechas / total) * 100)) : 0;

    return <progress className="progress progress-primary mt-1.5 h-2" value={porcentaje} max={100} />;
}

/** Caja del veredicto: verde acepta, rojo rechaza, ámbar todavía no decide. */
export function CajaVeredicto({ texto, rechazado, cerrado }: { texto: string; rechazado: boolean; cerrado: boolean }) {
    const clase = rechazado ? 'bg-error/10 text-error' : cerrado ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning';

    return <div className={cn('rounded-box mt-3 px-3.5 py-3 text-center text-[15px] font-extrabold', clase)}>{texto}</div>;
}

export function Pastilla({ children, tono }: { children: ReactNode; tono: 'lib' | 'rej' | 'pen' }) {
    const tonos = {
        lib: 'bg-success/10 text-success',
        rej: 'bg-error/10 text-error',
        pen: 'bg-warning/10 text-warning',
    };

    return <span className={cn('rounded-full px-2.5 py-0.5 text-xs font-bold', tonos[tono])}>{children}</span>;
}
