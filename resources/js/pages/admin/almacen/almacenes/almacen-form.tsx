import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import type { AlmAlmacen, AlmAlmacenTipo, Obra, Usuario } from '@/types/models';
import { Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import { useState, type FormEvent } from 'react';

export type OpcionTipo = { value: AlmAlmacenTipo; label: string };

type Props = {
    almacen?: AlmAlmacen;
    obras: Pick<Obra, 'id' | 'no' | 'descripcion'>[];
    usuarios: Pick<Usuario, 'id' | 'name'>[];
    tipos: OpcionTipo[];
};

/**
 * Alta y edición comparten forma. Lo único que cambia el comportamiento del
 * almacén es la obra: sin ella el almacén es central, o sea, está en la planta.
 *
 * Esa ausencia se declara con un checkbox y no dejando el select vacío: "no
 * elegí obra" y "este almacén vive en la planta" son cosas distintas, y con la
 * segunda escrita a mano nadie puede distinguir un central de un descuido.
 */
export function AlmacenForm({ almacen, obras, usuarios, tipos }: Props) {
    const esEdicion = !!almacen;

    // Al dar de alta se asume planta, que es donde están casi todos; al editar
    // manda lo que el almacén ya era.
    const [enPlanta, setEnPlanta] = useState(esEdicion ? almacen.obra_id == null : true);

    const { data, setData, post, put, processing, errors } = useForm({
        clave: almacen?.clave ?? '',
        nombre: almacen?.nombre ?? '',
        obra_id: almacen?.obra_id != null ? String(almacen.obra_id) : '',
        tipo: (almacen?.tipo ?? 'insumos') as AlmAlmacenTipo,
        responsable_id: almacen?.responsable_id ?? '',
        observaciones: almacen?.observaciones ?? '',
        activo: almacen?.activo ?? true,
    });

    const alternarPlanta = (marcado: boolean) => {
        setEnPlanta(marcado);
        setData('obra_id', '');
    };

    // Si no está en la planta tiene que decir en qué obra: guardarlo sin obra lo
    // convertiría en central por la puerta de atrás.
    const faltaObra = !enPlanta && data.obra_id === '';

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();

        if (faltaObra) {
            return;
        }

        if (esEdicion) {
            put(`/admin/almacen/almacenes/${almacen.id}`);
        } else {
            post('/admin/almacen/almacenes');
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div className="grid grid-cols-3 gap-4">
                <FormField
                    label="Clave"
                    htmlFor="clave"
                    error={errors.clave}
                    description="Como la nombra el área: AG, FAK, FAD, A, E."
                    required
                >
                    <Input
                        id="clave"
                        value={data.clave}
                        onChange={(e) => setData('clave', e.target.value.toUpperCase())}
                        error={!!errors.clave}
                        placeholder="AG"
                        className="font-mono"
                    />
                </FormField>

                <FormField
                    label="Nombre"
                    htmlFor="nombre"
                    error={errors.nombre}
                    className="col-span-2"
                    required
                >
                    <Input
                        id="nombre"
                        value={data.nombre}
                        onChange={(e) => setData('nombre', e.target.value)}
                        error={!!errors.nombre}
                        placeholder="Almacén general"
                    />
                </FormField>
            </div>

            <div className="rounded-box border-base-300 border p-3">
                <label className="flex cursor-pointer items-start gap-3">
                    <input
                        type="checkbox"
                        className="checkbox checkbox-sm mt-0.5"
                        checked={enPlanta}
                        onChange={(e) => alternarPlanta(e.target.checked)}
                    />
                    <span>
                        <span className="font-medium">Está en la planta</span>
                        <span className="text-base-content/60 block text-sm">
                            Almacén central: no pertenece a ninguna obra y puede surtir a todas. La planta tiene
                            varios, cada uno con su propia clave.
                        </span>
                    </span>
                </label>

                {!enPlanta && (
                    <div className="mt-3">
                        <FormField
                            label="Obra"
                            htmlFor="obra_id"
                            error={errors.obra_id ?? (faltaObra ? 'Elige la obra donde está el almacén.' : undefined)}
                            description="En qué obra vive este almacén."
                            required
                        >
                            <Select
                                id="obra_id"
                                value={data.obra_id}
                                onValueChange={(v) => setData('obra_id', v)}
                                placeholder="Selecciona la obra"
                                error={!!errors.obra_id || faltaObra}
                            >
                                {obras.map((o) => (
                                    <SelectItem key={o.id} value={String(o.id)}>
                                        {o.no} — {o.descripcion}
                                    </SelectItem>
                                ))}
                            </Select>
                        </FormField>
                    </div>
                )}
            </div>

            <div className="grid grid-cols-2 gap-4">
                <FormField label="Tipo" htmlFor="tipo" error={errors.tipo} required>
                    <Select
                        id="tipo"
                        value={data.tipo}
                        onValueChange={(v) => setData('tipo', v as AlmAlmacenTipo)}
                        error={!!errors.tipo}
                    >
                        {tipos.map((t) => (
                            <SelectItem key={t.value} value={t.value}>
                                {t.label}
                            </SelectItem>
                        ))}
                    </Select>
                </FormField>

                <FormField
                    label="Responsable"
                htmlFor="responsable_id"
                error={errors.responsable_id}
                    description="Informativo: no limita quién puede ver o mover este almacén."
                >
                    <Select
                        id="responsable_id"
                        value={data.responsable_id}
                        onValueChange={(v) => setData('responsable_id', v)}
                        error={!!errors.responsable_id}
                    >
                        <SelectItem value="">Sin asignar</SelectItem>
                        {usuarios.map((u) => (
                            <SelectItem key={u.id} value={u.id}>
                                {u.name}
                            </SelectItem>
                        ))}
                    </Select>
                </FormField>
            </div>

            <FormField label="Observaciones" htmlFor="observaciones" error={errors.observaciones}>
                <textarea
                    id="observaciones"
                    className="textarea textarea-bordered w-full"
                    rows={2}
                    value={data.observaciones}
                    onChange={(e) => setData('observaciones', e.target.value)}
                    placeholder="Ubicación física, horario, notas"
                />
            </FormField>

            <label className="flex cursor-pointer items-center gap-2">
                <input
                    type="checkbox"
                    className="checkbox checkbox-sm"
                    checked={data.activo}
                    onChange={(e) => setData('activo', e.target.checked)}
                />
                <span className="text-sm">Activo</span>
            </label>

            <div className="flex justify-end gap-2">
                <Button variant="outline" asChild>
                    <Link href="/admin/almacen/almacenes">Cancelar</Link>
                </Button>
                <Button type="submit" disabled={processing || faltaObra}>
                    {processing && <Loader2Icon className="size-4 animate-spin" />}
                    Guardar
                </Button>
            </div>
        </form>
    );
}
