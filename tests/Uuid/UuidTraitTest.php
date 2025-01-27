<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\Uuid;

use Brainbits\FunctionalTestHelpers\Uuid\UuidTrait;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Throwable;

use function Safe\json_encode;

final class UuidTraitTest extends TestCase
{
    use UuidTrait;

    public function testCreateUuidByInteger(): void
    {
        self::assertEquals('00000000-0000-0000-0000-000000000001', self::uuidFromInteger(1));
        self::assertEquals('00000000-0000-0000-0000-000000000002', self::uuidFromInteger(2));
    }

    public function testNextUuid(): void
    {
        self::assertEquals('00000000-0000-0000-0000-000000000001', $this->nextUuid());
        self::assertEquals('00000000-0000-0000-0000-000000000002', $this->nextUuid());
        self::assertEquals('00000000-0000-0000-0000-000000000003', $this->nextUuid());
        self::assertEquals('00000000-0000-0000-0000-000000000004', $this->nextUuid());
        self::assertEquals('00000000-0000-0000-0000-000000000005', $this->nextUuid());
        self::assertEquals('00000000-0000-0000-0000-000000000006', $this->nextUuid());
        self::assertEquals('00000000-0000-0000-0000-000000000007', $this->nextUuid());
        self::assertEquals('00000000-0000-0000-0000-000000000008', $this->nextUuid());
        self::assertEquals('00000000-0000-0000-0000-000000000009', $this->nextUuid());
        self::assertEquals('00000000-0000-0000-0000-00000000000a', $this->nextUuid());
        self::assertEquals('00000000-0000-0000-0000-00000000000b', $this->nextUuid());
        self::assertEquals('00000000-0000-0000-0000-00000000000c', $this->nextUuid());
        self::assertEquals('00000000-0000-0000-0000-00000000000d', $this->nextUuid());
        self::assertEquals('00000000-0000-0000-0000-00000000000e', $this->nextUuid());
        self::assertEquals('00000000-0000-0000-0000-00000000000f', $this->nextUuid());
        self::assertEquals('00000000-0000-0000-0000-000000000010', $this->nextUuid());
    }

    public function testAssertIsUuid(): void
    {
        $uuid = (string) Uuid::v4();
        $noUuid = 'foo';

        self::assertIsUuid($uuid);

        try {
            self::assertIsUuid($noUuid);

            self::fail('Expected ExpectationFailedException exception was not thrown');
        } catch (ExpectationFailedException) {
            // @ignoreException
        } catch (Throwable) {
            self::fail('Expected ExpectationFailedException exception was not thrown');
        }
    }

    public function testAssertAndReplaceUuidInJson(): void
    {
        $json = json_encode([
            'foo' => (string) Uuid::v4(),
            'bar' => 'baz',
        ]);

        $replacedJson = self::assertAndReplaceUuidInJson($json, 'foo');

        self::assertJson($replacedJson);
        self::assertJsonStringEqualsJsonString(
            '{"foo":"00000000-0000-0000-0000-000000000000","bar":"baz"}',
            $replacedJson,
        );
    }

    public function testAssertAndReplaceUuidInJsonWithNullUuidValueDoesNotReplaceUuid(): void
    {
        $json = json_encode([
            'foo' => null,
            'bar' => 'baz',
        ]);

        $replacedJson = self::assertAndReplaceUuidInJson($json, 'foo');

        self::assertJson($replacedJson);
        self::assertJsonStringEqualsJsonString(
            '{"foo":null,"bar":"baz"}',
            $replacedJson,
        );
    }

    public function testAssertAndReplaceUuidInJsonFailsOnInvalidJson(): void
    {
        $json = 'foo';

        try {
            self::assertAndReplaceUuidInJson($json, 'foo');

            self::fail('Expected ExpectationFailedException exception was not thrown');
        } catch (ExpectationFailedException $e) {
            self::assertSame(
                'Failed asserting that a string is valid JSON (Syntax error, malformed JSON).',
                $e->getMessage(),
            );
        } catch (Throwable) {
            self::fail('Expected ExpectationFailedException exception was not thrown');
        }
    }

    public function testAssertAndReplaceUuidInJsonFailsOnInvalidUuid(): void
    {
        $json = json_encode([
            'foo' => 'xxx',
            'bar' => 'baz',
        ]);

        try {
            self::assertAndReplaceUuidInJson($json, 'foo');

            self::fail('Expected ExpectationFailedException exception was not thrown');
        } catch (ExpectationFailedException $e) {
            self::assertSame(
                'Failed asserting that false is true.',
                $e->getMessage(),
            );
        } catch (Throwable) {
            self::fail('Expected ExpectationFailedException exception was not thrown');
        }
    }

    public function testAssertAndReplaceUuidInArray(): void
    {
        $data = [
            'foo' => (string) Uuid::v4(),
            'bar' => 'baz',
        ];

        $replacedData = self::assertAndReplaceUuidInArray($data, 'foo');

        self::assertIsArray($replacedData);
        self::assertSame(
            ['foo' => '00000000-0000-0000-0000-000000000000', 'bar' => 'baz'],
            $replacedData,
        );
    }

    public function testAssertAndReplaceUuidInArrayWithNullUuidValueDoesNotReplaceUuid(): void
    {
        $data = [
            'foo' => null,
            'bar' => 'baz',
        ];

        $replacedData = self::assertAndReplaceUuidInArray($data, 'foo');

        self::assertIsArray($replacedData);
        self::assertSame(
            ['foo' => null, 'bar' => 'baz'],
            $replacedData,
        );
    }

    public function testAssertAndReplaceUuidInArrayFailsOnInvalidJson(): void
    {
        $data = 'foo';

        try {
            self::assertAndReplaceUuidInArray($data, 'foo');

            self::fail('Expected ExpectationFailedException exception was not thrown');
        } catch (ExpectationFailedException $e) {
            self::assertSame('Failed asserting that \'foo\' is of type array.', $e->getMessage());
        } catch (Throwable) {
            self::fail('Expected ExpectationFailedException exception was not thrown');
        }
    }

    public function testAssertAndReplaceUuidInArrayFailsOnInvalidUuid(): void
    {
        $data = [
            'foo' => 'xxx',
            'bar' => 'baz',
        ];

        try {
            self::assertAndReplaceUuidInArray($data, 'foo');

            self::fail('Expected ExpectationFailedException exception was not thrown');
        } catch (ExpectationFailedException $e) {
            self::assertSame(
                'Failed asserting that false is true.',
                $e->getMessage(),
            );
        } catch (Throwable) {
            self::fail('Expected ExpectationFailedException exception was not thrown');
        }
    }
}
