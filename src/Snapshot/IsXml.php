<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Snapshot;

use DOMDocument;
use LibXMLError;
use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Util\Exporter;
use Throwable;

use function array_map;
use function implode;
use function libxml_clear_errors;
use function libxml_get_errors;
use function libxml_use_internal_errors;
use function sprintf;
use function trim;

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

        $useInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom = new DOMDocument();
            $dom->loadXML($other);

            $error = implode('; ', array_map(
                static fn (LibXMLError $error): string => sprintf('line %d: %s', $error->line, trim($error->message)),
                libxml_get_errors(),
            ));
        } catch (Throwable $e) {
            $error = $e->getMessage();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($useInternalErrors);
        }

        return sprintf(
            '%s is valid XML (%s)',
            Exporter::shortenedExport($other),
            $error,
        );
    }
}
