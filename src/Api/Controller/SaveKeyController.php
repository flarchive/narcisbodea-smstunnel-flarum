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
use Psr\Http\Message\ServerRequestInterface;
use SMSTunnel\FlarumSms\Secrets;
use SMSTunnel\FlarumSms\TextsError;

class SaveKeyController extends Controller
{
    public function __construct(
        private readonly Secrets $secrets
    ) {
    }

    protected function respond(ServerRequestInterface $request, User $actor): array
    {
        $actor->assertAdmin();

        $key = $this->input($request, 'key');
        $key = is_string($key) ? trim($key) : $key;

        if (! Secrets::validKey($key)) {
            throw new TextsError('invalid_key');
        }

        $this->secrets->saveApiKey($key);

        return ['hasKey' => true];
    }
}
