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

/**
 * One row per text attempted: a hash of the event, its outcome and when. No number and no words.
 *
 * The row is written before the request, so a job that runs twice finds it and stops.
 */
final class SentLog
{
    private const TABLE = 'smstunnel_sent';
    private const KEEP = 3456000; // 40 days.

    public function __construct(
        private readonly ConnectionInterface $db
    ) {
    }

    public function reserve(string $eventKey, ?int $userId): bool
    {
        if (random_int(1, 100) === 1) {
            $this->purge();
        }

        return $this->db->table(self::TABLE)->insertOrIgnore([
            'event_key' => $eventKey,
            'user_id' => $userId,
            'status' => 'sending',
            'created_at' => time(),
        ]) === 1;
    }

    public function release(string $eventKey): void
    {
        $this->db->table(self::TABLE)->where('event_key', $eventKey)->delete();
    }

    public function finish(string $eventKey, string $status): void
    {
        $this->db->table(self::TABLE)->where('event_key', $eventKey)->update(['status' => $status]);
    }

    public function purge(): void
    {
        $this->db->table(self::TABLE)->where('created_at', '<', time() - self::KEEP)->delete();
    }
}
