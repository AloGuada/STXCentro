<?php

namespace App\Models\Concerns;

use App\Exceptions\Costos\StaleModelException;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Soft-lock de edición por usuario con TTL configurable.
 *
 * El modelo debe tener columnas `locked_by` (FK a usuarios, nullable) y
 * `locked_at` (timestamp nullable). La complementa la validación optimista
 * de `updated_at` (ver App\Http\Requests\Concerns\ValidatesOptimisticLock)
 * que protege el update aunque el soft-lock falle por ventana expirada.
 */
trait HasEditLock
{
    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'locked_by');
    }

    public function lock(string $usuarioId): bool
    {
        if ($this->isLocked() && ! $this->isLockedBy($usuarioId)) {
            return false;
        }

        $this->forceFill([
            'locked_by' => $usuarioId,
            'locked_at' => now(),
        ])->save();

        return true;
    }

    public function unlock(?string $usuarioId = null): void
    {
        if ($usuarioId && ! $this->isLockedBy($usuarioId)) {
            return;
        }

        $this->forceFill([
            'locked_by' => null,
            'locked_at' => null,
        ])->save();
    }

    public function isLocked(): bool
    {
        return $this->locked_by !== null && ! $this->isLockExpired();
    }

    public function isLockedBy(string $usuarioId): bool
    {
        return $this->locked_by === $usuarioId && ! $this->isLockExpired();
    }

    public function isLockExpired(): bool
    {
        if ($this->locked_at === null) {
            return true;
        }

        $ttl = (int) config('costos.lock_ttl_minutes', 15);

        return $this->locked_at->lt(now()->subMinutes($ttl));
    }

    /**
     * Valida que el `_version` enviado por el cliente (updated_at del read)
     * siga siendo el `updated_at` actual del modelo. Si no, lanza 409.
     */
    public function assertVersion(?string $clientVersion): void
    {
        if ($clientVersion === null) {
            throw new StaleModelException($this);
        }

        $serverVersion = $this->updated_at?->toIso8601String();

        if ($serverVersion === null) {
            return;
        }

        if (Carbon::parse($clientVersion)->ne(Carbon::parse($serverVersion))) {
            throw new StaleModelException($this);
        }
    }
}
