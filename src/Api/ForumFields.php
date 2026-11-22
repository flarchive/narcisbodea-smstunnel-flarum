<?php

/*
 * This file is part of smstunnel/flarum-sms.
 *
 * Copyright (c) 2026 NARBOWEB SRL
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SMSTunnel\FlarumSms\Api;

use Flarum\Api\Schema;
use SMSTunnel\FlarumSms\Members;

/**
 * One attribute, present only while member texts are on: with the extension off the forum's payload doesn't change.
 */
class ForumFields
{
    public function __construct(
        private readonly Members $members
    ) {
    }

    public function __invoke(): array
    {
        return [
            Schema\Boolean::make('smstunnelMemberTexts')
                ->visible(fn () => $this->members->available())
                ->get(fn () => true),
        ];
    }
}
