<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Request;

use function array_pop;
use function count;

final class DataBuilder
{
    /** @param mixed[] $data */
    private function __construct(private array $data)
    {
    }

    /** @param mixed[] $data */
    public static function from(array $data): self
    {
        return new self($data);
    }

    /** @return mixed[] */
    public function __invoke(): array
    {
        return $this->data;
    }

    public function without(string|int ...$path): self
    {
        if (count($path) === 0) {
            return $this;
        }

        $indexToUnset = array_pop($path);

        $dataToModify = &$this->data;
        foreach ($path as $index) {
            $dataToModify = &$dataToModify[$index];
        }

        unset($dataToModify[$indexToUnset]);

        return $this;
    }

    public function with(mixed $newValue, string|int ...$path): self
    {
        if (count($path) === 0) {
            return $this;
        }

        $indexToModify = array_pop($path);

        $dataToModify = &$this->data;
        foreach ($path as $index) {
            $dataToModify = &$dataToModify[$index];
        }

        $dataToModify[$indexToModify] = $newValue;

        return $this;
    }

    public function add(mixed $newValue, string|int ...$path): self
    {
        $dataToModify = &$this->data;
        foreach ($path as $index) {
            $dataToModify = &$dataToModify[$index];
        }

        $dataToModify[] = $newValue;

        return $this;
    }
}
