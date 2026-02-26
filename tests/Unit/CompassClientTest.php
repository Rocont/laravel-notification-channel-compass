<?php

namespace Rocont\CompassChannel\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mockery;
use PHPUnit\Framework\TestCase;
use Rocont\CompassChannel\Exceptions\CompassException;
use Rocont\CompassChannel\Support\CompassClient;

class CompassClientTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    private function makeClient(?Client $http = null): CompassClient
    {
        return new CompassClient(
            $http ?? Mockery::mock(Client::class),
            'test-token',
            'https://example.com/api/v3/'
        );
    }

    public function test_post_json_sends_correct_request(): void
    {
        $http = Mockery::mock(Client::class);
        $http->shouldReceive('post')
            ->once()
            ->with('https://example.com/api/v3/user/send', Mockery::on(function ($opts) {
                return $opts['headers']['Authorization'] === 'bearer=test-token'
                    && $opts['headers']['Accept'] === 'application/json'
                    && $opts['headers']['Content-Type'] === 'application/json'
                    && $opts['json'] === ['user_id' => 1, 'text' => 'hello'];
            }))
            ->andReturn(new Response(200, [], json_encode([
                'status' => 'ok',
                'response' => ['message_id' => 'msg-123'],
            ])));

        $client = $this->makeClient($http);
        $result = $client->postJson('user/send', ['user_id' => 1, 'text' => 'hello']);

        $this->assertSame(['message_id' => 'msg-123'], $result);
    }

    public function test_post_json_throws_on_api_error(): void
    {
        $http = Mockery::mock(Client::class);
        $http->shouldReceive('post')
            ->andReturn(new Response(200, [], json_encode([
                'status' => 'error',
                'response' => ['error_code' => 1001, 'message' => 'Member not found'],
            ])));

        $this->expectException(CompassException::class);
        $this->expectExceptionMessage('Compass API error: Member not found');

        $client = $this->makeClient($http);
        $client->postJson('user/send', []);
    }

    public function test_post_json_throws_on_http_error(): void
    {
        $http = Mockery::mock(Client::class);
        $http->shouldReceive('post')
            ->andThrow(new ConnectException('Connection failed', new Request('POST', '/test')));

        $this->expectException(CompassException::class);
        $this->expectExceptionMessage('[Compass] HTTP error');

        $this->makeClient($http)->postJson('user/send', []);
    }

    public function test_upload_file_full_flow(): void
    {
        $http = Mockery::mock(Client::class);

        // 1. getUrl call
        $http->shouldReceive('post')
            ->with('https://example.com/api/v3/file/getUrl', Mockery::any())
            ->once()
            ->andReturn(new Response(200, [], json_encode([
                'status' => 'ok',
                'response' => ['url' => 'https://upload.example.com/upload'],
            ])));

        // 2. upload call
        $http->shouldReceive('post')
            ->with('https://upload.example.com/upload', Mockery::on(function ($opts) {
                return isset($opts['multipart']);
            }))
            ->once()
            ->andReturn(new Response(200, [], json_encode([
                'file_id' => 'file-abc',
            ])));

        $client = $this->makeClient($http);

        $tmpFile = tempnam(sys_get_temp_dir(), 'compass_test_');
        file_put_contents($tmpFile, 'test content');

        try {
            $fileId = $client->uploadFile($tmpFile);
            $this->assertSame('file-abc', $fileId);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function test_upload_file_throws_when_no_url(): void
    {
        $http = Mockery::mock(Client::class);
        $http->shouldReceive('post')
            ->with('https://example.com/api/v3/file/getUrl', Mockery::any())
            ->once()
            ->andReturn(new Response(200, [], json_encode([
                'status' => 'ok',
                'response' => [],
            ])));

        $this->expectException(CompassException::class);
        $this->expectExceptionMessage('upload URL not received');

        $this->makeClient($http)->uploadFile('/tmp/test.txt');
    }
}
