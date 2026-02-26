<?php

namespace Rocont\CompassChannel;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Notifications\Notification;
use Rocont\CompassChannel\Support\CompassClientFactory;

class CompassChannel
{
    public function __construct(
        private readonly CompassClientFactory $factory,
        private readonly Repository           $config,
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
            'token' => $this->config->get("compass.bots.$botName.token"),
            'base_url' => $this->config->get('compass.base_url'),
            'timeout' => $this->config->get('compass.timeout', 10),
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
                'CompassChannel: user_id, group_id or message_id is required'
            ),
        };

        $data['type'] ??= 'text';

        $response = $client->postJson($endpoint, $data);

        return $response['message_id'] ?? $response;
    }
}