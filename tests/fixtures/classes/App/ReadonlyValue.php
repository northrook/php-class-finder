<?php

declare(strict_types=1);

namespace Fixture\App;

final readonly class ReadonlyValue
{
    public function __construct(
        public string $value,
    ) {
    }
}
