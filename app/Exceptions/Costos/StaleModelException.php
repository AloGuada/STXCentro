<?php

namespace App\Exceptions\Costos;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Se lanza cuando el cliente intenta actualizar un modelo con un
 * `_version` (updated_at del read) que ya no coincide con el actual:
 * otro usuario modificó el registro en medias. Responde HTTP 409.
 */
class StaleModelException extends RuntimeException implements HttpExceptionInterface
{
    public function __construct(public readonly Model $model)
    {
        parent::__construct(
            'El registro fue modificado por otro usuario. Recargue la página para ver los cambios más recientes.'
        );
    }

    public function getStatusCode(): int
    {
        return 409;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }
}
