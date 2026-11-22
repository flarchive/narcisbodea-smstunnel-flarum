<?php

/*
 * This file is part of smstunnel/flarum-sms.
 *
 * Copyright (c) 2026 NARBOWEB SRL
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// The rules that need no forum: php tests/run.php

require __DIR__.'/../src/Numbers.php';
require __DIR__.'/../src/SafeText.php';

use SMSTunnel\FlarumSms\Numbers;
use SMSTunnel\FlarumSms\SafeText;

$failed = 0;
$passed = 0;

$check = function (string $name, mixed $got, mixed $want) use (&$failed, &$passed): void {
    if ($got === $want) {
        $passed++;

        return;
    }

    $failed++;
    echo 'FAIL '.$name.': got '.json_encode($got, JSON_UNESCAPED_UNICODE).', wanted '.json_encode($want, JSON_UNESCAPED_UNICODE).PHP_EOL;
};

$valid = ['+1234567', '+40712345678', '+123456789012345', '+19995550100'];

$invalid = [
    '', ' ', '+', '+0712345678', '40712345678', '0712345678', '00407123456789', '+123456', '+1234567890123456',
    '+40 712 345 678', '+40-712-345-678', '+(40)712345678', '+40712345678a', "+40712345678\n", "+40712345678\r\n",
    "\n+40712345678", '+40712345678,+40712345679', '+40712345678;+40712345679', '＋40712345678', '+٤٠٧١٢٣٤٥٦٧',
    12345678, 4.5, null, true, false, ['+40712345678'], (object) [],
];

foreach ($valid as $number) {
    $check('valid '.$number, Numbers::valid($number), true);
    $check('parse '.$number, Numbers::parse(' '.$number.' '), $number);
}

foreach ($invalid as $number) {
    $label = json_encode($number, JSON_UNESCAPED_UNICODE);
    $check('invalid '.$label, Numbers::valid($number), false);
    $check('parse refuses '.$label, Numbers::parse($number), null);
}

$check('mask', Numbers::mask('+40712345678'), '+407******78');
$check('mask short', Numbers::mask('+12345'), '****');

$names = [
    // Ordinary names survive.
    ['Maria Popescu', 'Maria Popescu'],
    ['Ștefan Țurcanu', 'Ștefan Țurcanu'],
    ["O'Brien", "O'Brien"],
    ['Jean-Luc', 'Jean-Luc'],
    ['Владимир', 'Владимир'],
    ['محمد', 'محمد'],
    ['李小龙', '李小龙'],

    // Links and domains, plain and disguised.
    ['www.evil.com', ''],
    ['evil.com', ''],
    ['EVIL.COM', ''],
    ['http://evil.com/x', ''],
    ['https://evil.com', ''],
    ['ftp://evil.example', ''],
    ['evil [dot] com', ''],
    ['evil (dot) com', ''],
    ['evil dot com', ''],
    ['evil . com', ''],
    ['evil. com', ''],
    ['evil com', ''],
    ["evil\u{3002}com", ''],
    ["evil\u{FF0E}com", ''],
    ["evil\u{2024}com", ''],
    ["evil\u{FE52}com", ''],
    ['ｅｖｉｌ．ｃｏｍ', ''],
    ["evil\u{200B}.\u{200B}com", ''],
    ["evil\u{00AD}.com", ''],
    ["evil\u{202E}moc.live", ''],
    ['me@evil.com', ''],
    ["me\u{FE6B}evil.com", ''],
    ['tel:+40712345678', ''],
    ['sms:0712345678', ''],
    ['bit.ly/abc', ''],

    // Digits are never kept, so a text can't carry a number to call.
    ['+40712345678', ''],
    ['0800123456', ''],
    ['call 0800123456', 'call'],
    ['４０７１２３', ''],
    ['٤٠٧١٢٣', ''],

    // Markup and encoded forms.
    ['<b>Bob</b>', 'Bob'],
    ['<script>alert(1)</script>Bob', 'Bob'],
    ['&lt;b&gt;Bob&lt;/b&gt;', 'Bob'],
    ['evil&#46;com', ''],
    ['evil&period;com', ''],
    ['&amp;#46;evil&amp;#46;com', ''],

    // Nothing left, or too short to mean anything.
    ['', ''],
    ['   ', ''],
    ['a', ''],
    ['12', ''],
    ['!!!', ''],
    ["\u{200B}\u{200C}\u{200D}", ''],
    ['🙂🙂🙂', ''],
];

foreach ($names as [$in, $want]) {
    $check('name '.json_encode($in, JSON_UNESCAPED_UNICODE), SafeText::name($in), $want);
}

$check('null name', SafeText::name(null), '');
$check('invalid UTF-8', SafeText::name("Bob\xC3\x28"), '');
$check('a name is cut to 24 characters', mb_strlen(SafeText::name(str_repeat('abcdefghij ', 9))) <= 24, true);

foreach ($names as [$in]) {
    $out = SafeText::name($in);
    $check('no digit in '.json_encode($in, JSON_UNESCAPED_UNICODE), preg_match('/\p{N}/u', $out), 0);
    $check('no dot, slash, colon or @ in '.json_encode($in, JSON_UNESCAPED_UNICODE), preg_match('~[.:/@]~', $out), 0);
}

$check('forum label', SafeText::label('My Forum 24', 'forum.example'), 'My Forum 24');
$check('forum label falls back to the host', SafeText::label('', 'forum.example'), 'forum.example');
$check('forum label has no line breaks', SafeText::label("My\nForum", 'forum.example'), 'My Forum');
$check('forum label is cut', mb_strlen(SafeText::label(str_repeat('x', 60), 'h')), 40);

echo $passed.' passed, '.$failed.' failed'.PHP_EOL;

exit($failed === 0 ? 0 : 1);
