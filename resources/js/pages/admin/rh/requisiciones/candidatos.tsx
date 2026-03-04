import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { RhRequisicion } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { UserCheckIcon, XIcon } from 'lucide-react';
import { useState } from 'react';

type Props = {
    requisicion: RhRequisicion;
    personasDisponibles: { id: number; nombre: string; apellido: string }[];
};

export default function RequisicionCandidatos({ requisicion, personasDisponibles }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'RH', href: '/admin/rh/skills' },
        { title: 'Requisiciones', href: '/admin/rh/requisiciones' },
        { title: requisicion.folio, href: `/admin/rh/requisiciones/${requisicion.id}` },
        { title: 'Candidatos', href: `/admin/rh/requisiciones/${requisicion.id}/candidatos` },
    ];

    const [selectedPersonaId, setSelectedPersonaId] = useState('');

    const addCandidato = () => {
        if (!selectedPersonaId) return;
        router.post(`/admin/rh/requisiciones/${requisicion.id}/candidaturas`, {
            persona_id: selectedPersonaId,
        }, { preserveScroll: true });
        setSelectedPersonaId('');
    };

    const removeCandidato = (candidaturaId: number) => {
        router.delete(`/admin/rh/requisiciones/${requisicion.id}/candidaturas/${candidaturaId}`, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Candidatos - ${requisicion.folio}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <div className="mb-6 flex items-center justify-between">
                        <h1 className="text-2xl font-semibold">Candidatos - {requisicion.folio}</h1>
                        <Button variant="outline" asChild>
                            <Link href={`/admin/rh/requisiciones/${requisicion.id}`}>Volver</Link>
                        </Button>
                    </div>

                    {/* Agregar candidato */}
                    <div className="mb-6 flex gap-2">
                        <Select value={selectedPersonaId} onValueChange={setSelectedPersonaId} placeholder="Seleccionar persona" className="w-80">
                            {personasDisponibles.map((p) => (
                                <SelectItem key={p.id} value={String(p.id)}>
                                    {p.nombre} {p.apellido}
                                </SelectItem>
                            ))}
                        </Select>
                        <Button type="button" onClick={addCandidato} disabled={!selectedPersonaId}>
                            Agregar Candidato
                        </Button>
                    </div>

                    {/* Lista de candidaturas */}
                    {(requisicion.candidaturas ?? []).length > 0 ? (
                        <ul className="space-y-2">
                            {(requisicion.candidaturas ?? []).map((cand) => (
                                <li key={cand.id} className="flex items-center justify-between rounded border px-4 py-3">
                                    <div>
                                        <span className="font-medium">
                                            {cand.persona ? `${cand.persona.nombre} ${cand.persona.apellido}` : `Persona #${cand.persona_id}`}
                                        </span>
                                        {cand.fecha_aplicacion && (
                                            <span className="text-muted-foreground ml-2 text-sm">Aplicado: {cand.fecha_aplicacion}</span>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-3">
                                        {cand.porcentaje_match !== null && (
                                            <Badge variant="outline">Match: {cand.porcentaje_match}%</Badge>
                                        )}
                                        {cand.porcentaje_skills !== null && (
                                            <Badge variant="outline">Skills: {cand.porcentaje_skills}%</Badge>
                                        )}
                                        {cand.porcentaje_requisitos !== null && (
                                            <Badge variant="outline">Requisitos: {cand.porcentaje_requisitos}%</Badge>
                                        )}
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={`/admin/rh/periodos-laborales/create?persona_id=${cand.persona_id}&puesto_id=${requisicion.puesto_id}&requisicion_id=${requisicion.id}`}>
                                                <UserCheckIcon className="size-4" />
                                                Contratar
                                            </Link>
                                        </Button>
                                        <Button type="button" variant="ghost" size="icon" onClick={() => removeCandidato(cand.id)}>
                                            <XIcon className="size-4" />
                                        </Button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-muted-foreground text-sm">No hay candidatos registrados para esta requisicion.</p>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
