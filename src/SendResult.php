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

/**
 * How one send ended. Every outcome is final for this event: nothing here is ever sent a second time.
 *
 * "accepted" means SMSTunnel took the text for the phone. It doesn't mean the text arrived.
 */
final class SendResult
{
    public const ACCEPTED = 'accepted';
    /** The key's daily limit (429 KEY_DAILY_CAP_REACHED). */
    public const CAP = 'cap';
    public const BAD_KEY = 'bad_key';
    public const NO_KEY = 'no_key';
    public const BAD_NUMBER = 'bad_number';
    /** SMSTunnel refused this text. */
    public const REFUSED = 'refused';
    /** No clear answer: the text may or may not have gone out, so it must not be sent again. */
    public const UNCLEAR = 'unclear';

    public function __construct(
        public readonly string $status,
        public readonly int $http = 0
    ) {
    }

    public function accepted(): bool
    {
        return $this->status === self::ACCEPTED;
    }
}
