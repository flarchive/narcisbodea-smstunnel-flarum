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

class ConfirmCodeController extends MemberController
{
    protected function forMember(ServerRequestInterface $request, User $actor): array
    {
        return ['number' => $this->members->confirm($actor, $this->input($request, 'code')), 'pending' => false];
    }
}
