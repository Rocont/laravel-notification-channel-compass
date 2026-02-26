<?php

namespace Rocont\CompassChannel\Tests\Unit;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Mockery;
use PHPUnit\Framework\TestCase;
use Rocont\CompassChannel\CompassChannel;
use Rocont\CompassChannel\Support\CompassClient;
use Rocont\CompassChannel\Support\CompassClientFactory;

class CompassChannelTest extends TestCase
{
    private CompassClientFactory|Mockery\MockInterface $factory;
    private Repository|Mockery\MockInterface $config;
    private CompassClient|Mockery\MockInterface $client;
    private CompassChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = Mockery::mock(CompassClientFactory::class);
        $this->config = Mockery::mock(Repository::class);
        $this->client = Mockery::mock(CompassClient::class);

        $this->config->shouldReceive('get')
            ->with('compass.bots.main.token')
            ->andReturn('test-token')
            ->byDefault();
        $this->config->shouldReceive('get')
            ->with('compass.base_url')
            ->andReturn('https://example.com/api/v3/')
            ->byDefault();
        $this->config->shouldReceive('get')
            ->with('compass.timeout', 10)
            ->andReturn(10)
            ->byDefault();

        $this->factory->shouldReceive('make')
            ->andReturn($this->client)
            ->byDefault();

        $this->channel = new CompassChannel($this->factory, $this->config, 'main');
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    private function makeNotifiable(mixed $route = null): object
    {
        return new class($route) {
            use Notifiable;

            public function __construct(private readonly mixed $route)
            {
            }

            public function routeNotificationForCompass($notification): mixed
            {
                return $this->route;
            }
        };
    }

    private function makeNotification(array $data): Notification
    {
        return new class($data) extends Notification {
            public function __construct(private readonly array $data)
            {
            }

            public function toCompass($notifiable): array
            {
                return $this->data;
            }
        };
    }

    public function test_send_to_user(): void
    {
        $this->client->shouldReceive('postJson')
            ->with('user/send', Mockery::on(fn($d) => $d['user_id'] === 123 && $d['type'] === 'text'))
            ->once()
            ->andReturn(['message_id' => 'msg-1']);

        $result = $this->channel->send(
            $this->makeNotifiable(),
            $this->makeNotification(['user_id' => 123, 'text' => 'hi'])
        );

        $this->assertSame('msg-1', $result);
    }

    public function test_send_to_group(): void
    {
        $this->client->shouldReceive('postJson')
            ->with('group/send', Mockery::on(fn($d) => $d['group_id'] === 'grp-1'))
            ->once()
            ->andReturn(['message_id' => 'msg-2']);

        $result = $this->channel->send(
            $this->makeNotifiable(),
            $this->makeNotification(['group_id' => 'grp-1', 'text' => 'hello group'])
        );

        $this->assertSame('msg-2', $result);
    }

    public function test_send_to_thread(): void
    {
        $this->client->shouldReceive('postJson')
            ->with('thread/send', Mockery::on(fn($d) => $d['message_id'] === 'thread-1'))
            ->once()
            ->andReturn(['message_id' => 'msg-3']);

        $result = $this->channel->send(
            $this->makeNotifiable(),
            $this->makeNotification(['message_id' => 'thread-1', 'text' => 'reply'])
        );

        $this->assertSame('msg-3', $result);
    }

    public function test_returns_null_without_to_compass(): void
    {
        $notification = new class extends Notification {
        };

        $result = $this->channel->send($this->makeNotifiable(), $notification);

        $this->assertNull($result);
    }

    public function test_throws_without_recipient(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id, group_id or message_id is required');

        $this->channel->send(
            $this->makeNotifiable(),
            $this->makeNotification(['text' => 'orphan'])
        );
    }

    public function test_route_numeric_sets_user_id(): void
    {
        $this->client->shouldReceive('postJson')
            ->with('user/send', Mockery::on(fn($d) => $d['user_id'] === 42))
            ->once()
            ->andReturn(['message_id' => 'msg-r1']);

        $result = $this->channel->send(
            $this->makeNotifiable(42),
            $this->makeNotification(['text' => 'via route'])
        );

        $this->assertSame('msg-r1', $result);
    }

    public function test_route_string_sets_group_id(): void
    {
        $this->client->shouldReceive('postJson')
            ->with('group/send', Mockery::on(fn($d) => $d['group_id'] === 'grp-route'))
            ->once()
            ->andReturn(['message_id' => 'msg-r2']);

        $result = $this->channel->send(
            $this->makeNotifiable('grp-route'),
            $this->makeNotification(['text' => 'via route'])
        );

        $this->assertSame('msg-r2', $result);
    }

    public function test_route_array_merges_data(): void
    {
        $this->client->shouldReceive('postJson')
            ->with('user/send', Mockery::on(fn($d) => $d['user_id'] === 99 && $d['text'] === 'hi'))
            ->once()
            ->andReturn(['message_id' => 'msg-r3']);

        $result = $this->channel->send(
            $this->makeNotifiable(['user_id' => 99]),
            $this->makeNotification(['text' => 'hi'])
        );

        $this->assertSame('msg-r3', $result);
    }

    public function test_file_upload_flow(): void
    {
        $this->client->shouldReceive('uploadFile')
            ->with('/tmp/test.pdf')
            ->once()
            ->andReturn('file-xyz');

        $this->client->shouldReceive('postJson')
            ->with('user/send', Mockery::on(fn($d) => $d['file_id'] === 'file-xyz'
                && $d['type'] === 'file'
                && !isset($d['file'])))
            ->once()
            ->andReturn(['message_id' => 'msg-f']);

        $result = $this->channel->send(
            $this->makeNotifiable(),
            $this->makeNotification([
                'user_id' => 1,
                'type' => 'file',
                'file' => '/tmp/test.pdf',
            ])
        );

        $this->assertSame('msg-f', $result);
    }

    public function test_bot_from_notification_data(): void
    {
        $this->config->shouldReceive('get')
            ->with('compass.bots.secondary.token')
            ->andReturn('secondary-token');

        $this->factory->shouldReceive('make')
            ->with(Mockery::on(fn($a) => $a['token'] === 'secondary-token'))
            ->once()
            ->andReturn($this->client);

        $this->client->shouldReceive('postJson')
            ->andReturn(['message_id' => 'msg-b']);

        $result = $this->channel->send(
            $this->makeNotifiable(),
            $this->makeNotification([
                'user_id' => 1,
                'text' => 'from secondary bot',
                'bot' => 'secondary',
            ])
        );

        $this->assertSame('msg-b', $result);
    }
}
