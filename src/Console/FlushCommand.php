<?php

/*
 * This file is part of smstunnel/flarum-sms.
 *
 * Copyright (c) 2026 NARBOWEB SRL
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SMSTunnel\FlarumSms\Console;

use Flarum\Console\AbstractCommand;
use SMSTunnel\FlarumSms\Codes;
use SMSTunnel\FlarumSms\Limits;
use SMSTunnel\FlarumSms\SentLog;
use SMSTunnel\FlarumSms\StaffAlerts;

/**
 * Runs from Flarum's scheduler: sends staff alerts held back by the 10-minute limit and clears expired rows.
 * Without a scheduler, the next flag or held post sends what is waiting.
 */
class FlushCommand extends AbstractCommand
{
    public function __construct(
        private readonly StaffAlerts $alerts,
        private readonly Limits $limits,
        private readonly Codes $codes,
        private readonly SentLog $sent
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('smstunnel:flush')
            ->setDescription('Send held staff alerts and clear expired SMSTunnel rows');
    }

    protected function fire(): int
    {
        $this->alerts->flush(StaffAlerts::FLAGGED);
        $this->alerts->flush(StaffAlerts::APPROVAL);

        $this->limits->purge();
        $this->codes->purge();
        $this->sent->purge();

        return self::SUCCESS;
    }
}
