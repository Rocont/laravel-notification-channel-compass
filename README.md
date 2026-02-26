# Laravel Notification Channel for Compass

[![Latest Version](https://img.shields.io/packagist/v/rocont/laravel-notification-channel-compass.svg)](https://packagist.org/packages/rocont/laravel-notification-channel-compass)
[![Tests](https://github.com/Rocont/laravel-notification-channel-compass/actions/workflows/tests.yml/badge.svg)](https://github.com/Rocont/laravel-notification-channel-compass/actions)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Laravel notification channel for [Compass Userbot API](https://github.com/getCompass/userbot). Send messages to users, groups, and threads via standard Laravel notifications.

## Installation

```bash
composer require rocont/laravel-notification-channel-compass
```

```bash
php artisan vendor:publish --provider="Rocont\CompassChannel\CompassServiceProvider" --tag=config
```

Add to `.env`:

```dotenv
COMPASS_BOT_TOKEN=your-bot-token
COMPASS_BASE_URL=https://userbot.getcompass.com/
```

## Configuration

Published config `config/compass.php`:

```php
return [
    'default' => env('COMPASS_DEFAULT_BOT', 'main'),

    'bots' => [
        'main' => [
            'token' => env('COMPASS_BOT_TOKEN'),
        ],
    ],

    'base_url' => rtrim(env('COMPASS_BASE_URL', 'https://userbot.getcompass.com/'), '/') . '/api/v3/',
    'timeout' => 10,
];
```

Multiple bots are supported — add more keys under `bots` and reference them via `'bot' => 'key_name'` in your notification.

## Usage

### Route notification from a model

```php
class User extends Model
{
    use Notifiable;

    // Return user_id (int) to send as DM
    public function routeNotificationForCompass(): ?int
    {
        return $this->compass_user_id;
    }
}
```

The return value determines the recipient:
- `int` — treated as `user_id` (direct message)
- `string` — treated as `group_id`
- `array` — merged into notification data (`['user_id' => ..., 'group_id' => ...]`)

### Create a notification

```php
class WelcomeNotification extends Notification
{
    public function via($notifiable): array
    {
        return ['compass'];
    }

    public function toCompass($notifiable): array
    {
        return [
            'type' => 'text',
            'text' => "Welcome, {$notifiable->name}!",
        ];
    }
}
```

### Send to a group (on-demand)

```php
Notification::route('compass', ['group_id' => $groupId])
    ->notify(new WelcomeNotification());
```

### Send a file to a thread

```php
public function toCompass($notifiable): array
{
    return [
        'type' => 'file',
        'file' => '/path/to/document.pdf',
        'message_id' => $this->threadId,
    ];
}
```

The file is automatically uploaded via `file/getUrl` and the resulting `file_id` is sent.

### Use a specific bot

```php
public function toCompass($notifiable): array
{
    return [
        'bot' => 'marketing', // key from config('compass.bots')
        'type' => 'text',
        'text' => 'Hello from marketing bot!',
    ];
}
```

## Testing

```bash
composer test
```

## License

MIT
