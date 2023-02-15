<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Snapshot;

use DOMDocument;
use PHPUnit\Framework\Constraint\Constraint;
use Throwable;

use function Safe\sprintf;

use const LIBXML_NOERROR;
use const LIBXML_NOWARNING;

// phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint

/** @no-named-arguments Parameter names are not covered by the backward compatibility promise for PHPUnit */
final class IsXml extends Constraint
{
    /**
     * Returns a string representation of the constraint.
     */
    public function toString(): string
    {
        return 'is valid XML';
    }

    /**
     * Evaluates the constraint for parameter $other. Returns true if the
     * constraint is met, false otherwise.
     *
     * @param mixed $other value or object to evaluate
     */
    protected function matches($other): bool
    {
        if ($other === '') {
            return false;
        }

        try {
            $dom = new DOMDocument();

            return $dom->loadXML($other, LIBXML_NOERROR | LIBXML_NOWARNING);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Returns the description of the failure.
     *
     * The beginning of failure messages is "Failed asserting that" in most
     * cases. This method should return the second part of that sentence.
     *
     * @param mixed $other evaluated value or object
     */
    protected function failureDescription($other): string
    {
        if ($other === '') {
            return 'an empty string is valid XML';
        }

        try {
            $error = '';
            $dom = new DOMDocument();
            $dom->loadXML($other, LIBXML_NOERROR | LIBXML_NOWARNING);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        return sprintf(
            '%s is valid XML (%s)',
            $this->exporter()->shortenedExport($other),
            $error,
        );
    }
}
