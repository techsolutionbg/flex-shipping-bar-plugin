<?php

declare(strict_types=1);

namespace Flex\ShippingBar;

use Flex\Contracts\Http\ResponseFactoryInterface;
use Flex\Http\RequestInput;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};

final readonly class AdminController
{
    public function __construct(private ResponseFactoryInterface $responses, private RequestInput $input) {}

    public function __invoke(ServerRequestInterface $request, array $arguments = []): ResponseInterface
    {
        $guard = new CommerceGuard();
        $settings = new ShippingBarSettings();
        try {
            if ($guard->active()) {
                $settings->install();
            } elseif ($request->getMethod() !== 'GET') {
                throw new \RuntimeException('Първо активирайте Flex Commerce.', 409);
            }
            if ($request->getMethod() === 'GET') {
                return $this->responses->json([
                    'commerce_active' => $guard->active(),
                    'currency' => $settings->currency(),
                    'settings' => $this->readable($settings),
                ], 200, ['Cache-Control' => 'no-store']);
            }
            $saved = $settings->save($this->input->all($request));

            return $this->responses->json([
                'commerce_active' => true,
                'currency' => $settings->currency(),
                'settings' => $saved,
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (\InvalidArgumentException $error) {
            return $this->responses->json(['error' => ['message' => $error->getMessage()]], $error->getCode() ?: 422, ['Cache-Control' => 'no-store']);
        } catch (\RuntimeException $error) {
            $status = $error->getCode() >= 400 && $error->getCode() < 600 ? $error->getCode() : 409;

            return $this->responses->json(['error' => ['message' => $error->getMessage()]], $status, ['Cache-Control' => 'no-store']);
        }
    }

    /** @return array<string, mixed>|null */
    private function readable(ShippingBarSettings $settings): ?array
    {
        try {
            return $settings->all();
        } catch (\Throwable) {
            return null;
        }
    }
}
