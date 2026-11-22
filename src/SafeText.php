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

use Normalizer;

/**
 * Words typed by a member, made safe to appear in a text that goes out from the forum owner's own number.
 *
 * A text can't carry a link, a domain, a number to call or a disguised version of them just because a member put it
 * in their display name.
 */
final class SafeText
{
    private const TLDS = 'com|net|org|info|biz|io|co|me|app|dev|xyz|top|site|online|shop|store|club|link|click|live|icu|ru|cn|de|uk|fr|ro|it|es|nl|pl|br|in|us|eu|tk|ml|ga|cf|gq|ly|to|cc|tv|ws';

    /**
     * A display name that can go in a text, or '' when nothing safe is left.
     */
    public static function name(?string $name): string
    {
        return self::words((string) $name, 24);
    }

    /**
     * Keeps letters only. Links, schemes, domains (also spelled out or written with look-alike dots), e-mail addresses
     * and every digit are removed, after undoing full-width forms and removing invisible and direction-changing
     * characters, so that none of them can be hidden from these checks.
     */
    public static function words(string $text, int $max): string
    {
        for ($i = 0; $i < 3; $i++) {
            $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($decoded === $text) {
                break;
            }

            $text = $decoded;
        }

        $text = (string) preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $text);
        $text = strip_tags($text);

        if (! preg_match('//u', $text)) {
            return '';
        }

        if (class_exists(Normalizer::class)) {
            $normal = Normalizer::normalize($text, Normalizer::FORM_KC);
            $text = is_string($normal) ? $normal : $text;
        }

        // Full-width letters, digits and punctuation, also on hosts without the intl extension.
        $text = (string) preg_replace_callback('/\xEF\xBC[\x81-\xBF]|\xEF\xBD[\x80-\x9E]/', function (array $match): string {
            $last = ord($match[0][2]);

            return chr($match[0][1] === "\xBC" ? $last - 0x60 : $last - 0x20);
        }, $text);

        // Line breaks become spaces; control, zero-width, direction and blank-looking characters go.
        $text = (string) preg_replace('/[\r\n\t\x{0085}\x{2028}\x{2029}]+/u', ' ', $text);
        $text = (string) preg_replace('/[\p{C}\x{034F}\x{115F}\x{1160}\x{17B4}\x{17B5}\x{180B}-\x{180F}\x{2800}\x{3164}\x{FE00}-\x{FE0F}\x{FFA0}\x{10000}-\x{10FFFF}]+/u', '', $text);

        // Look-alikes of the characters that make up a link.
        $text = (string) preg_replace('/[\x{00B7}\x{0387}\x{05C3}\x{06D4}\x{0701}\x{0702}\x{0964}\x{0965}\x{1362}\x{166E}\x{1803}\x{1809}\x{2022}\x{2024}\x{2027}\x{2219}\x{22C5}\x{2E33}\x{30FB}\x{3002}\x{A4F8}\x{A60E}\x{A6F3}\x{FE12}\x{FE52}\x{FF61}\x{FF65}]/u', '.', $text);
        $text = (string) preg_replace('/[\x{2044}\x{2215}\x{29F8}]/u', '/', $text);
        $text = (string) preg_replace('/[\x{2236}\x{A789}\x{FE55}]/u', ':', $text);
        $text = (string) preg_replace('/\x{FE6B}/u', '@', $text);
        $text = (string) preg_replace('/[\[({<]\s*(?:\.|dot)\s*[\])}>]/iu', '.', $text);

        // Schemes, addresses, and any word with a dot or a double slash inside it.
        $text = (string) preg_replace('~\b\p{L}[\p{L}\p{N}+.\-]*:\S+~u', ' ', $text);
        $text = (string) preg_replace('~\S*@\S*~u', ' ', $text);
        $text = (string) preg_replace('~\S*//\S*~u', ' ', $text);
        $text = (string) preg_replace('~\S*[\p{L}\p{N}]\.[\p{L}\p{N}]\S*~u', ' ', $text);

        // Domains written with spaces or spelled out.
        $text = (string) preg_replace('~[\p{L}\p{N}\-]+\s+\.\s*[\p{L}\p{N}\-]+\S*~u', ' ', $text);
        $text = (string) preg_replace('~[\p{L}\p{N}\-]+\s+dot\s+(?:'.self::TLDS.')\b\S*~iu', ' ', $text);
        $text = (string) preg_replace('~[\p{L}\p{N}\-]+\.\s+(?:com|net|org|info|biz|xyz|io|ru|cn)\b\S*~iu', ' ', $text);
        $text = (string) preg_replace('~[\p{L}\p{N}\-]+\s+(?:com|xyz)\b\S*~iu', ' ', $text);
        $text = (string) preg_replace('~\b(?:https?|www|ftp)\b~iu', ' ', $text);

        // What is left: letters, their marks, spaces, apostrophes and hyphens.
        $text = (string) preg_replace("/[^\p{L}\p{M} '\-]+/u", ' ', $text);
        $text = trim((string) preg_replace('/\s+/u', ' ', $text), " '-");
        $text = trim(trim(mb_substr($text, 0, $max)), " '-");

        return mb_strlen($text) >= 2 ? $text : '';
    }

    /**
     * The forum's name, short and plain, for the start of a text. Set by the admin, so it may keep digits.
     */
    public static function label(string $title, string $host): string
    {
        $name = strip_tags(html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if (! preg_match('//u', $name)) {
            $name = '';
        }

        $name = (string) preg_replace('/\p{C}+/u', ' ', $name);
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        if ($name === '') {
            $name = $host;
        }

        return trim(mb_substr($name, 0, 40));
    }
}
