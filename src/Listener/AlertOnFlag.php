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

use Flarum\Flags\Event\Created;
use Psr\Log\LoggerInterface;
use SMSTunnel\FlarumSms\StaffAlerts;
use Throwable;

class AlertOnFlag
{
    public function __construct(
        private readonly StaffAlerts $alerts,
        private readonly LoggerInterface $log
    ) {
    }

    public function handle(Created $event): void
    {
        try {
            $this->alerts->event(StaffAlerts::FLAGGED);
        } catch (Throwable $e) {
            $this->log->warning('[smstunnel-sms] Could not queue a staff alert: '.$e::class);
        }
    }
}
