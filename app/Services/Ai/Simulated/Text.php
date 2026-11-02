<?php

namespace App\Services\Ai\Simulated;

use Illuminate\Support\Str;

/** Shared wording helpers for the simulated tasks. */
final class Text
{
    public static function fold(string $text): string
    {
        return Str::lower(Str::ascii($text));
    }

    public static function money(int|float $value): string
    {
        return '$'.number_format((float) $value, 0, ',', '.');
    }

    public static function date(?string $iso): string
    {
        return $iso ? \Illuminate\Support\Carbon::parse($iso)->format('d/m/Y') : '—';
    }

    public static function firstName(string $name): string
    {
        return Str::before(trim($name), ' ') ?: $name;
    }

    /** "1 año y 5 meses", "8 meses", "12 días". */
    public static function tenure(string $from, string $to): string
    {
        $diff = \Illuminate\Support\Carbon::parse($from)->diff(\Illuminate\Support\Carbon::parse($to)->addDay());
        $parts = array_filter([
            $diff->y ? $diff->y.' '.($diff->y === 1 ? 'año' : 'años') : null,
            $diff->m ? $diff->m.' '.($diff->m === 1 ? 'mes' : 'meses') : null,
        ]);

        return $parts ? implode(' y ', $parts) : max(1, $diff->d).' días';
    }

    /** "a término indefinido", "por obra o labor" — reads after the word "contrato". */
    public static function contractKind(string $name): string
    {
        $lower = mb_strtolower($name);

        return match (true) {
            str_starts_with($lower, 'término') => 'a '.$lower,
            str_starts_with($lower, 'obra') => 'por '.$lower,
            default => 'de '.$lower,
        };
    }

    public static function sentenceEnd(string $text): string
    {
        return rtrim($text, '. ').'.';
    }

    public static function percent(int|float $part, int|float $whole): int
    {
        return $whole > 0 ? (int) round($part / $whole * 100) : 0;
    }
}
