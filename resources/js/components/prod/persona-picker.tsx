import { Button } from '@/components/ui/button';
import { CreatableCombobox } from '@/components/ui/creatable-combobox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useState } from 'react';

export type PersonaOption = {
    id: number;
    nombre: string;
    no_empleado: string | null;
    contratado: boolean;
};

/**
 * Lo que se manda al backend por cada integrante: la persona de RH que ya
 * existe, o una nueva a dar de alta. `etiqueta` es sólo para pintar el renglón
 * antes de guardar.
 */
export type PersonaSeleccion = {
    persona_id: number | null;
    persona_nueva: { nombre: string; apellido: string } | null;
    etiqueta: string;
};

type Props = {
    personas: PersonaOption[];
    onSelect: (seleccion: PersonaSeleccion) => void;
    /** Personas ya en el grupo, para no ofrecerlas otra vez. */
    excluir?: number[];
    className?: string;
};

/** Parte el texto tecleado en nombre y apellido, para no obligar a reescribirlo. */
function partirNombre(texto: string): { nombre: string; apellido: string } {
    const partes = texto.trim().split(/\s+/);

    return {
        nombre: partes[0] ?? '',
        apellido: partes.slice(1).join(' '),
    };
}

export function PersonaPicker({ personas, onSelect, excluir = [], className }: Props) {
    const [nueva, setNueva] = useState<{ nombre: string; apellido: string } | null>(null);

    const options = personas
        .filter((p) => !excluir.includes(p.id))
        .map((p) => ({
            value: String(p.id),
            label: p.no_empleado ? `${p.nombre} · ${p.no_empleado}` : `${p.nombre}${p.contratado ? '' : ' (sin alta en RH)'}`,
        }));

    const confirmarNueva = () => {
        if (!nueva?.nombre.trim() || !nueva.apellido.trim()) return;

        onSelect({
            persona_id: null,
            persona_nueva: { nombre: nueva.nombre.trim(), apellido: nueva.apellido.trim() },
            etiqueta: `${nueva.nombre.trim()} ${nueva.apellido.trim()}`,
        });
        setNueva(null);
    };

    return (
        <>
            <CreatableCombobox
                options={options}
                placeholder="Buscar persona por nombre..."
                creatableLabel="Dar de alta a"
                className={className}
                onSelect={(option) => {
                    const persona = personas.find((p) => String(p.id) === option.value);
                    if (!persona) return;

                    onSelect({ persona_id: persona.id, persona_nueva: null, etiqueta: persona.nombre });
                }}
                onCreate={(texto) => setNueva(partirNombre(texto))}
            />

            <Dialog open={nueva !== null} onOpenChange={(open) => !open && setNueva(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Nueva persona</DialogTitle>
                        <p className="text-base-content/60 mt-1 text-sm">
                            Se da de alta en RH sin contrato. Cuando la contraten, su periodo laboral se le agrega ahí
                            mismo y aquí no hay que recapturar nada.
                        </p>
                    </DialogHeader>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <label className="block">
                            <span className="mb-1 block text-sm font-medium">Nombre</span>
                            <Input
                                value={nueva?.nombre ?? ''}
                                onChange={(e) => setNueva((n) => ({ nombre: e.target.value, apellido: n?.apellido ?? '' }))}
                                autoFocus
                            />
                        </label>
                        <label className="block">
                            <span className="mb-1 block text-sm font-medium">Apellido</span>
                            <Input
                                value={nueva?.apellido ?? ''}
                                onChange={(e) => setNueva((n) => ({ nombre: n?.nombre ?? '', apellido: e.target.value }))}
                            />
                        </label>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setNueva(null)}>
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            onClick={confirmarNueva}
                            disabled={!nueva?.nombre.trim() || !nueva?.apellido.trim()}
                        >
                            Agregar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
