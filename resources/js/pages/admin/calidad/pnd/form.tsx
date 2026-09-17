/**
 * Captura del informe de PND que entrega el laboratorio.
 *
 * El laboratorio no entra al sistema: manda su informe y aquí se teclea. Por
 * eso la pantalla se parece al papel —encabezado arriba, parámetros, rejilla— y
 * no a un formulario nuestro.
 *
 * El encabezado se guarda **una sola vez**: en la aplicación anterior estos
 * catorce datos se repetían dentro de cada renglón de la rejilla, y corregir el
 * nombre del laboratorio significaba reescribir el informe entero.
 */

import { DeleteDialog } from '@/components/delete-dialog';
import { ParametrosMetodo, type FilaParametro } from '@/components/qal/pnd/parametros-metodo';
import { FILA_VACIA, RejillaJuntas, type FilaJunta } from '@/components/qal/pnd/rejilla-juntas';
import { semanaIsoDe } from '@/components/qal/pnd/reglas';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import { FormField } from '@/components/form';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { QalLaboratorio, QalObra, QalPndReporte, QalSoldador } from '@/types/models';
import { Head, router, useForm } from '@inertiajs/react';
import { FileTextIcon, LinkIcon } from 'lucide-react';

type Metodo = { valor: string; nombre: string; detecta: string; parametros: string[] };

type Props = {
    reporte: QalPndReporte | null;
    obraId: number | null;
    obras: QalObra[];
    laboratorios: QalLaboratorio[];
    soldadores: QalSoldador[];
    metodos: Metodo[];
};

type Datos = {
    reporte_no: string;
    metodo: string;
    laboratorio_id: string;
    qal_obra_id: string;
    lugar: string;
    fecha_prueba: string;
    fecha_emision: string;
    anio: string;
    semana: string;
    porcentaje_inspeccion: string;
    tecnico: string;
    material: string;
    norma: string;
    parametros: FilaParametro[];
    juntas: FilaJunta[];
    pdf: File | null;
    fotos: File[];
};

