<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\DevServer\Services;

/**
 * Request handler behind `php -S` for site:devserver (both modes).
 *
 * handle() returns null when the built-in server should serve the file itself;
 * that only happens after the path has been resolved and contained in the docroot.
 */
final class DevServerRouter
{
    public const STATE_PREFIX = '/__staticforge/';
    public const STATE_PATH = '/__staticforge/state';

    private readonly string $root;

    /**
     * @param list<string> $allowedHosts Lowercase Host header values (no port) accepted by the state endpoint
     */
    public function __construct(
        private readonly string $docroot,
        private readonly bool $watch = false,
        private readonly ?string $stateFile = null,
        private readonly array $allowedHosts = [],
        private readonly string $appRoot = '',
        private readonly bool $allowRemote = false
    ) {
        $this->root = rtrim(realpath($docroot) ?: $docroot, '/');
    }

    /**
     * Entry point used by the generated router script. Returns false to let php -S serve natively.
     *
     * @param array<string, mixed> $server
     * @api Called from the generated router script
     */
    public function dispatch(array $server): bool
    {
        $response = $this->handle(
            (string) ($server['REQUEST_METHOD'] ?? 'GET'),
            (string) ($server['REQUEST_URI'] ?? '/'),
            (string) ($server['HTTP_HOST'] ?? '')
        );
        if ($response === null) {
            return false;
        }

        http_response_code($response->status);
        foreach ($response->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $response->body;

        return true;
    }

    public function handle(string $method, string $requestUri, string $hostHeader = ''): ?RouterResponse
    {
        $rawPath = parse_url($requestUri, PHP_URL_PATH);
        $rawPath = is_string($rawPath) ? $rawPath : '';
        $path = rawurldecode($rawPath);

        if (str_contains($path, "\0") || !str_starts_with($path, '/')) {
            return $this->notFound($rawPath);
        }

        if ($this->watch && ($path === rtrim(self::STATE_PREFIX, '/') || str_starts_with($path, self::STATE_PREFIX))) {
            return $this->stateEndpoint($method, $path, $hostHeader);
        }

        $candidate = $this->root . $path;
        if (is_dir($candidate)) {
            $candidate = rtrim($candidate, '/') . '/index.html';
        }

        $file = $this->contained($candidate);
        if ($file === null) {
            return $this->notFound($rawPath);
        }

        if ($this->watch && $this->isHtml($file)) {
            return $this->html(200, (string) file_get_contents($file));
        }

        return null;
    }

    private function contained(string $candidate): ?string
    {
        $real = realpath($candidate);
        if ($real === false || !is_file($real)) {
            return null;
        }
        if (!str_starts_with($real, $this->root . '/')) {
            return null;
        }

        return $real;
    }

    private function isHtml(string $file): bool
    {
        return in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['html', 'htm'], true);
    }

    private function notFound(string $rawPath): RouterResponse
    {
        $page = $this->contained($this->root . '/404.html');
        $body = $page !== null ? (string) file_get_contents($page) : $this->fallbackPage($rawPath);

        return $this->html(404, $body);
    }

    private function html(int $status, string $body): RouterResponse
    {
        $headers = ['Content-Type' => 'text/html; charset=UTF-8'];
        if ($this->watch) {
            $body = $this->inject($body);
            $headers['Content-Length'] = (string) strlen($body);
            $headers['Cache-Control'] = 'no-store';
        }

        return new RouterResponse($status, $headers, $body);
    }

    public function inject(string $html): string
    {
        $tag = ClientScript::tag();
        $pos = strripos($html, '</body>');

        return $pos === false ? $html . $tag : substr($html, 0, $pos) . $tag . substr($html, $pos);
    }

    private function stateEndpoint(string $method, string $path, string $hostHeader): RouterResponse
    {
        $headers = ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store'];

        if (!$this->hostAllowed($hostHeader)) {
            return new RouterResponse(403, $headers, 'Forbidden');
        }
        if ($path !== self::STATE_PATH) {
            return new RouterResponse(404, $headers, 'Not found');
        }
        if ($method !== 'GET') {
            return new RouterResponse(405, $headers + ['Allow' => 'GET'], 'Method not allowed');
        }

        $headers['Content-Type'] = 'application/json';

        return new RouterResponse(200, $headers, json_encode($this->readState(), JSON_UNESCAPED_SLASHES) ?: '{}');
    }

