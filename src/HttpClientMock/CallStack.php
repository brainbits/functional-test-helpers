<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock;

use Countable;
use IteratorAggregate;
use Traversable;

use function array_map;
use function array_merge;
use function count;
use function Safe\sprintf;

use const PHP_EOL;

/**
 * @implements IteratorAggregate<MockRequestBuilder>
 */
final class CallStack implements Countable, IteratorAggregate
{
    /** @var MockRequestBuilder[] */
    private array $calls;

    public function __construct(MockRequestBuilder ...$calls)
    {
        $this->calls = $calls;
    }

    public static function fromCallStacks(CallStack ...$callStacks): self
    {
        $requests = array_merge(...array_map(static fn ($callStack) => $callStack->calls, $callStacks));

        return new self(...$requests);
    }

    public function first(): ?MockRequestBuilder
    {
        if (!count($this->calls)) {
            return null;
        }

        return $this->calls[0];
    }

    public function isEmpty(): bool
    {
        return count($this->calls) === 0;
    }

    public function count(): int
    {
        return count($this->calls);
    }

    /**
     * @return Traversable<MockRequestBuilder>|MockRequestBuilder[]
     */
    public function getIterator(): Traversable
    {
        yield from $this->calls;
    }

    public function __toString(): string
    {
        $output = '';
        foreach ($this->calls as $index => $call) {
            $output .= sprintf('%s %s%s', $index + 1, (string) $call, PHP_EOL);
        }

        return $output;
    }
}
