<?php

declare(strict_types=1);

namespace Thbappy7706\SteadFastCourier\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Thbappy7706\SteadFastCourier\Exceptions\ConfigurationException;
use Thbappy7706\SteadFastCourier\Support\SteadFastConfig;

final readonly class VerifyWebhookToken
{
    public function __construct(
        private SteadFastConfig $config,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws ConfigurationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->config->webhookBearerToken;

        if ($token === null) {
            throw ConfigurationException::missingWebhookToken();
        }

        $provided = $request->bearerToken();

        if (! is_string($provided) || ! hash_equals($token, $provided)) {
            return response()->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
