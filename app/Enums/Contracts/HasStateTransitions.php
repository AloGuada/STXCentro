<?php

namespace App\Enums\Contracts;

/**
 * Un enum de estatus que declara a qué estados puede transicionar desde cada case.
 * Habilita máquinas de estado genéricas vía App\Models\Concerns\HasStateMachine.
 */
interface HasStateTransitions
{
    /**
     * @return array<int, static>
     */
    public function allowedTransitions(): array;
}
