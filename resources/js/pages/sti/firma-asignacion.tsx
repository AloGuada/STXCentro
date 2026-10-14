import { Head, useForm } from '@inertiajs/react';
import {
    CheckCircleIcon,
    FileTextIcon,
    Loader2Icon,
    XIcon,
} from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { SignaturePad } from '@/components/sti/signature-pad';
import { Button } from '@/components/ui/button';

const ANCHO_FIRMA = 380;
const ALTO_FIRMA = 150;

type Props = {
    token: string;
    imagenes: { id: number; descripcion: string | null; url: string }[];
    asignacion: {
        id: number;
        empleado: string;
        no_empleado: string;
        departamento: string | null;
        equipo: string | null;
        serie: string | null;
        nombre_ti: string;
        fecha_inicial: string;
        firmada: boolean;
    };
};

// El lienzo se dibuja a la densidad de la pantalla; en un celular la imagen sale
// varias veces mas grande que en escritorio. Se reexporta a un tamano fijo para
// que la firma pese lo mismo sin importar el dispositivo.
function normalizarFirma(dataUrl: string): Promise<string> {
    return new Promise((resolve, reject) => {
        const imagen = new Image();
        imagen.onload = () => {
            const lienzo = document.createElement('canvas');
            lienzo.width = ANCHO_FIRMA;
            lienzo.height = ALTO_FIRMA;
            const contexto = lienzo.getContext('2d');
            if (!contexto) {
                reject(new Error('No se pudo procesar la firma.'));
                return;
            }
            contexto.fillStyle = 'rgb(255, 255, 255)';
            contexto.fillRect(0, 0, ANCHO_FIRMA, ALTO_FIRMA);
            contexto.drawImage(imagen, 0, 0, ANCHO_FIRMA, ALTO_FIRMA);
            resolve(lienzo.toDataURL('image/png'));
        };
        imagen.onerror = () =>
            reject(new Error('No se pudo procesar la firma.'));
        imagen.src = dataUrl;
    });
}