    private function hostAllowed(string $hostHeader): bool
    {
        $host = strtolower(trim($hostHeader));
        if ($this->allowRemote && self::isIpLiteral($host)) {
            return true;
        }
        if (str_starts_with($host, '[')) {
            $end = strpos($host, ']');
            $host = $end === false ? $host : substr($host, 0, $end + 1);
        } elseif (($colon = strpos($host, ':')) !== false) {
            $host = substr($host, 0, $colon);
        }

        return $host !== '' && in_array($host, $this->allowedHosts, true);
    }

    /**
     * DNS rebinding needs a hostname, so a literal IP Host cannot be a rebinding attack.
     */
    private static function isIpLiteral(string $host): bool
    {
        if (preg_match('/^\[([0-9a-f:.]+)\](?::\d{1,5})?$/', $host, $m) === 1) {
            return filter_var($m[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        return preg_match('/^([0-9.]+):\d{1,5}$/', $host, $m) === 1
            && filter_var($m[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    /**
     * @return array{v: int, status: string, error: string}
     */
    private function readState(): array
    {
        $state = ['v' => 0, 'status' => 'ok', 'error' => ''];

        $raw = $this->stateFile !== null ? @file_get_contents($this->stateFile) : false;
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            return $state;
        }

        if (is_int($data['v'] ?? null)) {
            $state['v'] = $data['v'];
        }
        if (in_array($data['status'] ?? null, ['ok', 'building', 'failed'], true)) {
            $state['status'] = $data['status'];
        }
        if (is_string($data['error'] ?? null)) {
            $state['error'] = $this->sanitizeError($data['error']);
        }

        return $state;
    }

    private function sanitizeError(string $error): string
    {
        return ErrorSanitizer::sanitize($error, $this->appRoot);
    }

    private function fallbackPage(string $rawPath): string
    {
        $escaped = htmlspecialchars($rawPath, ENT_QUOTES, 'UTF-8');

        return str_replace('%%URL%%', $escaped, self::FALLBACK_PAGE);
    }

    private const FALLBACK_PAGE = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found | Static Forge</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-align: center; padding: 50px 20px; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { max-width: 600px; background: rgba(255, 255, 255, 0.1); border-radius: 15px; padding: 40px; box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37); border: 1px solid rgba(255, 255, 255, 0.18); }
        h1 { font-size: 4rem; margin: 0 0 20px 0; text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3); }
        h2 { font-size: 1.5rem; margin: 0 0 30px 0; opacity: 0.9; }
        p { font-size: 1.1rem; line-height: 1.6; opacity: 0.8; margin-bottom: 20px; }
        .url { font-family: monospace; background: rgba(0, 0, 0, 0.2); padding: 5px 10px; border-radius: 5px; font-size: 0.9rem; word-break: break-all; }
        a { display: inline-block; background: rgba(255, 255, 255, 0.2); color: white; text-decoration: none; padding: 12px 30px; border-radius: 25px; border: 1px solid rgba(255, 255, 255, 0.3); margin-top: 20px; }
        .dev-note { font-size: 0.9rem; opacity: 0.6; margin-top: 30px; padding-top: 20px; border-top: 1px solid rgba(255, 255, 255, 0.2); }
    </style>
</head>
<body>
    <div class="container">
        <h1>404</h1>
        <h2>Page Not Found</h2>
        <p>The page <span class="url">%%URL%%</span> could not be found.</p>
        <p>It may have been moved, deleted, or you may have entered the wrong URL.</p>
        <a href="/">&larr; Back to Home</a>
        <div class="dev-note">
            <strong>Development Mode:</strong> This 404 page is served by the StaticForge development server.
        </div>
    </div>
</body>
</html>
HTML;
}
