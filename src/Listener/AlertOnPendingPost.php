<?php

/*
 * This file is part of smstunnel/flarum-sms.
 *
 * Copyright (c) 2026 NARBOWEB SRL
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SMSTunnel\FlarumSms\Listener;

use Flarum\Post\Event\Posted;
use Psr\Log\LoggerInterface;
use SMSTunnel\FlarumSms\StaffAlerts;
use Throwable;

/**
 * Flarum's approval extension flags a held post without firing the flag event, so a held post is read from here.
 */
class AlertOnPendingPost
{
    public function __construct(
        private readonly StaffAlerts $alerts,
        private readonly LoggerInterface $log
    ) {
    }

    public function handle(Posted $event): void
    {
        try {
            $approved = $event->post->getAttribute('is_approved');

            if ($approved !== null && ! $approved) {
                $this->alerts->event(StaffAlerts::APPROVAL);
            }
        } catch (Throwable $e) {
            $this->log->warning('[smstunnel-sms] Could not queue a staff alert: '.$e::class);
        }
    }
}
