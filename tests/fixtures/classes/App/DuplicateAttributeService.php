<?php

declare(strict_types=1);

namespace Fixture\App;

use Fixture\App\Attribute\RepeatableTag;

#[RepeatableTag( 'a' )]
#[RepeatableTag( 'b' )]
final class DuplicateAttributeService
{
}
