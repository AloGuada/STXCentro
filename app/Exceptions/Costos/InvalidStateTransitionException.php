<?php

namespace App\Exceptions\Costos;

use DomainException;

class InvalidStateTransitionException extends DomainException
{
    public function __construct(
        public readonly string $fromState,
        public readonly string $toState,
        public readonly string $entity,
    ) {
        parent::__construct(
            "No se permite transicionar {$entity} de '{$fromState}' a '{$toState}'."
        );
    }
}
