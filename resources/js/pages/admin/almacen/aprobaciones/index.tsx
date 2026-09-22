import { Head, useForm } from '@inertiajs/react';
import { InfoIcon, PlusIcon, TrashIcon } from 'lucide-react';
import { useState } from 'react';
import { UsuariosMultiselect } from '@/components/alm/usuarios-multiselect';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { SearchSelect } from '@/components/ui/search-select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { AlmDocumentoFirmable, AlmDocumentoTipo, AlmFirmaDocumento, AlmUsuarioOpcion } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Aprobaciones', href: '/admin/almacen/aprobaciones' },
];

type Almacen = { id: number; clave: string; nombre: string };

/** Lo guardado: almacén → documento → sus renglones en orden. */
type Configuradas = Record<string, Record<string, AlmFirmaDocumento[]>>;

type Props = {
    almacenes: Almacen[];
    documentos: AlmDocumentoFirmable[];
    configuradas: Configuradas;
    usuarios: AlmUsuarioOpcion[];
    puedeConfigurar: boolean;
};

/** Máximos que el backend también valida; aquí sólo evitan llegar a un error. */
const MAX_FIRMAS = 6;
const MAX_USUARIOS = 5;

export default function AprobacionesIndex({ almacenes, documentos, configuradas, usuarios, puedeConfigurar }: Props) {
    const [almacenId, setAlmacenId] = useState(almacenes.length > 0 ? String(almacenes[0].id) : '');

    if (almacenes.length === 0) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Aprobaciones" />
                <div className="p-6">
                    <h1 className="text-2xl font-semibold">Firmas de los formatos</h1>
                    <div className="alert alert-warning mt-4">
                        <span>No hay almacenes activos: primero da de alta uno.</span>
                    </div>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Aprobaciones" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Firmas de los formatos</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Qué rayas de firma lleva al pie el formato impreso de cada documento. Nada queda detenido
                        esperando a nadie: esto es sólo lo que sale en la hoja.
                    </p>
                </div>

                <div className="mb-4 w-full max-w-sm">
                    <FormField label="Almacén" htmlFor="almacen">
                        <SearchSelect
                            value={almacenId}
                            onValueChange={(v) => v && setAlmacenId(v)}
                            placeholder="Busca el almacén..."
                            options={almacenes.map((a) => ({
                                value: String(a.id),
                                label: `${a.clave} — ${a.nombre}`,
                            }))}
                        />
                    </FormField>
                    <p className="text-base-content/50 mt-1 text-xs">
                        Las firmas son de cada almacén: quien firma en el general no es quien firma en obra.
                    </p>
                </div>

                <FormularioDelAlmacen
                    key={almacenId}
                    almacen={almacenes.find((a) => String(a.id) === almacenId)!}
                    documentos={documentos}
                    guardadas={configuradas[almacenId]}
                    usuarios={usuarios}
                    puedeConfigurar={puedeConfigurar}
                />
            </div>
        </AppLayout>
    );
}

/**
 * El formulario de un almacén. Se remonta al cambiar de almacén (por la `key`)
 * para que no se arrastre lo escrito de uno al otro sin guardar.
 */
