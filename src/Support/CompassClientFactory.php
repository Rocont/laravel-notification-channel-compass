<?php

namespace Rocont\CompassChannel\Support;

class CompassClientFactory
{
    /** @var array<string, CompassClient> */
    private array $pool = [];

    public function make(array $cfg): CompassClient
    {
        $token = $cfg['token'] ?? null;
        $baseUrl = $cfg['base_url'] ?? null;
        $timeout = $cfg['timeout'] ?? 10;

        if (!$token || !$baseUrl) {
            throw new \InvalidArgumentException('CompassClientFactory: token and base_url are required');
        }

        $key = hash('sha256', $token . '|' . $baseUrl . '|' . $timeout);

        return $this->pool[$key] ??= CompassClient::make([
            'token' => $token,
            'base_url' => rtrim($baseUrl, '/') . '/',
            'timeout' => $timeout,
        ]);
    }
}