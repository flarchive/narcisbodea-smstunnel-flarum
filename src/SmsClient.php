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

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The only place that talks to SMSTunnel.
 *
 * One request per text, to a fixed address. It never retries: a timeout or an unclear answer may mean the text is
 * already on its way. Logs carry the outcome and the HTTP status, never a number, a text, a code or the key.
 */
class SmsClient
{
    public const BASE_URL = 'https://smstunnel.io/api/v1';

    private const CONNECT_TIMEOUT = 3;
    private const TIMEOUT = 8;

    private ?ClientInterface $http = null;

    public function __construct(
        private readonly Secrets $secrets,
        private readonly LoggerInterface $log
    ) {
    }

    /**
     * @param string $idempotencyKey The same for every attempt at this one event.
     * @param bool $isCode A confirmation code: goes through the sign-in code route and its own allowance.
     */
    public function send(
        #[\SensitiveParameter] string $to,
        #[\SensitiveParameter] string $text,
        string $idempotencyKey,
        bool $isCode = false
    ): SendResult {
        if (! Numbers::valid($to)) {
            return new SendResult(SendResult::BAD_NUMBER);
        }

        $key = $this->secrets->apiKey();

        if ($key === null) {
            return new SendResult(SendResult::NO_KEY);
        }

        try {
            $response = $this->http()->request('POST', self::BASE_URL.($isCode ? '/sms/send-2fa' : '/sms/send'), [
                'headers' => [
                    'X-API-Key' => $key,
                    'Idempotency-Key' => $idempotencyKey,
                    'Accept' => 'application/json',
                    'User-Agent' => 'smstunnel-flarum-sms',
                ],
                'json' => ['to' => $to, 'message' => $text],
                'http_errors' => false,
                'allow_redirects' => false,
                'connect_timeout' => self::CONNECT_TIMEOUT,
                'timeout' => self::TIMEOUT,
            ]);

            $status = $response->getStatusCode();
            $body = json_decode((string) $response->getBody(), true);
        } catch (Throwable) {
            // The exception's message can quote the request, so it stays out of the log.
            $this->log->warning('[smstunnel-sms] No clear answer from SMSTunnel. The text will not be sent again.');

            return new SendResult(SendResult::UNCLEAR);
        }

        $result = $this->read($status, is_array($body) ? $body : []);

        if (! $result->accepted()) {
            $this->log->warning(sprintf('[smstunnel-sms] Text not sent: %s (HTTP %d).', $result->status, $status));
        }

        return $result;
    }

    private function read(int $status, array $body): SendResult
    {
        if ($status >= 200 && $status < 300) {
            return new SendResult(($body['success'] ?? false) === true ? SendResult::ACCEPTED : SendResult::REFUSED, $status);
        }

        if ($status === 429 && ($body['code'] ?? null) === 'KEY_DAILY_CAP_REACHED') {
            return new SendResult(SendResult::CAP, $status);
        }

        if ($status === 401 || $status === 403) {
            return new SendResult(SendResult::BAD_KEY, $status);
        }

        // A 5xx is stored and answered again for 24 hours under the same key, so it is no reason to try again.
        return new SendResult($status >= 500 ? SendResult::UNCLEAR : SendResult::REFUSED, $status);
    }

    private function http(): ClientInterface
    {
        return $this->http ??= new Client();
    }
}
