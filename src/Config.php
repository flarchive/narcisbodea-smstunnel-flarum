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

use Flarum\Settings\SettingsRepositoryInterface;

/**
 * The switches and limits an admin can change. Everything is off until it is turned on.
 *
 * The API key is not here on purpose: Flarum hands every row of its settings table to the admin panel's browser.
 * See {@see Secrets}.
 */
final class Config
{
    public const PREFIX = 'smstunnel-sms.';

    public const MEMBER_DAY_DEFAULT = 5;
    public const MEMBER_DAY_MAX = 20;
    public const FORUM_DAY_DEFAULT = 100;
    public const FORUM_DAY_MAX = 1000;

    public function __construct(
        private readonly SettingsRepositoryInterface $settings
    ) {
    }

    public function membersOn(): bool
    {
        return $this->flag('member_texts');
    }

    public function flagAlertsOn(): bool
    {
        return $this->flag('staff_flags');
    }

    public function approvalAlertsOn(): bool
    {
        return $this->flag('staff_approval');
    }

    /** Most texts one member can get in a day. */
    public function memberDay(): int
    {
        return $this->bounded('member_day', self::MEMBER_DAY_DEFAULT, self::MEMBER_DAY_MAX);
    }

    /** Most member texts the whole forum can send in a day. */
    public function forumDay(): int
    {
        return $this->bounded('forum_day', self::FORUM_DAY_DEFAULT, self::FORUM_DAY_MAX);
    }

    public function forumTitle(): string
    {
        return (string) $this->settings->get('forum_title');
    }

    public function defaultLocale(): string
    {
        return (string) ($this->settings->get('default_locale') ?: 'en');
    }

    private function flag(string $name): bool
    {
        return (bool) $this->settings->get(self::PREFIX.$name);
    }

    private function bounded(string $name, int $default, int $max): int
    {
        $value = $this->settings->get(self::PREFIX.$name);

        if (! is_numeric($value)) {
            return $default;
        }

        return max(1, min($max, (int) $value));
    }
}