export default function PndForm({ reporte, obraId, obras, laboratorios, soldadores, metodos }: Props) {
    const { can } = useCan();
    const editando = reporte !== null;

    const { data, setData, post, processing, errors } = useForm<Datos>({
        reporte_no: reporte?.reporte_no ?? '',
        metodo: reporte?.metodo ?? 'UT',
        laboratorio_id: reporte ? String(reporte.laboratorio_id) : '',
        qal_obra_id: String(reporte?.qal_obra_id ?? obraId ?? ''),
        lugar: reporte?.lugar ?? '',
        fecha_prueba: reporte?.fecha_prueba?.slice(0, 10) ?? '',
        fecha_emision: reporte?.fecha_emision?.slice(0, 10) ?? '',
        anio: reporte ? String(reporte.anio) : '',
        semana: reporte ? String(reporte.semana) : '',
        porcentaje_inspeccion: reporte?.porcentaje_inspeccion ?? '',
        tecnico: reporte?.tecnico ?? '',
        material: reporte?.material ?? '',
        norma: reporte?.norma ?? '',
        parametros: reporte?.parametros?.map((p) => ({ clave: p.clave, valor: p.valor })) ?? [],
        juntas:
            reporte?.juntas?.map((j) => ({
                marca: j.marca,
                // Se re-arma la referencia para poder editarla como se tecleó.
                junta: j.spot > 1 ? `${j.junta}-${j.spot}` : j.junta,
                spot: String(j.spot),
                modulo: j.modulo ?? '',
                resultado: j.resultado,
                discontinuidad: j.discontinuidad ?? '',
                longitud_discontinuidad: j.longitud_discontinuidad ?? '',
                espesor: j.espesor ?? '',
                soldador_id: j.soldador_id ? String(j.soldador_id) : '',
            })) ?? [{ ...FILA_VACIA }],
        pdf: null,
        fotos: [],
    });

    const metodo = metodos.find((m) => m.valor === data.metodo) ?? metodos[0];

    // Ver un informe no es poder corregirlo: la pantalla se abre con
    // `qal.pnd.ver` y guardar pide `qal.pnd.editar`.
    const puedeGuardar = editando ? can('qal.pnd.editar') : true;

    /** La semana ISO sale de la fecha de prueba; queda editable por si el informe dice otra. */
    const cambiarFechaPrueba = (fecha: string) => {
        const iso = semanaIsoDe(fecha);

        setData((actual) => ({
            ...actual,
            fecha_prueba: fecha,
            anio: iso ? String(iso.anio) : actual.anio,
            semana: iso ? String(iso.semana) : actual.semana,
        }));
    };

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        post(editando ? `/admin/calidad/pnd/${reporte.id}` : '/admin/calidad/pnd', {
            forceFormData: true,
            preserveScroll: editando,
        });
    };

    const sueltas = reporte?.juntas?.filter((junta) => junta.concepto_id === null).length ?? 0;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Calidad', href: '/admin/calidad/catalogos' },
        { title: 'PND', href: `/admin/calidad/pnd${obraId ? `?obra=${obraId}` : ''}` },
        { title: editando ? reporte.reporte_no : 'Capturar informe', href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={editando ? `PND ${reporte.reporte_no}` : 'Capturar informe de PND'} />

            <form onSubmit={enviar} className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            {editando ? `Informe ${reporte.reporte_no}` : 'Capturar informe de PND'}
                        </h1>
                        <p className="text-base-content/60 text-sm">
                            {metodo?.nombre} — {metodo?.detecta}
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        {editando && reporte.archivo_pdf && (
                            <a
                                href={`/storage/${reporte.archivo_pdf}`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="btn btn-ghost btn-sm"
                            >
                                <FileTextIcon className="size-4" /> Informe original
                            </a>
                        )}
                        {editando && can('qal.pnd.eliminar') && (
                            <DeleteDialog
                                deleteUrl={`/admin/calidad/pnd/${reporte.id}`}
                                title="Eliminar el informe"
                                description="Se van con él sus puntos examinados, sus parámetros y sus archivos. El avance de PND de la obra baja en consecuencia."
                            />
                        )}
                        {puedeGuardar ? (
                            <Button type="submit" variant="primary" loading={processing}>
                                {editando ? 'Guardar cambios' : 'Guardar informe'}
                            </Button>
                        ) : (
                            <span className="text-base-content/50 text-sm">Sólo lectura</span>
                        )}
                    </div>
                </div>

                {editando && sueltas > 0 && can('qal.pnd.editar') && (
                    <div className="alert alert-warning">
                        <span>
                            {sueltas} puntos del informe citan marcas que no están, o están en más de un lote, en el
                            catálogo vigente de Producción de la obra. Se conserva el texto del laboratorio; el enlace
                            se puede volver a intentar cuando Producción las cargue.
                        </span>
                        <button
                            type="button"
                            className="btn btn-sm"
                            onClick={() =>
                                router.post(`/admin/calidad/pnd/${reporte.id}/resolver-marcas`, {}, { preserveScroll: true })
                            }
                        >
                            <LinkIcon className="size-4" /> Enlazar marcas
                        </button>
                    </div>
                )}

                <section className="rounded-box border border-base-300 p-4">
                    <h2 className="font-semibold">Encabezado del informe</h2>
                    <p className="text-base-content/60 text-sm">Se guarda una vez y vale para toda la rejilla.</p>

                    <div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
                        <FormField label="N.º de reporte" htmlFor="reporte_no" required error={errors.reporte_no}
                            description="El folio del laboratorio, tal como viene en su hoja.">
                            <Input
                                id="reporte_no"
                                className="font-mono"
                                value={data.reporte_no}
                                onChange={(e) => setData('reporte_no', e.target.value)}
                            />
                        </FormField>

                        <FormField label="Método" htmlFor="metodo" required error={errors.metodo}>
                            <Select id="metodo" value={data.metodo} onValueChange={(valor) => setData('metodo', valor)}>
                                {metodos.map((m) => (
                                    <SelectItem key={m.valor} value={m.valor}>
                                        {m.valor} · {m.nombre}
                                    </SelectItem>
                                ))}
                            </Select>
                        </FormField>

                        <FormField label="Laboratorio" htmlFor="laboratorio_id" required error={errors.laboratorio_id}>
                            <Select
                                id="laboratorio_id"
                                value={data.laboratorio_id}
                                onValueChange={(valor) => setData('laboratorio_id', valor)}
                                placeholder="Elegir laboratorio"
                            >
                                {laboratorios.map((laboratorio) => (
                                    <SelectItem key={laboratorio.id} value={String(laboratorio.id)}>
                                        {laboratorio.siglas ? `${laboratorio.siglas} · ` : ''}
                                        {laboratorio.nombre}
                                    </SelectItem>
                                ))}
                            </Select>
                        </FormField>

                        <FormField label="Obra" htmlFor="qal_obra_id" required error={errors.qal_obra_id}>
                            <Select
                                id="qal_obra_id"
                                value={data.qal_obra_id}
                                onValueChange={(valor) => setData('qal_obra_id', valor)}
                                placeholder="Elegir obra"
                            >
                                {obras.map((obra) => (
                                    <SelectItem key={obra.id} value={String(obra.id)}>
                                        {obra.no}
                                        {obra.descripcion ? ` · ${obra.descripcion}` : ''}
                                    </SelectItem>
                                ))}
                            </Select>
                        </FormField>

                        <FormField label="Lugar" htmlFor="lugar" error={errors.lugar}>
                            <Input id="lugar" value={data.lugar} onChange={(e) => setData('lugar', e.target.value)} />
                        </FormField>

                        <FormField label="Técnico" htmlFor="tecnico" error={errors.tecnico}
                            description="Quien firmó la prueba por el laboratorio.">
                            <Input id="tecnico" value={data.tecnico} onChange={(e) => setData('tecnico', e.target.value)} />
                        </FormField>

                        <FormField label="Fecha de prueba" htmlFor="fecha_prueba" required error={errors.fecha_prueba}>
                            <Input
                                id="fecha_prueba"
                                type="date"
                                value={data.fecha_prueba}
                                onChange={(e) => cambiarFechaPrueba(e.target.value)}
                            />
                        </FormField>

                        <FormField label="Fecha de emisión" htmlFor="fecha_emision" error={errors.fecha_emision}>
                            <Input
                                id="fecha_emision"
                                type="date"
                                value={data.fecha_emision}
                                onChange={(e) => setData('fecha_emision', e.target.value)}
                            />
                        </FormField>

                        <div className="grid grid-cols-2 gap-3">
                            <FormField label="Año" htmlFor="anio" required error={errors.anio}
                                description="ISO: sale de la fecha.">
                                <Input
                                    id="anio"
                                    type="number"
                                    className="font-mono"
                                    value={data.anio}
                                    onChange={(e) => setData('anio', e.target.value)}
                                />
                            </FormField>

                            <FormField label="Semana" htmlFor="semana" required error={errors.semana}>
                                <Input
                                    id="semana"
                                    type="number"
                                    min={1}
                                    max={53}
                                    className="font-mono"
                                    value={data.semana}
                                    onChange={(e) => setData('semana', e.target.value)}
                                />
                            </FormField>
                        </div>

                        <FormField label="% de inspección pactado" htmlFor="porcentaje_inspeccion"
                            error={errors.porcentaje_inspeccion}
                            description="El que dice el informe, no el del contrato.">
                            <Input
                                id="porcentaje_inspeccion"
                                type="number"
                                step="0.01"
                                className="font-mono"
                                value={data.porcentaje_inspeccion}
                                onChange={(e) => setData('porcentaje_inspeccion', e.target.value)}
                            />
                        </FormField>

                        <FormField label="Material" htmlFor="material" error={errors.material}>
                            <Input
                                id="material"
                                placeholder="A572 Gr.50"
                                value={data.material}
                                onChange={(e) => setData('material', e.target.value)}
                            />
                        </FormField>

                        <FormField label="Norma" htmlFor="norma" error={errors.norma}>
                            <Input
                                id="norma"
                                placeholder="AWS D1.1"
                                value={data.norma}
                                onChange={(e) => setData('norma', e.target.value)}
                            />
                        </FormField>
                    </div>
                </section>

                <section className="rounded-box border border-base-300 p-4">
                    <ParametrosMetodo
                        filas={data.parametros}
                        onChange={(filas) => setData('parametros', filas)}
                        sugerencias={metodo?.parametros ?? []}
                    />
                </section>

                <section className="rounded-box border border-base-300 p-4">
                    <RejillaJuntas
                        filas={data.juntas}
                        onChange={(filas) => setData('juntas', filas)}
                        soldadores={soldadores}
                        errores={errors as Record<string, string>}
                    />
                </section>

                <section className="rounded-box border border-base-300 p-4">
                    <h2 className="font-semibold">Archivos</h2>
                    <p className="text-base-content/60 text-sm">
                        El PDF firmado es lo que va al dosier; las fotos son la evidencia de lo que se vio.
                    </p>

                    <div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                        <FormField label="Informe original (PDF)" htmlFor="pdf" error={errors.pdf}
                            description={reporte?.archivo_pdf ? 'Subir uno nuevo reemplaza el guardado.' : undefined}>
                            <input
                                id="pdf"
                                type="file"
                                accept="application/pdf"
                                className="file-input file-input-bordered w-full"
                                onChange={(e) => setData('pdf', e.target.files?.[0] ?? null)}
                            />
                        </FormField>

                        <FormField label="Fotos" htmlFor="fotos" error={errors.fotos}
                            description="Se agregan a las que ya tenga el informe.">
                            <input
                                id="fotos"
                                type="file"
                                accept="image/*"
                                multiple
                                className="file-input file-input-bordered w-full"
                                onChange={(e) => setData('fotos', Array.from(e.target.files ?? []))}
                            />
                        </FormField>
                    </div>

                    {editando && (reporte.fotos?.length ?? 0) > 0 && (
                        <div className="mt-3 flex flex-wrap gap-2">
                            {reporte.fotos?.map((foto) => (
                                <a
                                    key={foto.id}
                                    href={`/storage/${foto.ruta}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="border-base-300 block h-20 w-20 overflow-hidden rounded border"
                                    title={foto.nombre ?? ''}
                                >
                                    <img src={`/storage/${foto.ruta}`} alt={foto.nombre ?? 'Evidencia'} className="h-full w-full object-cover" />
                                </a>
                            ))}
                        </div>
                    )}
                </section>
            </form>
        </AppLayout>
    );
}
