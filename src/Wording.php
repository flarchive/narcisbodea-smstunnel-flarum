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

use Flarum\Http\UrlGenerator;
use Flarum\Locale\TranslatorInterface;

/**
 * The fixed wording of every text. The only thing a member can put in a text is a display name, made safe.
 */
final class Wording
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly Config $config,
        private readonly UrlGenerator $url
    ) {
    }

    /**
     * @param string $kind reply or mention.
     */
    public function member(string $kind, ?string $authorName, int $discussionId, int $postNumber, ?string $locale): string
    {
        return $this->inLocale($locale, function () use ($kind, $authorName, $discussionId, $postNumber): string {
            $name = SafeText::name($authorName);

            if ($name === '') {
                $name = $this->translator->trans('smstunnel-sms.lib.text.a_member');
            }

            return $this->translator->trans('smstunnel-sms.lib.text.'.($kind === 'reply' ? 'reply' : 'mention'), [
                'site' => $this->site(),
                'name' => $name,
                'url' => $this->postLink($discussionId, $postNumber),
            ]);
        });
    }

    /**
     * @param string $type flagged or approval.
     */
    public function staff(string $type, int $count): string
    {
        // In the forum's language: this runs inside the request of whoever flagged the post, in their language.
        return $this->inLocale(null, fn (): string => $this->translator->trans(
            'smstunnel-sms.lib.text.'.($type === 'approval' ? 'approval' : 'flagged'),
            [
                'site' => $this->site(),
                'count' => $count,
                'url' => $this->url->to('forum')->base().'/flags',
            ]
        ));
    }

    public function code(bool $staff, #[\SensitiveParameter] string $code, ?string $locale = null): string
    {
        return $this->inLocale($locale, fn (): string => $this->translator->trans(
            'smstunnel-sms.lib.text.'.($staff ? 'code_staff' : 'code_member'),
            ['site' => $this->site(), 'code' => $code]
        ));
    }

    public function test(): string
    {
        return $this->inLocale(null, fn (): string => $this->translator->trans('smstunnel-sms.lib.text.test', ['site' => $this->site()]));
    }

    public function forumId(): string
    {
        return $this->url->to('forum')->base();
    }

    /**
     * A link built from ids: a slug would carry words typed by a member.
     */
    private function postLink(int $discussionId, int $postNumber): string
    {
        return $this->url->to('forum')->base().'/d/'.$discussionId.'/'.$postNumber;
    }

    private function site(): string
    {
        $host = (string) parse_url($this->url->to('forum')->base(), PHP_URL_HOST);

        return SafeText::label($this->config->forumTitle(), $host);
    }

    private function inLocale(?string $locale, callable $make): string
    {
        $before = $this->translator->getLocale();
        $this->translator->setLocale($locale ?: $this->config->defaultLocale());

        try {
            return $make();
        } finally {
            $this->translator->setLocale($before);
        }
    }
}
