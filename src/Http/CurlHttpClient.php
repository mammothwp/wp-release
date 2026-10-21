<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Http;

use MammothWP\WPRelease\Process\CommandRunner;
use Symfony\Component\Filesystem\Filesystem;

/**
 * HttpClient that shells out to curl through the shared command runner.
 */
class CurlHttpClient implements HttpClient
{
    /**
     * @param CommandRunner $runner Process wrapper.
     * @param Filesystem    $fs     Filesystem helper.
     */
    public function __construct(private readonly CommandRunner $runner, private readonly Filesystem $fs = new Filesystem())
    {
    }

    /**
     * @inheritDoc
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null, ?string $bodyFile = null): HttpResponse
    {
        $this->runner->requireTool('curl');
        $out = $this->fs->tempnam(sys_get_temp_dir(), 'mmwp-http-');
        $tempBody = null;
        $command = ['curl', '-sS', '-L', '-X', $method, '-o', $out, '-w', '%{http_code}', '-H', 'User-Agent: mmwp-release'];
        foreach ($headers as $name => $value) {
            array_push($command, '-H', $name . ': ' . $value);
        }
        if ($body !== null && $bodyFile === null) {
            $tempBody = $this->fs->tempnam(sys_get_temp_dir(), 'mmwp-body-');
            $this->fs->dumpFile($tempBody, $body);
            $bodyFile = $tempBody;
        }
        if ($bodyFile !== null) {
            array_push($command, '--data-binary', '@' . $bodyFile);
        }
        $command[] = $url;
        $result = $this->runner->run($command);
        $response = new HttpResponse((int) $result->stdout, (string) file_get_contents($out));
        $this->fs->remove(array_filter([$out, $tempBody]));
        return $response;
    }
}
