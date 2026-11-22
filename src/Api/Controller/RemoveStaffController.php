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
use SMSTunnel\FlarumSms\StaffNumbers;

class RemoveStaffController extends Controller
{
    public function __construct(
        private readonly StaffNumbers $staff
    ) {
    }

    protected function respond(ServerRequestInterface $request, User $actor): array
    {
        $actor->assertAdmin();

        $this->staff->remove($this->routeId($request));

        return ['staff' => $this->staff->list()];
    }
}
