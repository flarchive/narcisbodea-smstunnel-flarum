<?php

/*
 * This file is part of smstunnel/flarum-sms.
 *
 * Copyright (c) 2026 NARBOWEB SRL
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SMSTunnel\FlarumSms\Job;

use Flarum\Queue\AbstractJob;
use Psr\Log\LoggerInterface;
use SMSTunnel\FlarumSms\StaffAlerts;
use Throwable;

/**
 * One try: a text is never sent again on its own.
 */
class SendStaffAlert extends AbstractJob
{
    public int $tries = 1;

    public function __construct(
        public string $type
    ) {
        parent::__construct();
    }

    public function handle(StaffAlerts $alerts, LoggerInterface $log): void
    {
        try {
            $alerts->flush($this->type);
        } catch (Throwable $e) {
            // Not rethrown: the queue would log the message, which can quote a number, and could run the job again.
            $log->error('[smstunnel-sms] A staff alert failed: '.$e::class);
        }
    }
}
