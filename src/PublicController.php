<?php

declare(strict_types=1);

namespace Flex\ShippingBar;

use Flex\Contracts\Http\ResponseFactoryInterface;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};

final readonly class PublicController
{
    public function __construct(private ResponseFactoryInterface $responses) {}

    public function __invoke(ServerRequestInterface $request, array $arguments = []): ResponseInterface
    {
        $hidden = ['config' => ['enabled' => false]];
        if (!(new CommerceGuard())->active()) {
            return $this->responses->json($hidden, 200, ['Cache-Control' => 'no-store']);
        }
        try {
            $settings = new ShippingBarSettings();
            $settings->install();

            return $this->responses->json(['config' => $settings->publicConfig()], 200, ['Cache-Control' => 'no-store']);
        } catch (\Throwable) {
            return $this->responses->json($hidden, 200, ['Cache-Control' => 'no-store']);
        }
    }
}
