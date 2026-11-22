<?php

/*
 * This file is part of smstunnel/flarum-sms.
 *
 * Copyright (c) 2026 NARBOWEB SRL
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SMSTunnel\FlarumSms\Notification;

use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\Driver\NotificationDriverInterface;
use Flarum\User\User;
use Illuminate\Contracts\Container\Container;
use Psr\Log\LoggerInterface;
use SMSTunnel\FlarumSms\Config;
use SMSTunnel\FlarumSms\MemberTexts;
use Throwable;

/**
 * The "Text" column of each member's notification grid.
 *
 * It offers two rows only: someone replies to my post, and someone mentions me. Group mentions and posts in followed
 * discussions can reach many people at once, so they have no box in this column.
 */
final class TextDriver implements NotificationDriverInterface
{
    public const NAME = 'smstunnel';

    public const TYPES = ['userMentioned', 'postMentioned'];

    public function __construct(
        private readonly Container $container,
        private readonly Config $config
    ) {
    }

    public function send(BlueprintInterface $blueprint, array $users): void
    {
        if (! $users || ! in_array($blueprint::getType(), self::TYPES, true)) {
            return;
        }

        try {
            $this->container->make(MemberTexts::class)->queue($blueprint, $users);
        } catch (Throwable $e) {
            // A text must never break the post it is about, or the alert and email drivers running next to this one.
            $this->container->make(LoggerInterface::class)->warning('[smstunnel-sms] Could not queue a text: '.$e::class);
        }
    }

    /**
     * While member texts are off the preference doesn't exist, so the API and the grid stay exactly as they were.
     * It is always off by default, whatever another extension asks for.
     */
    public function registerType(string $blueprintClass, array $driversEnabledByDefault): void
    {
        if (! in_array($blueprintClass::getType(), self::TYPES, true)) {
            return;
        }

        try {
            if (! $this->config->membersOn()) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        User::registerPreference(
            User::getNotificationPreferenceKey($blueprintClass::getType(), self::NAME),
            boolval(...),
            false
        );
    }
}
