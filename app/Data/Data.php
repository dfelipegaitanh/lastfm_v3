<?php

declare(strict_types=1);

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
abstract readonly class Data implements Arrayable, JsonSerializable
{
    /**
     * @return array<string, mixed>
     */
    final public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    final public function toArray(): array
    {
        return array_map(
            fn (mixed $value): mixed => $value instanceof Arrayable ? $value->toArray() : $value,
            get_object_vars($this)
        );
    }
}
