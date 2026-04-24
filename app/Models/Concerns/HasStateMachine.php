<?php

namespace App\Models\Concerns;

use App\Enums\Contracts\HasStateTransitions;
use App\Exceptions\Costos\InvalidStateTransitionException;
use BackedEnum;

/**
 * Máquina de estados genérica para modelos con una columna de estatus
 * (default: "estatus") respaldada por un Enum que implementa HasStateTransitions.
 *
 * El modelo debe declarar: `protected static string $stateEnum = OrdenCompraEstatus::class;`
 *
 * Las transiciones automáticas del sistema (ej. OrdenCompra::recalcularEstatus)
 * deben seguir usando ->update() directo; transitionTo() es para acciones de usuario.
 */
trait HasStateMachine
{
    public function currentState(): HasStateTransitions
    {
        $enumClass = static::$stateEnum;
        $value = $this->getAttribute($this->stateColumn());

        if ($value instanceof BackedEnum) {
            return $value;
        }

        return $enumClass::from($value);
    }

    /**
     * Valida y aplica una transición de estado. Lanza InvalidStateTransitionException
     * si la transición no está permitida por el enum actual.
     */
    public function transitionTo(HasStateTransitions $new): static
    {
        $current = $this->currentState();

        if (! in_array($new, $current->allowedTransitions(), true)) {
            throw new InvalidStateTransitionException(
                fromState: $current->value,
                toState: $new->value,
                entity: class_basename(static::class),
            );
        }

        $this->update([$this->stateColumn() => $new->value]);

        return $this;
    }

    protected function stateColumn(): string
    {
        return 'estatus';
    }
}
