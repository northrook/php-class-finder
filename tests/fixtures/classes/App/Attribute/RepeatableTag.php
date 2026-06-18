<?php

declare(strict_types=1);

namespace Fixture\App\Attribute;

#[\Attribute( \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE )]
final class RepeatableTag
{
    public function __construct(
        public string $name,
    ) {
    }
}
