<?php

/*
 * This file is part of smstunnel/flarum-sms.
 *
 * Copyright (c) 2026 NARBOWEB SRL
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SMSTunnel\FlarumSms\Gdpr;

use Flarum\Gdpr\Data\Type;
use SMSTunnel\FlarumSms\Members;

/**
 * Registered only when flarum/gdpr is enabled: a member's number goes in their export and is removed on erasure.
 */
class TextsData extends Type
{
    public static function dataType(): string
    {
        return 'SmsTunnelTexts';
    }

    public static function exportDescription(): string
    {
        return self::staticTranslator()->trans('smstunnel-sms.lib.gdpr.export_description');
    }

    public static function anonymizeDescription(): string
    {
        return self::deleteDescription();
    }

    public static function deleteDescription(): string
    {
        return self::staticTranslator()->trans('smstunnel-sms.lib.gdpr.delete_description');
    }

    public static function piiFields(): array
    {
        return ['number'];
    }

    public function export(): ?array
    {
        $number = resolve(Members::class)->numberOf((int) $this->user->id);

        if ($number === null) {
            return null;
        }

        return ['smstunnel-texts.json' => $this->encodeForExport(['number' => $number])];
    }

    public function anonymize(): void
    {
        $this->delete();
    }

    public function delete(): void
    {
        resolve(Members::class)->forget((int) $this->user->id);
    }
}
