<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Schema;

use Doctrine\DBAL\Schema\Schema;

interface SchemaBuilder
{
    public function getSchema(): Schema;
}
