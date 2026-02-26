<?php

namespace Rocont\CompassChannel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rocont\CompassChannel\Support\CompassClientFactory;
use Rocont\CompassChannel\Support\CompassClient;

class CompassClientFactoryTest extends TestCase
{
    public function test_creates_client_with_valid_params(): void
    {
        $factory = new CompassClientFactory();

        $client = $factory->make([
            'token' => 'test-token',
            'base_url' => 'https://example.com/api/',
            'timeout' => 5,
        ]);

        $this->assertInstanceOf(CompassClient::class, $client);
    }

    public function test_throws_when_token_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new CompassClientFactory())->make([
            'base_url' => 'https://example.com/api/',
        ]);
    }

    public function test_throws_when_base_url_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new CompassClientFactory())->make([
            'token' => 'test-token',
        ]);
    }

    public function test_pooling_returns_same_client(): void
    {
        $factory = new CompassClientFactory();
        $cfg = [
            'token' => 'test-token',
            'base_url' => 'https://example.com/api/',
            'timeout' => 5,
        ];

        $client1 = $factory->make($cfg);
        $client2 = $factory->make($cfg);

        $this->assertSame($client1, $client2);
    }

    public function test_different_params_return_different_clients(): void
    {
        $factory = new CompassClientFactory();

        $client1 = $factory->make([
            'token' => 'token-a',
            'base_url' => 'https://example.com/api/',
        ]);

        $client2 = $factory->make([
            'token' => 'token-b',
            'base_url' => 'https://example.com/api/',
        ]);

        $this->assertNotSame($client1, $client2);
    }
}
