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
 * Confirmation codes. A number is only used after whoever holds it typed back a code sent to it.
 *
 * Codes are six digits, stored hashed, valid for 10 minutes and dead after 5 wrong tries.
 */
final class Codes
{
    public const TTL = 600;
    public const TRIES = 5;
    public const RESEND_AFTER = 60;

    private const TABLE = 'smstunnel_codes';

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly Secrets $secrets
    ) {
    }

    /**
     * Seconds to wait before this owner can ask for another code.
     */
    public function waitSeconds(string $owner): int
    {
        $sent = $this->db->table(self::TABLE)->where('owner', $owner)->value('sent_at');

        return $sent === null ? 0 : max(0, (int) $sent + self::RESEND_AFTER - time());
    }

    public function pending(string $owner): bool
    {
        $expires = $this->db->table(self::TABLE)->where('owner', $owner)->value('expires_at');

        return $expires !== null && (int) $expires > time();
    }

    /**
     * Make a code for this owner and number. The code itself is not kept.
     */
    public function issue(string $owner, ?int $userId, #[\SensitiveParameter] string $number): string
    {
        $code = sprintf('%06d', random_int(0, 999999));
        $now = time();

        $this->db->table(self::TABLE)->updateOrInsert(['owner' => $owner], [
            'user_id' => $userId,
            'number' => $number,
            'hash' => $this->hash($owner, $number, $code),
            'tries' => 0,
            'expires_at' => $now + self::TTL,
            'sent_at' => $now,
        ]);

        // A number nobody confirmed doesn't stay here, also on forums that run no scheduler.
        $this->purge();

        return $code;
    }

    /**
     * Check a typed code. Returns the number when it is right, and null in every other case.
     *
     * The try is counted before the comparison, in one statement, so guesses sent in parallel can't get past the limit.
     */
    public function check(string $owner, #[\SensitiveParameter] mixed $code): ?string
    {
        $row = $this->db->table(self::TABLE)->where('owner', $owner)->first();

        if (! $row) {
            return null;
        }

        if ((int) $row->expires_at <= time() || (int) $row->tries >= self::TRIES) {
            $this->forget($owner);

            return null;
        }

        $counted = $this->db->table(self::TABLE)
            ->where('owner', $owner)
            ->where('tries', '<', self::TRIES)
            ->increment('tries');

        if ($counted !== 1 || ! is_string($code) || preg_match('/\A[0-9]{6}\z/', trim($code)) !== 1) {
            $this->closeIfSpent($owner);

            return null;
        }

        if (hash_equals((string) $row->hash, $this->hash($owner, (string) $row->number, trim($code)))) {
            $this->forget($owner);

            return (string) $row->number;
        }

        $this->closeIfSpent($owner);

        return null;
    }

    public function forget(string $owner): void
    {
        $this->db->table(self::TABLE)->where('owner', $owner)->delete();
    }

    public function purge(): void
    {
        $this->db->table(self::TABLE)->where('expires_at', '<=', time() - self::RESEND_AFTER)->delete();
    }

    private function closeIfSpent(string $owner): void
    {
        $this->db->table(self::TABLE)->where('owner', $owner)->where('tries', '>=', self::TRIES)->delete();
    }

    private function hash(string $owner, #[\SensitiveParameter] string $number, #[\SensitiveParameter] string $code): string
    {
        return hash_hmac('sha256', $owner.'|'.$number.'|'.$code, $this->secrets->salt());
    }
}
