<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

final class CallbackHandler extends AbstractProcessingHandler
{
    /** @var callable */
    private $fn;

    public function __construct(callable $fn, int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->fn = $fn;
    }

    public function clear(): void
    {
    }

    public function reset(): void
    {
    }

    protected function write(LogRecord $record): void
    {
        ($this->fn)($record);
    }
}
