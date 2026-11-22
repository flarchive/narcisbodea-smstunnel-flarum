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
use SMSTunnel\FlarumSms\MemberTexts;
use Throwable;

/**
 * Holds ids only, so a queue table never stores a number or a text. One try: a text is never sent again on its own.
 */
class SendMemberText extends AbstractJob
{
    public int $tries = 1;

    public function __construct(
        public int $userId,
        public int $postId,
        public string $type
    ) {
        parent::__construct();
    }

    public function handle(MemberTexts $texts, LoggerInterface $log): void
    {
        try {
            $texts->deliver($this->userId, $this->postId, $this->type);
        } catch (Throwable $e) {
            // Not rethrown: the queue would log the message, which can quote a number, and could run the job again.
            $log->error('[smstunnel-sms] A member text failed: '.$e::class);
        }
    }
}
