<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Psr\Log\LogLevel;

use function class_exists;

if (!class_exists(LogRecord::class)) {
    /**
     * Callback handler for Monolog 2.x
     *
     * @phpstan-import-type Level from Logger
     * @phpstan-import-type LevelName from Logger
     */
    final class LegacyCallbackHandler extends AbstractProcessingHandler
    {
        /** @var callable */
        private $fn;

        /**
         * @phpstan-param Level|LevelName|LogLevel::* $level
         */
        public function __construct(callable $fn, int|string $level = Logger::DEBUG, bool $bubble = true)
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

        /**
         * @param mixed[] $record
         */
        protected function write(array $record): void
        {
            ($this->fn)($record);
        }
    }
}
