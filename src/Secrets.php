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
 * What must stay on the server, in a table of its own.
 *
 * Flarum sends every row of its settings table to the admin panel in the page itself, so a key saved there would reach
 * a browser. Nothing in this class is ever returned by an API route.
 */
final class Secrets
{
    private const TABLE = 'smstunnel_secrets';
    private const API_KEY = 'api_key';
    private const SALT = 'salt';

    /** Printable ASCII only: the key travels in an HTTP header. */
    private const KEY_PATTERN = '/\A[\x21-\x7E]{16,128}\z/';

    private ?string $salt = null;

    public function __construct(
        private readonly ConnectionInterface $db
    ) {
    }

    public function apiKey(): ?string
    {
        $value = $this->read(self::API_KEY);

        return $value !== null && preg_match(self::KEY_PATTERN, $value) === 1 ? $value : null;
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey() !== null;
    }

    public static function validKey(#[\SensitiveParameter] mixed $key): bool
    {
        return is_string($key) && preg_match(self::KEY_PATTERN, $key) === 1;
    }

    public function saveApiKey(#[\SensitiveParameter] string $key): void
    {
        $this->write(self::API_KEY, $key);
    }

    public function removeApiKey(): void
    {
        $this->db->table(self::TABLE)->where('name', self::API_KEY)->delete();
    }

    /**
     * A random value of this forum, for hashing confirmation codes and limit buckets.
     */
    public function salt(): string
    {
        if ($this->salt !== null) {
            return $this->salt;
        }

        $this->db->table(self::TABLE)->insertOrIgnore([
            'name' => self::SALT,
            'value' => bin2hex(random_bytes(32)),
        ]);

        return $this->salt = (string) $this->read(self::SALT);
    }

    private function read(string $name): ?string
    {
        $value = $this->db->table(self::TABLE)->where('name', $name)->value('value');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function write(string $name, #[\SensitiveParameter] string $value): void
    {
        $this->db->table(self::TABLE)->updateOrInsert(['name' => $name], ['value' => $value]);
    }
}
