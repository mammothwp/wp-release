<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Tests\Support;

use MammothWP\WPRelease\Http\HttpClient;
use MammothWP\WPRelease\Http\HttpResponse;

/**
 * Scripted HttpClient: maps "METHOD url" to a response and records requests.
 */
final class FakeHttp implements HttpClient
{
    /** @var list<string> */
    public array $requests = [];

    /**
     * @param array<string, HttpResponse> $responses Responses keyed by "METHOD url"; unknown requests return 404.
     */
    public function __construct(private array $responses = [])
    {
    }

    /**
     * @inheritDoc
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null, ?string $bodyFile = null): HttpResponse
    {
        $key = $method . ' ' . $url;
        $this->requests[] = $key;
        return $this->responses[$key] ?? new HttpResponse(404);
    }
}
