import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import type { Obra, Proyecto } from '@/types/models';
import { useForm } from '@inertiajs/react';
import { Loader2Icon, PlusIcon } from 'lucide-react';
import { useState, type FormEvent } from 'react';

type ObraOption = Pick<Obra, 'id' | 'no' | 'descripcion'>;
type ProyectoOption = Pick<Proyecto, 'id' | 'no' | 'descripcion'>;

type Props = {
    obrasDisponibles: ObraOption[];
    proyectos: ProyectoOption[];
};

export function NuevoCatalogoDialog({ obrasDisponibles, proyectos }: Props) {
    const [open, setOpen] = useState(false);

    const form = useForm<{
        modo: 'existente' | 'nueva';
        nombre: string;
        notas: string;
        obra_id: string;
        obra_no: string;
        obra_descripcion: string;
        proyecto_id: string;
    }>({
        modo: obrasDisponibles.length > 0 ? 'existente' : 'nueva',
        nombre: '',
        notas: '',
        obra_id: '',
        obra_no: '',
        obra_descripcion: '',
        proyecto_id: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/admin/prod/catalogos', {
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger className="btn btn-primary">
                <PlusIcon className="size-4" /> Nuevo catálogo
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Nuevo catálogo de conceptos</DialogTitle>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Nace como versión 1 y vacío: las piezas se capturan o se importan del layout.
                    </p>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <FormField label="Nombre del catálogo" htmlFor="cat_nombre" error={form.errors.nombre} required>
                        <Input
                            id="cat_nombre"
                            value={form.data.nombre}
                            onChange={(e) => form.setData('nombre', e.target.value)}
                            error={!!form.errors.nombre}
                            placeholder="Ej: Estructura principal"
                        />
                    </FormField>

                    <div role="tablist" className="tabs tabs-boxed">
                        <button
                            type="button"
                            role="tab"
                            className={`tab ${form.data.modo === 'existente' ? 'tab-active' : ''}`}
                            onClick={() => form.setData('modo', 'existente')}
                        >
                            Obra existente
                        </button>
                        <button
                            type="button"
                            role="tab"
                            className={`tab ${form.data.modo === 'nueva' ? 'tab-active' : ''}`}
                            onClick={() => form.setData('modo', 'nueva')}
                        >
                            Obra nueva
                        </button>
                    </div>

                    {form.data.modo === 'existente' ? (
                        <FormField label="Obra" htmlFor="cat_obra_id" error={form.errors.obra_id} required>
                            {obrasDisponibles.length === 0 ? (
                                <p className="text-base-content/60 text-sm">
                                    Todas las obras ya tienen catálogo. Para cambiar sus piezas, crea una versión nueva
                                    desde el catálogo existente.
                                </p>
                            ) : (
                                <Select
                                    id="cat_obra_id"
                                    value={form.data.obra_id}
                                    onValueChange={(v) => form.setData('obra_id', v)}
                                    placeholder="Selecciona la obra"
                                    error={!!form.errors.obra_id}
                                >
                                    {obrasDisponibles.map((o) => (
                                        <SelectItem key={o.id} value={String(o.id)}>
                                            {o.no} — {o.descripcion}
                                        </SelectItem>
                                    ))}
                                </Select>
                            )}
                        </FormField>
                    ) : (
                        <div className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <FormField label="No. Obra" htmlFor="cat_obra_no" error={form.errors.obra_no} required>
                                    <Input
                                        id="cat_obra_no"
                                        value={form.data.obra_no}
                                        onChange={(e) => form.setData('obra_no', e.target.value)}
                                        error={!!form.errors.obra_no}
                                        placeholder="Ej: OBR-001"
                                    />
                                </FormField>

                                <FormField
                                    label="Descripción de la obra"
                                    htmlFor="cat_obra_descripcion"
                                    error={form.errors.obra_descripcion}
                                    required
                                >
                                    <Input
                                        id="cat_obra_descripcion"
                                        value={form.data.obra_descripcion}
                                        onChange={(e) => form.setData('obra_descripcion', e.target.value)}
                                        error={!!form.errors.obra_descripcion}
                                    />
                                </FormField>
                            </div>

                            <FormField
                                label="Proyecto de cobranza (opcional)"
                                htmlFor="cat_proyecto_id"
                                error={form.errors.proyecto_id}
                            >
                                <Select
                                    id="cat_proyecto_id"
                                    value={form.data.proyecto_id}
                                    onValueChange={(v) => form.setData('proyecto_id', v)}
                                    error={!!form.errors.proyecto_id}
                                >
                                    <SelectItem value="">Sin ligar (sólo producción)</SelectItem>
                                    {proyectos.map((p) => (
                                        <SelectItem key={p.id} value={String(p.id)}>
                                            {p.no} — {p.descripcion}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </FormField>
                        </div>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Loader2Icon className="size-4 animate-spin" />}
                            Crear catálogo
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
