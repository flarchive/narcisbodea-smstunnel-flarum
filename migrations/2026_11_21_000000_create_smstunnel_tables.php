<?php

/*
 * This file is part of smstunnel/flarum-sms.
 *
 * Copyright (c) 2026 NARBOWEB SRL
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        // Kept out of Flarum's settings table, whose rows all reach the admin panel's browser.
        $schema->create('smstunnel_secrets', function (Blueprint $table) {
            $table->string('name', 32)->primary();
            $table->text('value');
        });

        $schema->create('smstunnel_member_numbers', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->primary();
            $table->string('number', 16)->unique();
            $table->bigInteger('confirmed_at');

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        $schema->create('smstunnel_staff_numbers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('number', 16)->unique();
            $table->bigInteger('confirmed_at')->nullable();
        });

        $schema->create('smstunnel_codes', function (Blueprint $table) {
            $table->string('owner', 32)->primary();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('number', 16);
            $table->char('hash', 64);
            $table->unsignedTinyInteger('tries')->default(0);
            $table->bigInteger('expires_at')->index();
            $table->bigInteger('sent_at');

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        $schema->create('smstunnel_limits', function (Blueprint $table) {
            $table->char('bucket', 64)->primary();
            $table->unsignedInteger('hits')->default(0);
            $table->bigInteger('expires_at')->index();
        });

        $schema->create('smstunnel_sent', function (Blueprint $table) {
            $table->char('event_key', 64)->primary();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('status', 16);
            $table->bigInteger('created_at')->index();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        $schema->create('smstunnel_alerts', function (Blueprint $table) {
            $table->string('type', 16)->primary();
            $table->unsignedInteger('pending')->default(0);
            $table->bigInteger('last_sent_at')->nullable();
        });
    },

    'down' => function (Builder $schema) {
        foreach (['alerts', 'sent', 'limits', 'codes', 'staff_numbers', 'member_numbers', 'secrets'] as $name) {
            $schema->dropIfExists('smstunnel_'.$name);
        }
    },
];
