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
use SMSTunnel\FlarumSms\TextsError;

class AddStaffController extends Controller
{
    public function __construct(
        private readonly Secrets $secrets,
        private readonly StaffNumbers $staff
    ) {
    }

    protected function respond(ServerRequestInterface $request, User $actor): array
    {
        $actor->assertAdmin();

        if (! $this->secrets->hasApiKey()) {
            throw new TextsError('no_key');
        }

        $id = $this->staff->add($this->ip($request), $this->input($request, 'number'));

        return ['id' => $id, 'staff' => $this->staff->list()];
    }
}
