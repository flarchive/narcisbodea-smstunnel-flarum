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

use Flarum\Queue\AbstractJob;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\SyncQueue;
use Throwable;

/**
 * Runs the sending outside the member's request, so a slow answer can't hold up a post being saved.
 *
 * With a real queue the job goes there. Most forums have none (Flarum's default runs jobs inline), and then the work
 * runs right after the response is finished.
 */
final class Later
{
    /** @var callable[] */
    private static array $work = [];

    private static bool $registered = false;

    public function __construct(
        private readonly Container $container
    ) {
    }

    /**
     * A job holding ids only: it can be stored by a real queue.
     */
    public function job(AbstractJob $job): void
    {
        $queue = $this->container->make(Queue::class);

        if ($queue instanceof SyncQueue) {
            $this->afterResponse(fn () => $this->container->call([$job, 'handle']));

            return;
        }

        $queue->push($job);
    }

    /**
     * Work that holds a number or a code, so it must never be written to a queue table.
     */
    public function afterResponse(callable $work): void
    {
        self::$work[] = $work;

        if (! self::$registered) {
            self::$registered = true;
            register_shutdown_function([self::class, 'run']);
        }
    }

    /**
     * @internal
     */
    public static function run(): void
    {
        $work = self::$work;
        self::$work = [];

        if (! $work) {
            return;
        }

        ignore_user_abort(true);

        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            @litespeed_finish_request();
        }

        foreach ($work as $item) {
            try {
                $item();
            } catch (Throwable) {
                // Never let a text break the end of a request. A failed send was already recorded where it happened.
            }
        }
    }
}
