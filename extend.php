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

use Flarum\Api\Resource\ForumResource;
use Flarum\Extend;
use Flarum\Flags\Event\Created as PostFlagged;
use Flarum\Gdpr\Extend\UserData;
use Flarum\Post\Event\Posted;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Notification())
        ->driver(Notification\TextDriver::NAME, Notification\TextDriver::class),

    (new Extend\ApiResource(ForumResource::class))
        ->fields(Api\ForumFields::class),

    (new Extend\Routes('api'))
        ->get('/smstunnel/phone', 'smstunnel.phone.show', Api\Controller\ShowPhoneController::class)
        ->post('/smstunnel/phone/code', 'smstunnel.phone.code', Api\Controller\RequestCodeController::class)
        ->post('/smstunnel/phone/confirm', 'smstunnel.phone.confirm', Api\Controller\ConfirmCodeController::class)
        ->delete('/smstunnel/phone', 'smstunnel.phone.remove', Api\Controller\RemovePhoneController::class)
        ->get('/smstunnel/admin', 'smstunnel.admin.show', Api\Controller\AdminStateController::class)
        ->post('/smstunnel/admin/key', 'smstunnel.admin.key.save', Api\Controller\SaveKeyController::class)
        ->delete('/smstunnel/admin/key', 'smstunnel.admin.key.remove', Api\Controller\RemoveKeyController::class)
        ->post('/smstunnel/admin/test', 'smstunnel.admin.test', Api\Controller\SendTestController::class)
        ->post('/smstunnel/admin/staff', 'smstunnel.admin.staff.add', Api\Controller\AddStaffController::class)
        ->post('/smstunnel/admin/staff/{id:[0-9]+}/confirm', 'smstunnel.admin.staff.confirm', Api\Controller\ConfirmStaffController::class)
        ->delete('/smstunnel/admin/staff/{id:[0-9]+}', 'smstunnel.admin.staff.remove', Api\Controller\RemoveStaffController::class),

    (new Extend\Event())
        ->listen(PostFlagged::class, Listener\AlertOnFlag::class)
        ->listen(Posted::class, Listener\AlertOnPendingPost::class),

    (new Extend\Console())
        ->command(Console\FlushCommand::class)
        ->schedule(Console\FlushCommand::class, fn ($event) => $event->everyFiveMinutes()),

    (new Extend\Conditional())
        ->whenExtensionEnabled('flarum-gdpr', fn () => [
            (new UserData())->addType(Gdpr\TextsData::class),
        ]),
];
