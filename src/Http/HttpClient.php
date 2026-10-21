<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Http;

/**
 * HTTP transport abstraction (mockable in tests).
 */
interface HttpClient
{
    /**
     * Perform a request.
     *
     * @param string                $method   HTTP method.
     * @param string                $url      URL.
     * @param array<string, string> $headers  Request headers.
     * @param string|null           $body     Request body.
     * @param string|null           $bodyFile File to send as the body (takes precedence over $body).
     * @return HttpResponse
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null, ?string $bodyFile = null): HttpResponse;
}
