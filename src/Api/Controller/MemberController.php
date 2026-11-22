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

use Flarum\Http\Exception\RouteNotFoundException;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;
use SMSTunnel\FlarumSms\Members;

/**
 * The member's own number. While member texts are off these routes answer as if they didn't exist.
 */
abstract class MemberController extends Controller
{
    public function __construct(
        protected readonly Members $members
    ) {
    }

    final protected function respond(ServerRequestInterface $request, User $actor): array
    {
        if (! $this->members->available()) {
            throw new RouteNotFoundException();
        }

        $actor->assertRegistered();

        return $this->forMember($request, $actor);
    }

    abstract protected function forMember(ServerRequestInterface $request, User $actor): array;
}
