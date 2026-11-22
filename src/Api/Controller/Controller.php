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
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\NotAuthenticatedException;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use SMSTunnel\FlarumSms\TextsError;
use Throwable;

abstract class Controller implements RequestHandlerInterface
{
    final public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            return new JsonResponse($this->respond($request, RequestUtil::getActor($request)));
        } catch (TextsError $e) {
            return new JsonResponse(['error' => $e->reason], $e->status);
        } catch (RouteNotFoundException|NotAuthenticatedException|PermissionDeniedException $e) {
            throw $e;
        } catch (Throwable $e) {
            // Flarum's own handler would log this with its message, and a database error quotes the number in its SQL.
            resolve(LoggerInterface::class)->error('[smstunnel-sms] Request failed: '.$e::class);

            return new JsonResponse(['error' => 'generic'], 500);
        }
    }

    abstract protected function respond(ServerRequestInterface $request, User $actor): array;

    protected function input(ServerRequestInterface $request, string $name): mixed
    {
        $body = $request->getParsedBody();

        return is_array($body) ? ($body[$name] ?? null) : null;
    }

    protected function ip(ServerRequestInterface $request): ?string
    {
        $ip = $request->getAttribute('ipAddress');

        return is_string($ip) && $ip !== '' ? $ip : null;
    }

    protected function routeId(ServerRequestInterface $request): int
    {
        return (int) ($request->getQueryParams()['id'] ?? 0);
    }
}
