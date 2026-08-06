import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import type { AlmAlmacen, AlmAlmacenTipo, Obra, Usuario } from '@/types/models';
import { Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

export type OpcionTipo = { value: AlmAlmacenTipo; label: string };

type Props = {
    almacen?: AlmAlmacen;
    obras: Pick<Obra, 'id' | 'no' | 'descripcion'>[];
    usuarios: Pick<Usuario, 'id' | 'name'>[];
    tipos: OpcionTipo[];
};

/**
 * Alta y edición comparten forma. Lo único que cambia el comportamiento del
 * almacén es la obra: sin ella el almacén es central.
 */
export function AlmacenForm({ almacen, obras, usuarios, tipos }: Props) {
    const esEdicion = !!almacen;

    const { data, setData, post, put, processing, errors } = useForm({
        clave: almacen?.clave ?? '',
        nombre: almacen?.nombre ?? '',
        obra_id: almacen?.obra_id != null ? String(almacen.obra_id) : '',
        tipo: (almacen?.tipo ?? 'insumos') as AlmAlmacenTipo,
        responsable_id: almacen?.responsable_id ?? '',
        observaciones: almacen?.observaciones ?? '',
        activo: almacen?.activo ?? true,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();

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

            <div className="grid grid-cols-2 gap-4">
                <FormField
                    label="Obra"
                    htmlFor="obra_id"
                    error={errors.obra_id}
                    description="Déjala vacía si el almacén es central y surte a todas las obras."
                >
                    <Select
                        id="obra_id"
                        value={data.obra_id}
                        onValueChange={(v) => setData('obra_id', v)}
                        error={!!errors.obra_id}
                    >
                        <SelectItem value="">Central (sin obra)</SelectItem>
                        {obras.map((o) => (
                            <SelectItem key={o.id} value={String(o.id)}>
                                {o.no} — {o.descripcion}
                            </SelectItem>
                        ))}
                    </Select>
                </FormField>

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
            </div>

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
                <Button type="submit" disabled={processing}>
                    {processing && <Loader2Icon className="size-4 animate-spin" />}
                    Guardar
                </Button>
            </div>
        </form>
    );
}
