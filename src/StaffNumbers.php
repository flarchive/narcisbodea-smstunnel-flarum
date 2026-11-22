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

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

/**
 * The numbers an admin saved for staff alerts. A number gets alerts only after whoever holds it typed back a code, so a
 * typo can't send a stranger the forum's alerts.
 */
final class StaffNumbers
{
    public const MAX = 10;

    /** Enough for an admin to set up the whole list in one sitting. */
    public const CODES_PER_IP_HOUR = 15;
    public const CODES_PER_FORUM_DAY = 30;
    public const CODES_PER_NUMBER_DAY = 3;

    private const TABLE = 'smstunnel_staff_numbers';

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly Codes $codes,
        private readonly Limits $limits,
        private readonly SmsClient $client,
        private readonly Wording $wording,
        private readonly Later $later
    ) {
    }

    /**
     * For the admin screen: numbers are masked here too.
     *
     * @return array<int, array{id: int, number: string, confirmed: bool}>
     */
    public function list(): array
    {
        return $this->db->table(self::TABLE)->orderBy('id')->get()->map(fn ($row) => [
            'id' => (int) $row->id,
            'number' => Numbers::mask((string) $row->number),
            'confirmed' => $row->confirmed_at !== null,
        ])->all();
    }

    /**
     * @return string[] Confirmed numbers, at most {@see self::MAX}.
     */
    public function confirmed(): array
    {
        return $this->db->table(self::TABLE)
            ->whereNotNull('confirmed_at')
            ->orderBy('id')
            ->limit(self::MAX)
            ->pluck('number')
            ->filter(fn ($number) => Numbers::valid($number))
            ->values()
            ->all();
    }

    /**
     * Save a number and send it a code.
     */
    public function add(?string $ip, #[\SensitiveParameter] mixed $raw): int
    {
        $number = Numbers::parse($raw);

        if ($number === null) {
            throw new TextsError('invalid_number');
        }

        $row = $this->db->table(self::TABLE)->where('number', $number)->first();

        if ($row && $row->confirmed_at !== null) {
            throw new TextsError('already_saved');
        }

        if (! $row && $this->db->table(self::TABLE)->count() >= self::MAX) {
            throw new TextsError('too_many_numbers');
        }

        $id = $row ? (int) $row->id : null;

        if ($id !== null && $this->codes->waitSeconds('staff-'.$id) > 0) {
            throw new TextsError('wait', 429);
        }

        $allowed = $this->limits->takeAll([
            ['staff-code-ip-hour:'.($ip ?: 'unknown'), self::CODES_PER_IP_HOUR, 3600],
            ['code-number:'.$number, self::CODES_PER_NUMBER_DAY, 86400],
            ['staff-code-forum', self::CODES_PER_FORUM_DAY, 86400],
        ]);

        if (! $allowed) {
            throw new TextsError('too_many', 429);
        }

        $id ??= (int) $this->db->table(self::TABLE)->insertGetId(['number' => $number, 'confirmed_at' => null]);

        $code = $this->codes->issue('staff-'.$id, null, $number);
        $text = $this->wording->code(true, $code);
        $key = 'flarum-code-'.Str::uuid()->toString();

        $this->later->afterResponse(fn () => $this->client->send($number, $text, $key, true));

        return $id;
    }

    public function confirm(int $id, #[\SensitiveParameter] mixed $code): void
    {
        $number = $this->codes->check('staff-'.$id, $code);

        if ($number === null) {
            throw new TextsError('invalid_code');
        }

        $this->db->table(self::TABLE)->where('id', $id)->where('number', $number)->update(['confirmed_at' => time()]);
    }

    public function remove(int $id): void
    {
        $this->db->table(self::TABLE)->where('id', $id)->delete();
        $this->codes->forget('staff-'.$id);
    }
}
