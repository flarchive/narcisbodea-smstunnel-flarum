<?php

/*
 * This file is part of smstunnel/flarum-sms.
 *
 * Copyright (c) 2026 NARBOWEB SRL
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SMSTunnel\FlarumSms\Api\Controller;

use Flarum\User\User;
use Illuminate\Support\Str;
use Psr\Http\Message\ServerRequestInterface;
use SMSTunnel\FlarumSms\Limits;
use SMSTunnel\FlarumSms\Numbers;
use SMSTunnel\FlarumSms\Secrets;
use SMSTunnel\FlarumSms\SmsClient;
use SMSTunnel\FlarumSms\TextsError;
use SMSTunnel\FlarumSms\Wording;

/**
 * One fixed test text to one number. The admin sees the outcome, not what the API said in words.
 */
class SendTestController extends Controller
{
    private const PER_HOUR = 5;

    public function __construct(
        private readonly Secrets $secrets,
        private readonly Limits $limits,
        private readonly SmsClient $client,
        private readonly Wording $wording
    ) {
    }

    protected function respond(ServerRequestInterface $request, User $actor): array
    {
        $actor->assertAdmin();

        $number = Numbers::parse($this->input($request, 'number'));

        if ($number === null) {
            throw new TextsError('invalid_number');
        }

        if (! $this->secrets->hasApiKey()) {
            throw new TextsError('no_key');
        }

        if (! $this->limits->take('test-hour', self::PER_HOUR, 3600)) {
            throw new TextsError('too_many', 429);
        }

        $result = $this->client->send($number, $this->wording->test(), 'flarum-test-'.Str::uuid()->toString());

        return ['result' => $result->status];
    }
}
