<?php

/*
 * This file is part of smstunnel/flarum-sms.
 *
 * Copyright (c) 2026 NARBOWEB SRL
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SMSTunnel\FlarumSms;

/**
 * Phone numbers: international format only.
 *
 * A number that doesn't match is refused before any request. Nothing tries to repair it: a bare number could be
 * completed with the wrong country, and "+0..." is a way around the block on premium numbers.
 */
final class Numbers
{
    /** \A and \z, not ^ and $: "$" would also accept a number followed by a line break. */
    public const PATTERN = '/\A\+[1-9][0-9]{6,14}\z/';

    public static function valid(mixed $value): bool
    {
        return is_string($value) && preg_match(self::PATTERN, $value) === 1;
    }

    /**
     * The number as typed, without spaces around it, or null when it isn't one valid number.
     */
    public static function parse(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value, " \t");

        return self::valid($value) ? $value : null;
    }

    /**
     * A number with its middle hidden, for screens.
     */
    public static function mask(string $number): string
    {
        $length = strlen($number);

        if ($length < 7) {
            return '****';
        }

        return substr($number, 0, 4).str_repeat('*', $length - 6).substr($number, -2);
    }
}
