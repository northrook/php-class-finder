<?php

declare(strict_types=1);

namespace Fixture\App;

final class InvokableService
{
    public function __construct(
        public string $label,
    ) {
    }
}
