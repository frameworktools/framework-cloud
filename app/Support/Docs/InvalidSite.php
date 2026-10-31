<?php

namespace App\Support\Docs;

use RuntimeException;

/** A docs-site folder or archive that cannot be imported, with every problem found */
class InvalidSite extends RuntimeException
{
    /** @param  list<string>  $problems */
    public function __construct(public readonly array $problems)
    {
        parent::__construct(implode("\n", $problems));
    }
}
