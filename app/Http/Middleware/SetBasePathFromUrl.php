<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetBasePathFromUrl
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $appUrl = config('app.url');
        if ($appUrl) {
            $parsedUrl = parse_url($appUrl);

            // Force HTTPS if the configured APP_URL uses it
            if (isset($parsedUrl['scheme']) && $parsedUrl['scheme'] === 'https') {
                URL::forceScheme('https');
            }

            $basePath = rtrim($parsedUrl['path'] ?? '', '/');

            if (!empty($basePath)) {
                URL::forceRootUrl($appUrl);

                // Prepend the base path to REQUEST_URI and SCRIPT_NAME so that
                // the request reflects the correct public-facing URL.
                // Traefik strips the base path prefix before forwarding requests
                // to this container, so we restore it here.
                $server = $request->server->all();
                $server['REQUEST_URI'] = $basePath . ($server['REQUEST_URI'] ?? '/');
                $server['SCRIPT_NAME'] = $basePath . ($server['SCRIPT_NAME'] ?? '/index.php');

                // Duplicate the request to reset cached URI properties
                $request = $request->duplicate(null, null, null, null, null, $server);

                app()->instance('request', $request);
                Facade::clearResolvedInstance('request');
            }

            $response = $next($request);

            if (!empty($basePath) && $this->isHtmlResponse($response)) {
                $this->fixRelativeUrisMissingBasePath($response, $basePath);
            }

            return $response;
        }

        return $next($request);
    }

    /**
     * Laravel's non-absolute URL generation (used internally by Livewire for
     * its "data-update-uri" endpoint) subtracts the request's base path from
     * the generated path. That's correct for apps served directly from a
     * sub-directory, but wrong here: Traefik strips the /oee prefix
     * before forwarding to this container, so a browser resolving a
     * root-relative "/livewire/update" URL would hit the domain root, not
     * this app. Restore the missing prefix in the rendered HTML.
     */
    protected function fixRelativeUrisMissingBasePath(Response $response, string $basePath): void
    {
        $content = $response->getContent();

        if ($content === false || !str_contains($content, 'data-update-uri="/')) {
            return;
        }

        $content = preg_replace(
            '/data-update-uri="\/(?!'.preg_quote(ltrim($basePath, '/'), '/').'\/)/',
            'data-update-uri="'.$basePath.'/',
            $content
        );

        $response->setContent($content);
    }

    protected function isHtmlResponse(Response $response): bool
    {
        return str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }
}
