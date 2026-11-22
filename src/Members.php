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

use Carbon\Carbon;
use Flarum\Extension\ExtensionManager;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use SMSTunnel\FlarumSms\Notification\TextDriver;
use Throwable;

/**
 * A member's own number. Every action works on the signed-in member only: there is no user id to pass, so nobody, an
 * administrator included, can turn texts on for someone else or read their number.
 */
final class Members
{
    public const CODES_PER_MEMBER_DAY = 3;
    public const CODES_PER_NUMBER_DAY = 3;
    public const CODES_PER_IP_HOUR = 3;
    public const CODES_PER_IP_DAY = 10;
    public const CODES_PER_FORUM_DAY = 20;

    private const HOUR = 3600;
    private const DAY = 86400;
    private const TABLE = 'smstunnel_member_numbers';

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly Config $config,
        private readonly Secrets $secrets,
        private readonly Codes $codes,
        private readonly Limits $limits,
        private readonly SmsClient $client,
        private readonly Wording $wording,
        private readonly Later $later,
        private readonly ExtensionManager $extensions
    ) {
    }

    /**
     * Whether member texts are turned on and can work: replies and mentions come from the Mentions extension.
     * Anything unclear counts as no.
     */
    public function available(): bool
    {
        try {
            return $this->config->membersOn() && $this->secrets->hasApiKey() && $this->extensions->isEnabled('flarum-mentions');
        } catch (Throwable) {
            return false;
        }
    }

    public function numberOf(int $userId): ?string
    {
        $number = $this->db->table(self::TABLE)->where('user_id', $userId)->value('number');

        return Numbers::valid($number) ? $number : null;
    }

    /**
     * Whether this member turned texts on for this type and has a confirmed number.
     */
    public function wants(User $user, string $type): bool
    {
        if (! in_array($type, TextDriver::TYPES, true)) {
            return false;
        }

        if (! $user->getPreference(User::getNotificationPreferenceKey($type, TextDriver::NAME))) {
            return false;
        }

        return $this->numberOf((int) $user->id) !== null;
    }

    /**
     * A suspended account or one with an unconfirmed email gets no texts and can't add a number.
     */
    public function canReceive(User $user): bool
    {
        if (! $user->is_email_confirmed) {
            return false;
        }

        $until = $user->getAttribute('suspended_until');

        if ($until === null) {
            return true;
        }

        try {
            return Carbon::parse($until)->isPast();
        } catch (Throwable) {
            return false;
        }
    }

    public function state(User $user): array
    {
        $number = $this->numberOf((int) $user->id);

        return [
            'number' => $number === null ? null : Numbers::mask($number),
            'pending' => $this->codes->pending($this->owner($user)),
        ];
    }

    /**
     * Send a code to the number the member typed.
     *
     * The answer is the same whether or not another account holds the number, so this can't be used to look numbers
     * up. The text goes out after the response, so timing doesn't tell them apart either.
     */
    public function requestCode(User $user, ?string $ip, #[\SensitiveParameter] mixed $raw): void
    {
        $number = Numbers::parse($raw);

        if ($number === null) {
            throw new TextsError('invalid_number');
        }

        if (! $this->canReceive($user)) {
            throw new TextsError('not_allowed', 403);
        }

        $owner = $this->owner($user);

        if ($this->codes->waitSeconds($owner) > 0) {
            throw new TextsError('wait', 429);
        }

        $ipKey = $ip ?: 'unknown';

        $allowed = $this->limits->takeAll([
            ['code-member:'.$user->id, self::CODES_PER_MEMBER_DAY, self::DAY],
            ['code-ip-hour:'.$ipKey, self::CODES_PER_IP_HOUR, self::HOUR],
            ['code-ip-day:'.$ipKey, self::CODES_PER_IP_DAY, self::DAY],
            ['code-number:'.$number, self::CODES_PER_NUMBER_DAY, self::DAY],
            ['code-forum', self::CODES_PER_FORUM_DAY, self::DAY],
        ]);

        if (! $allowed) {
            throw new TextsError('too_many', 429);
        }

        $code = $this->codes->issue($owner, (int) $user->id, $number);

        if ($this->takenByOther($number, (int) $user->id)) {
            // A pending row with a code nobody receives: the member's screen looks the same.
            return;
        }

        $text = $this->wording->code(false, $code, $user->getPreference('locale'));
        $key = 'flarum-code-'.Str::uuid()->toString();

        $this->later->afterResponse(fn () => $this->client->send($number, $text, $key, true));
    }

    /**
     * Check the code and save the number.
     *
     * @return string The number, masked.
     */
    public function confirm(User $user, #[\SensitiveParameter] mixed $code): string
    {
        $number = $this->codes->check($this->owner($user), $code);

        if ($number === null || $this->takenByOther($number, (int) $user->id)) {
            throw new TextsError('invalid_code');
        }

        try {
            $this->db->table(self::TABLE)->updateOrInsert(
                ['user_id' => (int) $user->id],
                ['number' => $number, 'confirmed_at' => time()]
            );
        } catch (QueryException) {
            // Another account confirmed this number in the same moment.
            throw new TextsError('invalid_code');
        }

        return Numbers::mask($number);
    }

    /**
     * Remove everything kept about a member's texts.
     */
    public function forget(int $userId): void
    {
        $this->db->table(self::TABLE)->where('user_id', $userId)->delete();
        $this->codes->forget('user-'.$userId);
    }

    private function owner(User $user): string
    {
        return 'user-'.$user->id;
    }

    private function takenByOther(#[\SensitiveParameter] string $number, int $userId): bool
    {
        return $this->db->table(self::TABLE)
            ->where('number', $number)
            ->where('user_id', '!=', $userId)
            ->exists();
    }
}
