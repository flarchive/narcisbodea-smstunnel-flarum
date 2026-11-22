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

use RuntimeException;

/**
 * A refusal the screen can explain. The code is a fixed word; it never carries a number or a text.
 */
final class TextsError extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly int $status = 422
    ) {
        parent::__construct($reason);
    }
}
