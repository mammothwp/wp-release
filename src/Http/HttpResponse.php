<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Http;

/**
 * Minimal HTTP response.
 */
final class HttpResponse
{
    /**
     * @param int    $status HTTP status code (0 = transport failure).
     * @param string $body   Response body.
     */
    public function __construct(public readonly int $status, public readonly string $body = '')
    {
    }

    /**
     * Whether status is 2xx.
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * Body decoded as a JSON object.
     *
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $data = json_decode($this->body, true);
        return is_array($data) ? $data : [];
    }
}
