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
use SMSTunnel\FlarumSms\StaffNumbers;

/**
 * What the admin page shows. Whether a key is saved, never the key.
 */
class AdminStateController extends Controller
{
    public function __construct(
        private readonly Secrets $secrets,
        private readonly StaffNumbers $staff
    ) {
    }

    protected function respond(ServerRequestInterface $request, User $actor): array
    {
        $actor->assertAdmin();

        return [
            'hasKey' => $this->secrets->hasApiKey(),
            'staff' => $this->staff->list(),
            'maxStaff' => StaffNumbers::MAX,
        ];
    }
}
