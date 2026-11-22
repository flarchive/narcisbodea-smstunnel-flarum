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
 * Counters over a fixed window, safe when two requests arrive at once: the last place can only be taken by one of them,
 * because the database applies "add one while below the limit" as a single statement.
 *
 * A bucket's name is stored hashed, so this table holds no number and no IP address.
 */
final class Limits
{
    private const TABLE = 'smstunnel_limits';

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly Secrets $secrets
    ) {
    }

    /**
     * Take one place in this bucket, or return false when it is full.
     */
    public function take(string $name, int $max, int $window): bool
    {
        if ($max < 1) {
            return false;
        }

        $bucket = $this->bucket($name);
        $now = time();

        $this->db->table(self::TABLE)->insertOrIgnore([
            'bucket' => $bucket,
            'hits' => 0,
            'expires_at' => $now + $window,
        ]);

        $this->db->table(self::TABLE)
            ->where('bucket', $bucket)
            ->where('expires_at', '<=', $now)
            ->update(['hits' => 0, 'expires_at' => $now + $window]);

        $taken = $this->db->table(self::TABLE)
            ->where('bucket', $bucket)
            ->where('hits', '<', $max)
            ->increment('hits') === 1;

        if (random_int(1, 100) === 1) {
            $this->purge();
        }

        return $taken;
    }

    /**
     * Take a place in every bucket, or in none of them.
     *
     * @param array<int, array{0: string, 1: int, 2: int}> $wanted Name, most hits, window in seconds.
     */
    public function takeAll(array $wanted): bool
    {
        $taken = [];

        foreach ($wanted as [$name, $max, $window]) {
            if (! $this->take($name, $max, $window)) {
                foreach ($taken as $back) {
                    $this->give($back);
                }

                return false;
            }

            $taken[] = $name;
        }

        return true;
    }

    /**
     * Give a place back, when nothing was sent after all.
     */
    public function give(string $name): void
    {
        $this->db->table(self::TABLE)
            ->where('bucket', $this->bucket($name))
            ->where('hits', '>', 0)
            ->decrement('hits');
    }

    public function purge(): void
    {
        $this->db->table(self::TABLE)->where('expires_at', '<=', time())->delete();
    }

    private function bucket(string $name): string
    {
        return hash_hmac('sha256', $name, $this->secrets->salt());
    }
}
