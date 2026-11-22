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

use Illuminate\Database\ConnectionInterface;
use SMSTunnel\FlarumSms\Job\SendStaffAlert;

/**
 * A post was flagged or waits for approval: tell the staff numbers.
 *
 * At most one text per type in 10 minutes, so a burst of flags can't become a burst of texts: what happens in between
 * is counted in the next text. Staff numbers belong to no account, so nothing can show what they may see in a
 * restricted tag. These texts therefore carry a count and the link to the review page, never words from a post.
 */
final class StaffAlerts
{
    public const FLAGGED = 'flagged';
    public const APPROVAL = 'approval';

    public const COOLDOWN = 600;

    /** Most alerts one staff number gets in a day. */
    public const NUMBER_DAY = 40;

    private const TABLE = 'smstunnel_alerts';

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly Config $config,
        private readonly Secrets $secrets,
        private readonly StaffNumbers $staff,
        private readonly Limits $limits,
        private readonly SmsClient $client,
        private readonly Wording $wording,
        private readonly Later $later
    ) {
    }

    public function enabled(string $type): bool
    {
        $on = $type === self::APPROVAL ? $this->config->approvalAlertsOn() : $this->config->flagAlertsOn();

        return $on && $this->secrets->hasApiKey();
    }

    public function event(string $type): void
    {
        if (! $this->enabled($type) || ! $this->staff->confirmed()) {
            return;
        }

        $this->db->table(self::TABLE)->insertOrIgnore(['type' => $type, 'pending' => 0, 'last_sent_at' => null]);
        $this->db->table(self::TABLE)->where('type', $type)->increment('pending');

        $this->later->job(new SendStaffAlert($type));
    }

    /**
     * Send what is waiting for this type, if the last text is old enough.
     */
    public function flush(string $type): void
    {
        if (! $this->enabled($type)) {
            return;
        }

        $claim = $this->claim($type);

        if ($claim === null) {
            return;
        }

        $text = $this->wording->staff($type, $claim['count']);

        foreach ($this->staff->confirmed() as $number) {
            $hash = hash('sha256', $this->wording->forumId().'|alert|'.$type.'|'.$claim['at'].'|'.$number);

            if (! $this->limits->take('staff-day:'.$number, self::NUMBER_DAY, 86400)) {
                continue;
            }

            $result = $this->client->send($number, $text, 'flarum-alert-'.$hash);

            // The key's daily limit is final: the other numbers would only get the same refusal.
            if ($result->status === SendResult::CAP) {
                return;
            }
        }
    }

    /**
     * Take what is waiting, in one transaction, so a job that runs twice can't send the same count twice.
     *
     * @return array{count: int, at: int}|null
     */
    private function claim(string $type): ?array
    {
        return $this->db->transaction(function () use ($type): ?array {
            $row = $this->db->table(self::TABLE)->where('type', $type)->lockForUpdate()->first();

            if (! $row || (int) $row->pending < 1) {
                return null;
            }

            $now = time();

            if ($row->last_sent_at !== null && $now - (int) $row->last_sent_at < self::COOLDOWN) {
                return null;
            }

            $this->db->table(self::TABLE)->where('type', $type)->update(['pending' => 0, 'last_sent_at' => $now]);

            return ['count' => (int) $row->pending, 'at' => $now];
        });
    }
}
