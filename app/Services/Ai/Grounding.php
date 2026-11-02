<?php

namespace App\Services\Ai;

use App\Services\Ai\Simulated\Text;

/**
 * Checks model text against the data it was given: money amounts must be known figures and legal
 * citations must come from the reference answer. Anything else is treated as invented.
 */
final class Grounding
{
    /** Every integer amount found anywhere in the given arrays, as a lookup set. */
    public static function amountsIn(array ...$sources): array
    {
        $amounts = [];
        array_walk_recursive($sources, function ($value) use (&$amounts) {
            if (is_int($value) || (is_float($value) && floor($value) === $value) || (is_string($value) && ctype_digit($value))) {
                $amounts[(int) $value] = true;
            }
        });

        return $amounts;
    }

    /** Pesos written as "$1.234.567", "$1234567" or "$ 1.234.567". */
    public static function amounts(string $text): array
    {
        preg_match_all('/\$\s?(\d[\d.,]*\d|\d)/u', $text, $matches);

        // Amounts with decimals or odd separators are not app figures, so they map to -1 (never known).
        return array_map(fn ($raw) => preg_match('/^(\d+|\d{1,3}(?:\.\d{3})+)$/', $raw) ? (int) str_replace('.', '', $raw) : -1, $matches[1]);
    }

    /** True when every amount in the text is one of the known figures (a leading minus is allowed). */
    public static function amountsAreKnown(string $text, array $known): bool
    {
        foreach (self::amounts($text) as $amount) {
            if (! isset($known[$amount])) {
                return false;
            }
        }

        return true;
    }

    /** Rewrites "$4019918" as "$4.019.918" so model text matches the app's money format. */
    public static function formatMoney(string $text): string
    {
        return preg_replace_callback('/\$\s?(\d{4,})(?![\d.,]*\d)/', fn ($m) => Text::money((int) $m[1]), $text);
    }

    /** Normalised legal citations, e.g. ["art. 65", "ley 50"]. */
    public static function citations(string $text): array
    {
        preg_match_all('/\b(art(?:[íi]culo|\.)?\s*\d+|ley\s+\d+)/iu', $text, $matches);

        return array_values(array_unique(array_map(function ($citation) {
            $plain = Text::fold($citation);

            return str_starts_with($plain, 'ley') ? 'ley '.preg_replace('/\D/', '', $plain) : 'art. '.preg_replace('/\D/', '', $plain);
        }, $matches[1])));
    }

    public static function citationsAreKnown(string $text, string $reference): bool
    {
        return ! array_diff(self::citations($text), self::citations($reference));
    }

    /** True when the folded haystack contains any of the given fragments. */
    public static function mentions(string $haystack, string ...$needles): bool
    {
        $plain = Text::fold($haystack);
        foreach ($needles as $needle) {
            $needle = Text::fold(trim($needle));
            if ($needle !== '' && str_contains($plain, $needle)) {
                return true;
            }
        }

        return false;
    }
}
