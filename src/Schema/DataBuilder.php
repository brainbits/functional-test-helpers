<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Schema;

interface DataBuilder
{
    /** @return mixed[] */
    public function getData(): array;
}
