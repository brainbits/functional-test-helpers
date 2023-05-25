<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Uuid;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\NilUuid;
use Symfony\Component\Uid\Uuid;

use function dechex;
use function Safe\json_decode;
use function Safe\json_encode;
use function str_pad;
use function substr_replace;

use const STR_PAD_LEFT;

/** @mixin TestCase */
trait UuidTrait
{
    private int $lastUuidValue;

    /** @before */
    final protected function setUpUuidTrait(): void
    {
        $this->lastUuidValue = 0;
    }

    final protected function nextUuid(): string
    {
        $this->lastUuidValue ??= 0;

        $uuid = str_pad(dechex(++$this->lastUuidValue), 32, '0', STR_PAD_LEFT);
        $uuid = substr_replace($uuid, '-', 8, 0);
        $uuid = substr_replace($uuid, '-', 13, 0);
        $uuid = substr_replace($uuid, '-', 18, 0);
        $uuid = substr_replace($uuid, '-', 23, 0);

        return (string) Uuid::fromString($uuid);
    }

    /** @param string $actual */
    final protected static function assertIsUuid($actual, string $message = ''): void // phpcs:ignore
    {
        self::assertIsString($actual, $message);
        self::assertTrue(Uuid::isValid($actual), $message);
    }

    /** @param string $jsonData */
    final protected static function assertAndReplaceUuidInJson($jsonData, string $key): string // phpcs:ignore
    {
        self::assertJson($jsonData);

        $jsonData = self::assertAndReplaceUuidInArray(json_decode($jsonData, true), $key);

        return json_encode($jsonData);
    }

    /**
     * @param mixed[] $arrayData
     *
     * @return mixed[]
     */
    final protected static function assertAndReplaceUuidInArray($arrayData, string $key): array // phpcs:ignore
    {
        self::assertIsArray($arrayData);

        if (($arrayData[$key] ?? null) !== null) {
            self::assertIsUuid($arrayData[$key] ?? null);

            $arrayData[$key] = (string) (new NilUuid());
        }

        return $arrayData;
    }
}
