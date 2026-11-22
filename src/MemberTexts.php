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

use Flarum\Mentions\Notification\PostMentionedBlueprint;
use Flarum\Mentions\Notification\UserMentionedBlueprint;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Post\Post;
use Flarum\User\User;
use SMSTunnel\FlarumSms\Job\SendMemberText;
use SMSTunnel\FlarumSms\Notification\TextDriver;
use Throwable;

/**
 * Texts to members who turned them on: when someone replies to their post or mentions them by name.
 *
 * Only events addressed to one person come here. Followers of a discussion, group mentions and anything else that can
 * reach many people at once never do.
 */
final class MemberTexts
{
    /** Most people one post can text. */
    public const MAX_PER_POST = 3;

    /** Most texts one author's posts can cause in an hour. */
    public const AUTHOR_HOUR = 10;

    /** Most texts one member gets in an hour. */
    public const MEMBER_HOUR = 3;

    private const HOUR = 3600;
    private const DAY = 86400;

    /** @var array<string, true> Who is already queued for which post, in this process. */
    private static array $queued = [];

    public function __construct(
        private readonly Config $config,
        private readonly Members $members,
        private readonly Limits $limits,
        private readonly SentLog $sent,
        private readonly SmsClient $client,
        private readonly Wording $wording,
        private readonly Later $later
    ) {
    }

    /**
     * A notification was created: queue one job per person, for those who want it and can read the post.
     *
     * @param User[] $recipients
     */
    public function queue(BlueprintInterface $blueprint, array $recipients): void
    {
        if (! $this->members->available()) {
            return;
        }

        $event = $this->describe($blueprint);

        if ($event === null) {
            return;
        }

        [$type, $post] = $event;

        // The list is cut before anyone is looked up: a post can't reach everyone by naming them.
        foreach (array_slice(array_values($recipients), 0, self::MAX_PER_POST) as $user) {
            if (! $user instanceof User || (int) $user->id === (int) $post->user_id) {
                continue;
            }

            $seen = $post->id.':'.$user->id;

            if (isset(self::$queued[$seen])) {
                continue;
            }

            if (! $this->members->canReceive($user) || ! $this->members->wants($user, $type) || ! $this->canSee($post, $user)) {
                continue;
            }

            if (! $this->limits->take('post:'.$post->id, self::MAX_PER_POST, self::DAY)) {
                return;
            }

            if (count(self::$queued) > 1000) {
                self::$queued = [];
            }

            self::$queued[$seen] = true;

            $this->later->job(new SendMemberText((int) $user->id, (int) $post->id, $type));
        }
    }

    /**
     * Send one text. Everything is read and checked again here: the post may have been hidden, moved or unapproved
     * since it was queued, and the member may have turned texts off.
     */
    public function deliver(int $userId, int $postId, string $type): void
    {
        if (! $this->members->available() || ! in_array($type, TextDriver::TYPES, true)) {
            return;
        }

        $user = User::query()->find($userId);
        $post = Post::query()->find($postId);

        if (! $user instanceof User || ! $post instanceof Post || $post->type !== 'comment' || (int) $post->user_id === $userId) {
            return;
        }

        if (! $this->members->canReceive($user) || ! $this->members->wants($user, $type) || ! $this->canSee($post, $user)) {
            return;
        }

        $number = $this->members->numberOf($userId);

        if ($number === null) {
            return;
        }

        // One text per person per post, whichever way they were addressed.
        $eventKey = hash('sha256', $this->wording->forumId().'|post|'.$postId.'|'.$userId);

        if (! $this->sent->reserve($eventKey, $userId)) {
            return;
        }

        if (! $this->takeAllowance($userId, (int) $post->discussion_id, (int) $post->user_id)) {
            $this->sent->release($eventKey);

            return;
        }

        $text = $this->wording->member(
            $type === 'postMentioned' ? 'reply' : 'mention',
            $post->user?->display_name,
            (int) $post->discussion_id,
            (int) $post->number,
            $user->getPreference('locale')
        );

        $result = $this->client->send($number, $text, 'flarum-member-'.$eventKey);

        $this->sent->finish($eventKey, $result->status);
    }

    /**
     * @return array{0: string, 1: Post}|null
     */
    private function describe(BlueprintInterface $blueprint): ?array
    {
        if ($blueprint instanceof UserMentionedBlueprint) {
            return ['userMentioned', $blueprint->post];
        }

        if ($blueprint instanceof PostMentionedBlueprint) {
            return ['postMentioned', $blueprint->reply];
        }

        return null;
    }

    /**
     * Whether this member may read this post right now. Anything that can't be established counts as no.
     */
    private function canSee(Post $post, User $user): bool
    {
        try {
            if ($post->hidden_at !== null) {
                return false;
            }

            $approved = $post->getAttribute('is_approved');

            if ($approved !== null && ! $approved) {
                return false;
            }

            $discussion = $post->discussion;

            if (! $discussion || $discussion->hidden_at !== null) {
                return false;
            }

            return $post->isVisibleTo($user) === true;
        } catch (Throwable) {
            return false;
        }
    }

    private function takeAllowance(int $userId, int $discussionId, int $authorId): bool
    {
        return $this->limits->takeAll([
            ['member-topic:'.$userId.':'.$discussionId, 1, self::HOUR],
            ['member-hour:'.$userId, self::MEMBER_HOUR, self::HOUR],
            ['member-day:'.$userId, $this->config->memberDay(), self::DAY],
            ['author-hour:'.$authorId, self::AUTHOR_HOUR, self::HOUR],
            ['forum-day', $this->config->forumDay(), self::DAY],
        ]);
    }
}
