<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Request;

use function array_pop;
use function count;

final class DataBuilder
{
    /** @var mixed[] */
    private array $data;

    /**
     * @param mixed[] $data
     */
    private function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @param mixed[] $data
     */
    public static function from(array $data): self
    {
        return new self($data);
    }

    /**
     * @return mixed[]
     */
    public function __invoke(): array
    {
        return $this->data;
    }

    /**
     * @param string|int ...$path
     */
    public function without(...$path): self
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

    /**
     * @param mixed      $newValue
     * @param string|int ...$path
     */
    public function with($newValue, ...$path): self
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

    /**
     * @param mixed      $newValue
     * @param string|int ...$path
     */
    public function add($newValue, ...$path): self
    {
        $dataToModify = &$this->data;
        foreach ($path as $index) {
            $dataToModify = &$dataToModify[$index];
        }

        $dataToModify[] = $newValue;

        return $this;
    }
}
