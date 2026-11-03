<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Hit;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\JsonSubsetMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Mismatch;
use Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\RealRequestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonSubsetMatcher::class)]
final class JsonSubsetMatcherTest extends TestCase
{
    use RealRequestTrait;

    /** @return iterable<string, array{mixed[], mixed[], bool}> */
    public static function provideSubsets(): iterable
    {
        yield 'additional keys are ignored' => [['a' => 1], ['a' => 1, 'b' => 2], true];
        yield 'nested objects are compared as subset' => [['a' => ['b' => 1]], ['a' => ['b' => 1, 'c' => 2]], true];
        yield 'missing key' => [['a' => 1], ['b' => 1], false];
        yield 'different type' => [['a' => 1], ['a' => '1'], false];
        yield 'equal list' => [['ids' => [1, 2]], ['ids' => [1, 2]], true];
        yield 'list with other types' => [['ids' => [1, 2]], ['ids' => ['1', '2']], false];
        yield 'list with other order' => [['ids' => [1, 2]], ['ids' => [2, 1]], false];
        yield 'list of objects with loose values' => [[['id' => true]], [['id' => 1]], false];
        yield 'nested empty list matches empty list' => [['tags' => []], ['tags' => []], true];
        yield 'nested empty list does not match filled list' => [['tags' => []], ['tags' => ['a']], false];
        yield 'empty subset matches any object' => [[], ['a' => 1], true];
    }

    /**
     * @param mixed[] $subset
     * @param mixed[] $json
     */
    #[DataProvider('provideSubsets')]
    public function testSubset(array $subset, array $json, bool $matches): void
    {
        $result = (new JsonSubsetMatcher($subset))($this->createRealRequest(json: $json));

        self::assertSame($matches, $result instanceof Hit);
    }

    public function testMissingJsonBodyIsAMismatch(): void
    {
        self::assertInstanceOf(Mismatch::class, (new JsonSubsetMatcher(['a' => 1]))($this->createRealRequest()));
    }
}
