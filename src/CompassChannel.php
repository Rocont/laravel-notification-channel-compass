<?php

namespace Rocont\CompassChannel;

use Illuminate\Notifications\Notification;
use Rocont\CompassChannel\Support\CompassClientFactory;

class CompassChannel
{
    public function __construct(
        private readonly CompassClientFactory $factory,
        private readonly string               $defaultBotConfig
    )
    {
    }

    public function send($notifiable, Notification $notification): array|string|null
    {
        if (!method_exists($notification, 'toCompass')) {
            return null;
        }

        $data = (array)$notification->toCompass($notifiable);

        $route = $notifiable->routeNotificationFor('compass', $notification) ?? [];
        if (is_array($route)) {
            $data = $route + $data;
        } elseif (is_numeric($route)) {
            $data['user_id'] ??= (int)$route;
        } elseif (is_string($route) && $route !== '') {
            $data['group_id'] ??= $route;
        }

        $botName = $data['bot'] ?? $this->defaultBotConfig;
        $auth = [
            'token' => config("compass.bots.$botName.token") ?? null,
            'base_url' => config('compass.base_url') ?? null,
            'timeout' => config('compass.timeout') ?? 10,
        ];

        unset($data['bot']);

        $client = $this->factory->make($auth);

        if (($data['type'] ?? 'text') === 'file' && !isset($data['file_id']) && isset($data['file'])) {
            $data['file_id'] = $client->uploadFile($data['file']);
            unset($data['file']);
        }

        $endpoint = match (true) {
            isset($data['user_id']) => 'user/send',
            isset($data['group_id']) => 'group/send',
            isset($data['message_id']) => 'thread/send',
            default => throw new \InvalidArgumentException(
                'CompassChannel: нужен user_id | group_id | message_id'
            ),
        };

        $data['type'] ??= 'text';

        $response = $client->postJson($endpoint, $data);

        return $response['message_id'] ?? $response;
    }
}