function FormularioDelAlmacen({
    almacen,
    documentos,
    guardadas,
    usuarios,
    puedeConfigurar,
}: {
    almacen: Almacen;
    documentos: AlmDocumentoFirmable[];
    /** Lo guardado del almacén; sin nada, nadie lo ha configurado todavía. */
    guardadas?: Record<string, AlmFirmaDocumento[]>;
    usuarios: AlmUsuarioOpcion[];
    puedeConfigurar: boolean;
}) {
    // El almacén que nadie ha configurado arranca de la plantilla, que es lo
    // que imprime hoy: la pantalla enseña lo mismo que sale en el PDF. El ya
    // configurado enseña lo suyo, y un documento vacío es vacío a propósito.
    const configurado = guardadas !== undefined;
    const form = useForm({
        documentos: documentos.map((doc) => ({
            documento: doc.valor,
            firmas: (configurado
                ? (guardadas[doc.valor] ?? [])
                : doc.porDefecto.map((f) => ({ ...f, usuarios: [] }))
            ).map((f) => ({ ...f })),
        })),
    });

    const firmasDe = (documento: AlmDocumentoTipo): AlmFirmaDocumento[] =>
        form.data.documentos.find((d) => d.documento === documento)?.firmas ?? [];

    const escribir = (documento: AlmDocumentoTipo, firmas: AlmFirmaDocumento[]) =>
        form.setData(
            'documentos',
            form.data.documentos.map((d) => (d.documento === documento ? { ...d, firmas } : d)),
        );

    const editar = (documento: AlmDocumentoTipo, indice: number, cambio: Partial<AlmFirmaDocumento>) =>
        escribir(
            documento,
            firmasDe(documento).map((f, i) => (i === indice ? { ...f, ...cambio } : f)),
        );

    const agregar = (documento: AlmDocumentoTipo) =>
        escribir(documento, [...firmasDe(documento), { rotulo: '', nombre: null, usuarios: [] }]);

    const quitar = (documento: AlmDocumentoTipo, indice: number) =>
        escribir(
            documento,
            firmasDe(documento).filter((_, i) => i !== indice),
        );

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(`/admin/almacen/aprobaciones/${almacen.id}`, { preserveScroll: true });
    };

    return (
        <form onSubmit={enviar}>
            <div className="alert alert-info mb-4">
                <InfoIcon className="size-5 shrink-0" />
                <span>
                    Si eliges usuarios, su nombre se imprime sobre la raya —varios salen separados por «/», porque basta
                    con que firme uno—. Si no eliges a nadie, se imprime el nombre que escribas. Y si dejas los dos
                    vacíos, la raya va en blanco para llenarse a mano.
                </span>
            </div>

            {!configurado && (
                <div className="alert alert-warning mb-4">
                    <span>
                        <strong>{almacen.clave}</strong> todavía no se ha configurado: lo que ves es la plantilla, y es
                        lo que hoy se imprime. Al guardar, el almacén pasa a imprimir exactamente lo que dejes aquí
                        —también si a un documento le quitas todas las firmas.
                    </span>
                </div>
            )}

            <div className="space-y-4">
                {documentos.map((doc) => {
                    const firmas = firmasDe(doc.valor);

                    return (
                        <div key={doc.valor} className="rounded-box border-base-300 border p-4">
                            <div className="mb-3 flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <h2 className="font-medium">
                                        {doc.etiqueta}
                                        {!doc.tieneFormato && (
                                            <span className="badge badge-ghost badge-sm ml-2">Sin formato aún</span>
                                        )}
                                    </h2>
                                    {doc.ayuda && (
                                        <p className="text-base-content/50 mt-1 text-xs">{doc.ayuda}</p>
                                    )}
                                </div>
                                {puedeConfigurar && firmas.length < MAX_FIRMAS && (
                                    <Button type="button" className="btn-sm btn-ghost" onClick={() => agregar(doc.valor)}>
                                        <PlusIcon className="size-4" /> Agregar firma
                                    </Button>
                                )}
                            </div>

                            {firmas.length === 0 ? (
                                <p className="text-base-content/50 py-2 text-sm">
                                    Sin firmas: el formato sale sin rayas al pie.
                                </p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="table table-sm">
                                        <thead>
                                            <tr>
                                                <th className="w-10">#</th>
                                                <th className="w-64">Dice</th>
                                                <th className="w-56">Nombre sobre la raya</th>
                                                <th>Usuarios fijos</th>
                                                {puedeConfigurar && <th className="w-12"></th>}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {firmas.map((firma, i) => (
                                                <tr key={i}>
                                                    <td className="text-base-content/50 align-top">
                                                        <div className="pt-3">{i + 1}</div>
                                                    </td>
                                                    <td className="align-top">
                                                        <input
                                                            type="text"
                                                            className="input input-sm input-bordered w-full"
                                                            value={firma.rotulo}
                                                            maxLength={60}
                                                            disabled={!puedeConfigurar}
                                                            placeholder="Autorizó"
                                                            aria-label={`Rótulo de la firma ${i + 1} de ${doc.etiqueta}`}
                                                            onChange={(e) =>
                                                                editar(doc.valor, i, { rotulo: e.target.value })
                                                            }
                                                        />
                                                        <FormError
                                                            mensaje={
                                                                form.errors[
                                                                    `documentos.${documentos.indexOf(doc)}.firmas.${i}.rotulo` as keyof typeof form.errors
                                                                ]
                                                            }
                                                        />
                                                    </td>
                                                    <td className="align-top">
                                                        <input
                                                            type="text"
                                                            className="input input-sm input-bordered w-full"
                                                            value={firma.nombre ?? ''}
                                                            maxLength={60}
                                                            disabled={!puedeConfigurar || firma.usuarios.length > 0}
                                                            placeholder={
                                                                firma.usuarios.length > 0
                                                                    ? 'Mandan los usuarios elegidos'
                                                                    : 'En blanco para firmar a mano'
                                                            }
                                                            aria-label={`Nombre sobre la raya ${i + 1} de ${doc.etiqueta}`}
                                                            onChange={(e) =>
                                                                editar(doc.valor, i, {
                                                                    nombre: e.target.value || null,
                                                                })
                                                            }
                                                        />
                                                    </td>
                                                    <td className="align-top">
                                                        <UsuariosMultiselect
                                                            usuarios={usuarios}
                                                            seleccionados={firma.usuarios}
                                                            onChange={(ids) =>
                                                                editar(doc.valor, i, {
                                                                    usuarios: ids.slice(0, MAX_USUARIOS),
                                                                })
                                                            }
                                                        />
                                                    </td>
                                                    {puedeConfigurar && (
                                                        <td className="align-top">
                                                            <button
                                                                type="button"
                                                                className="btn btn-ghost btn-sm btn-square mt-0.5"
                                                                aria-label={`Quitar la firma ${i + 1} de ${doc.etiqueta}`}
                                                                onClick={() => quitar(doc.valor, i)}
                                                            >
                                                                <TrashIcon className="size-4" />
                                                            </button>
                                                        </td>
                                                    )}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>

            {puedeConfigurar && (
                <div className="mt-6 flex items-center gap-3">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Guardando...' : `Guardar firmas de ${almacen.clave}`}
                    </Button>
                    {form.recentlySuccessful && <span className="text-success text-sm">Guardado.</span>}
                </div>
            )}
        </form>
    );
}

function FormError({ mensaje }: { mensaje?: string }) {
    return mensaje ? <p className="text-error mt-1 text-xs">{mensaje}</p> : null;
}