export default function FirmaAsignacion({
    token,
    imagenes,
    asignacion,
}: Props) {
    const [anchoLienzo] = useState(() =>
        typeof window === 'undefined'
            ? ANCHO_FIRMA
            : Math.min(ANCHO_FIRMA, window.innerWidth - 96),
    );
    const altoLienzo = Math.round((anchoLienzo * ALTO_FIRMA) / ANCHO_FIRMA);
    const [imagenAbierta, setImagenAbierta] = useState<
        Props['imagenes'][number] | null
    >(null);

    useEffect(() => {
        if (!imagenAbierta) return;

        const cerrarConEscape = (e: KeyboardEvent) => {
            if (e.key === 'Escape') setImagenAbierta(null);
        };
        window.addEventListener('keydown', cerrarConEscape);

        return () => window.removeEventListener('keydown', cerrarConEscape);
    }, [imagenAbierta]);

    const { data, setData, post, processing, errors, transform, setError } =
        useForm<{ firma_empleado: string | null }>({
            firma_empleado: null,
        });

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        if (!data.firma_empleado) return;

        try {
            const firma = await normalizarFirma(data.firma_empleado);
            transform(() => ({ firma_empleado: firma }));
            post(`/sti/firmas/${token}`, { preserveScroll: true });
        } catch {
            setError(
                'firma_empleado',
                'No se pudo procesar la firma. Borrala e intenta de nuevo.',
            );
        }
    };

    return (
        <div className="min-h-screen bg-base-200">
            <Head title="Firma de responsiva - Soporte TI" />

            <div className="mx-auto w-full max-w-2xl p-4 md:p-10">
                <div className="card bg-base-100 shadow">
                    <div className="card-body">
                        <h1 className="card-title">Responsiva de equipo</h1>
                        <p className="text-sm text-gray-500">
                            Asignacion #{asignacion.id}
                        </p>

                        <div className="divider my-2" />

                        <div className="grid gap-4 text-sm md:grid-cols-2">
                            <div>
                                <span className="font-medium text-gray-500">
                                    Empleado:
                                </span>
                                <p>
                                    {asignacion.empleado} (#
                                    {asignacion.no_empleado})
                                </p>
                            </div>
                            <div>
                                <span className="font-medium text-gray-500">
                                    Departamento:
                                </span>
                                <p>{asignacion.departamento ?? '-'}</p>
                            </div>
                            <div>
                                <span className="font-medium text-gray-500">
                                    Equipo:
                                </span>
                                <p>
                                    {asignacion.equipo ?? '-'}{' '}
                                    {asignacion.serie
                                        ? `(${asignacion.serie})`
                                        : ''}
                                </p>
                            </div>
                            <div>
                                <span className="font-medium text-gray-500">
                                    Fecha de asignacion:
                                </span>
                                <p>
                                    {new Date(
                                        asignacion.fecha_inicial + 'T00:00:00',
                                    ).toLocaleDateString('es-MX')}
                                </p>
                            </div>
                            <div>
                                <span className="font-medium text-gray-500">
                                    Entrega (Soporte TI):
                                </span>
                                <p>{asignacion.nombre_ti}</p>
                            </div>
                        </div>

                        <div className="mt-4 space-y-3">
                            <p className="text-sm leading-relaxed">
                                Por medio de la presente, hago constar que he
                                recibido un equipo para el desarrollo de mis
                                funciones y actividades laborales, en la
                                empresa, TIM DEL MAYAB S.A. DE C.V., con nombre
                                comercial STEELEX.
                            </p>
                            <p className="text-sm leading-relaxed">
                                Yo asumo la responsabilidad y el cuidado de
                                dicho equipo y me comprometo a utilizarlo
                                estrictamente para uso de las labores en el área
                                de Construcción. Aquellos daños que sean
                                causados por una mala práctica, instalación de
                                software no autorizado o imprudencia mía,
                                asumiré las repercusiones que de ello se deriven
                                y los gastos que así se produzcan por lo antes
                                descrito.
                            </p>
                        </div>

                        {imagenes.length > 0 && (
                            <div className="mt-4">
                                <p className="mb-2 text-sm font-medium">
                                    Equipo entregado
                                </p>
                                <div className="grid grid-cols-2 gap-3 md:grid-cols-3">
                                    {imagenes.map((imagen) => (
                                        <button
                                            key={imagen.id}
                                            type="button"
                                            onClick={() =>
                                                setImagenAbierta(imagen)
                                            }
                                            className="block cursor-pointer text-left"
                                        >
                                            <img
                                                src={imagen.url}
                                                alt={
                                                    imagen.descripcion ??
                                                    'Equipo entregado'
                                                }
                                                loading="lazy"
                                                className="aspect-square w-full rounded-lg border border-base-300 object-cover"
                                            />
                                            {imagen.descripcion && (
                                                <span className="mt-1 block truncate text-xs text-gray-500">
                                                    {imagen.descripcion}
                                                </span>
                                            )}
                                        </button>
                                    ))}
                                </div>
                                <p className="mt-1 text-xs text-gray-500">
                                    Toca una imagen para verla completa.
                                </p>
                            </div>
                        )}

                        <div className="divider my-2" />

                        {asignacion.firmada ? (
                            <div className="space-y-4">
                                <div className="alert alert-success">
                                    <CheckCircleIcon className="size-5" />
                                    <span>
                                        Tu firma fue registrada. Ya puedes
                                        cerrar esta pagina.
                                    </span>
                                </div>
                                <Button variant="outline" asChild>
                                    <a href={`/sti/firmas/${token}/pdf`}>
                                        <FileTextIcon className="size-4" />
                                        Descargar responsiva (PDF)
                                    </a>
                                </Button>
                            </div>
                        ) : (
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div>
                                    <p className="mb-2 text-sm font-medium">
                                        Firma del empleado
                                    </p>
                                    <SignaturePad
                                        value={data.firma_empleado}
                                        onChange={(value) =>
                                            setData('firma_empleado', value)
                                        }
                                        width={anchoLienzo}
                                        height={altoLienzo}
                                    />
                                    <p className="mt-1 text-xs text-gray-500">
                                        Dibuja tu firma dentro del recuadro.
                                    </p>
                                    {errors.firma_empleado && (
                                        <div
                                            className="mt-2 alert text-sm alert-error"
                                            role="alert"
                                        >
                                            {errors.firma_empleado}
                                        </div>
                                    )}
                                </div>

                                <Button
                                    type="submit"
                                    disabled={
                                        processing || !data.firma_empleado
                                    }
                                >
                                    {processing && (
                                        <Loader2Icon className="size-4 animate-spin" />
                                    )}
                                    Firmar responsiva
                                </Button>
                            </form>
                        )}
                    </div>
                </div>
            </div>

            {imagenAbierta && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
                    role="dialog"
                    aria-modal="true"
                    onClick={() => setImagenAbierta(null)}
                >
                    <div
                        className="relative"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <img
                            src={imagenAbierta.url}
                            alt={
                                imagenAbierta.descripcion ?? 'Equipo entregado'
                            }
                            className="max-h-[calc(100vh-2rem)] max-w-full rounded-lg object-contain"
                        />
                        <button
                            type="button"
                            aria-label="Cerrar imagen"
                            className="btn absolute top-2 right-2 btn-circle shadow-lg btn-lg"
                            onClick={() => setImagenAbierta(null)}
                        >
                            <XIcon className="size-8" />
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
