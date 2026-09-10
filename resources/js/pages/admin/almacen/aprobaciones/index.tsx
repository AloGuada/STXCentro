import { UsuariosMultiselect } from '@/components/alm/usuarios-multiselect';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { ALMACENES_DEMO, APROBACIONES_DEMO, AYUDA_DOCUMENTO, DOCUMENTOS_ALM, USUARIOS_DEMO } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import type { AlmDocumentoTipo, AlmReglaAprobacion } from '@/types/models';
import { Head } from '@inertiajs/react';
import { InfoIcon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Aprobaciones', href: '/admin/almacen/aprobaciones' },
];

export default function AprobacionesIndex() {
    const [almacenId, setAlmacenId] = useState(String(ALMACENES_DEMO[0].id));
    // Las reglas son por almacén: cada uno arranca de la misma plantilla y se
    // ajusta aparte, porque quien firma en el general no es quien firma en obra.
    const [reglasPorAlmacen, setReglasPorAlmacen] = useState<Record<string, AlmReglaAprobacion[]>>({});

    const reglas = reglasPorAlmacen[almacenId] ?? APROBACIONES_DEMO;

    const almacen = ALMACENES_DEMO.find((a) => String(a.id) === almacenId);

    const editar = (documento: AlmDocumentoTipo, cambio: Partial<AlmReglaAprobacion>) =>
        setReglasPorAlmacen((prev) => ({
            ...prev,
            [almacenId]: reglas.map((r) => (r.documento === documento ? { ...r, ...cambio } : r)),
        }));

    const sinAprobador = reglas.filter((r) => r.requiere && r.usuarios.length === 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Aprobaciones" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Aprobaciones</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Qué documentos necesitan firma en cada almacén y quién puede darla.
                    </p>
                </div>

                <div className="alert alert-warning mb-4">
                    <span>Vista de maqueta: la configuración todavía no se guarda.</span>
                </div>

                <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                    <div className="w-full max-w-sm">
                        <FormField label="Almacén" htmlFor="almacen">
                            <Select id="almacen" value={almacenId} onValueChange={setAlmacenId}>
                                {ALMACENES_DEMO.map((a) => (
                                    <SelectItem key={a.id} value={String(a.id)}>
                                        {a.clave} — {a.nombre}
                                        {a.obra ? ` (${a.obra})` : ''}
                                    </SelectItem>
                                ))}
                            </Select>
                        </FormField>
                    </div>

                    <Button disabled title="La maqueta no guarda todavía">
                        Guardar configuración
                    </Button>
                </div>

                <div className="alert alert-info mb-4">
                    <InfoIcon className="size-5 shrink-0" />
                    <span>
                        Basta con que firme <strong>uno</strong> de los elegidos: se listan varios para que la
                        operación no se detenga cuando alguien falta, no para pedir todas las firmas.
                    </span>
                </div>

                {sinAprobador.length > 0 && (
                    <div className="alert alert-error mb-4">
                        <span>
                            {sinAprobador.map((r) => DOCUMENTOS_ALM[r.documento]).join(', ')} pide firma pero no tiene a
                            nadie que la pueda dar: esos documentos se quedarían atorados.
                        </span>
                    </div>
                )}

                <div className="rounded-box border-base-300 overflow-x-auto border">
                    <table className="table">
                        <thead className="bg-base-200">
                            <tr>
                                <th className="w-52">Documento</th>
                                <th className="w-32 text-center">¿Pide firma?</th>
                                <th>Puede firmar</th>
                            </tr>
                        </thead>
                        <tbody>
                            {reglas.map((regla) => (
                                <tr key={regla.documento}>
                                    <td className="align-top font-medium">
                                        <div className="pt-2">{DOCUMENTOS_ALM[regla.documento]}</div>
                                        {AYUDA_DOCUMENTO[regla.documento] && (
                                            <p className="text-base-content/50 mt-1 text-xs font-normal">
                                                {AYUDA_DOCUMENTO[regla.documento]}
                                            </p>
                                        )}
                                    </td>
                                    <td className="text-center align-top">
                                        <input
                                            type="checkbox"
                                            className="checkbox checkbox-sm mt-3"
                                            checked={regla.requiere}
                                            onChange={(e) =>
                                                editar(regla.documento, {
                                                    requiere: e.target.checked,
                                                    usuarios: e.target.checked ? regla.usuarios : [],
                                                })
                                            }
                                            aria-label={`${DOCUMENTOS_ALM[regla.documento]} pide firma`}
                                        />
                                    </td>
                                    <td>
                                        {regla.requiere ? (
                                            <UsuariosMultiselect
                                                usuarios={USUARIOS_DEMO}
                                                seleccionados={regla.usuarios}
                                                onChange={(ids) => editar(regla.documento, { usuarios: ids })}
                                                placeholder="Elige quién puede firmar..."
                                            />
                                        ) : (
                                            <p className="text-base-content/50 py-3 text-sm">
                                                Se registra directo, sin firma.
                                            </p>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <p className="text-base-content/60 mt-4 text-sm">
                    Configuración de <strong>{almacen ? `${almacen.clave} — ${almacen.nombre}` : 'este almacén'}</strong>
                    . El <strong>ajuste</strong> es el que más conviene cuidar: es el único movimiento que cambia la
                    existencia sin un documento que lo respalde.
                </p>
            </div>
        </AppLayout>
    );
}
