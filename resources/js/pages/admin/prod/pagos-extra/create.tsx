import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { ProdCorte, ProdGrupoTrabajo, ProdTipoPagoExtra } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/cortes' },
    { title: 'Pagos Extra', href: '/admin/prod/pagos-extra' },
    { title: 'Nuevo', href: '/admin/prod/pagos-extra/create' },
];

type Props = {
    tipos: ProdTipoPagoExtra[];
    cortes: ProdCorte[];
    gruposTrabajo: ProdGrupoTrabajo[];
};

export default function PagosExtraCreate({ tipos, cortes, gruposTrabajo }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        descripcion: '',
        tipo_id: '',
        corte_id: '',
        grupo_trabajo_id: '',
        precio: '',
        dias: '1',
        personas: '1',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/prod/pagos-extra');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Pago Extra" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo Pago Extra</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Tipo" htmlFor="tipo_id" error={errors.tipo_id} required>
                            <Select
                                id="tipo_id"
                                value={data.tipo_id}
                                onValueChange={(value) => setData('tipo_id', value)}
                                placeholder="Seleccionar tipo"
                            >
                                {tipos.map((t) => (
                                    <option key={t.id} value={t.id}>{t.descripcion}</option>
                                ))}
                            </Select>
                        </FormField>

                        <FormField label="Corte" htmlFor="corte_id" error={errors.corte_id} required>
                            <Select
                                id="corte_id"
                                value={data.corte_id}
                                onValueChange={(value) => setData('corte_id', value)}
                                placeholder="Seleccionar corte"
                            >
                                {cortes.map((c) => (
                                    <option key={c.id} value={c.id}>Semana {c.semana} ({c.fecha_inicio} - {c.fecha_fin})</option>
                                ))}
                            </Select>
                        </FormField>

                        <FormField label="Grupo de Trabajo" htmlFor="grupo_trabajo_id" error={errors.grupo_trabajo_id} required>
                            <Select
                                id="grupo_trabajo_id"
                                value={data.grupo_trabajo_id}
                                onValueChange={(value) => setData('grupo_trabajo_id', value)}
                                placeholder="Seleccionar grupo"
                            >
                                {gruposTrabajo.map((g) => (
                                    <option key={g.id} value={g.id}>{g.descripcion}</option>
                                ))}
                            </Select>
                        </FormField>

                        <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                            />
                        </FormField>

                        <FormField label="Precio" htmlFor="precio" error={errors.precio} required>
                            <Input
                                id="precio"
                                type="number"
                                step="0.01"
                                min="0"
                                value={data.precio}
                                onChange={(e) => setData('precio', e.target.value)}
                            />
                        </FormField>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Dias" htmlFor="dias" error={errors.dias} required>
                                <Input
                                    id="dias"
                                    type="number"
                                    min="1"
                                    value={data.dias}
                                    onChange={(e) => setData('dias', e.target.value)}
                                />
                            </FormField>

                            <FormField label="Personas" htmlFor="personas" error={errors.personas} required>
                                <Input
                                    id="personas"
                                    type="number"
                                    min="1"
                                    value={data.personas}
                                    onChange={(e) => setData('personas', e.target.value)}
                                />
                            </FormField>
                        </div>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/prod/pagos-extra">Cancelar</Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Guardar
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
