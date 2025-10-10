# Laravel Notification Channel for Compass

[![Laravel](https://img.shields.io/badge/Laravel-10/11/12-red.svg)](https://laravel.com)  
[![Compass Userbot](https://img.shields.io/badge/Compass-Userbot-blue.svg)](https://github.com/getCompass/userbot)  
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

> Пакет разработан компанией **Rocont** (занимается веб-разработкой на Laravel).

---

## 📦 Что это?

Данный пакет добавляет в Laravel новый канал нотификаций — **`compass`**, который позволяет отправлять сообщения, файлы и реакции через [Compass Userbot API](https://github.com/getCompass/userbot).

Теперь вы можете использовать привычный механизм `Notification` Laravel для:
- отправки сообщений конкретным пользователям (по `user_id`),
- отправки сообщений в группы (по `group_id`),
- написания ответов в треды (`message_id`),
- загрузки и пересылки файлов,
- выбора нужного **бота через конфиг по ключу**.

---

## 🚀 Установка

```bash
composer require vendor/laravel-notification-channel-compass
```

```bash
php artisan vendor:publish --provider="Vendor\\CompassChannel\\CompassServiceProvider" --tag=config
```

---

## ⚙️ Конфигурация `config/compass.php`

```php
<?php

return [

    'default' => env('COMPASS_DEFAULT_BOT', 'main'),

    'bots' => [
        'main' => [
            'token' => env('COMPASS_BOT_MAIN'),
        ],
        'birthday' => [
            'token' => env('COMPASS_BOT_BIRTHDAY'),
        ],
        'marketing' => [
            'token' => env('COMPASS_BOT_MARKETING'),
        ],
    ],

    'base_url' => rtrim(env('COMPASS_BASE_URL', 'https://userbot.getcompass.com/'), '/').'/api/v3/',
    'timeout' => (int) env('COMPASS_HTTP_TIMEOUT', 10),
    'retries' => (int) env('COMPASS_HTTP_RETRIES', 1),
];
```

### Пример `.env`

```dotenv
COMPASS_DEFAULT_BOT=main

COMPASS_BOT_MAIN=xxxx-main-token-xxxx
COMPASS_BOT_BIRTHDAY=xxxx-birthday-token-xxxx
COMPASS_BOT_MARKETING=xxxx-marketing-token-xxxx

COMPASS_BASE_URL=https://userbot.getcompass.com/
COMPASS_HTTP_TIMEOUT=10
COMPASS_HTTP_RETRIES=1
```

---

## 📝 Использование

### В модели пользователя

```php
class User extends Model
{
    use Notifiable;

    public function routeNotificationForCompass($notification = null): ?int
    {
        return $this->compass_user_id;
    }
}
```

---

## 📨 Пример уведомления (по умолчанию)

Если не указать `bot`, будет использован ключ `default` из `config/compass.php`.

```php
class WelcomeOnCompass extends Notification
{
    public function via($notifiable): array
    {
        return ['compass'];
    }

    public function toCompass($notifiable): array
    {
        return [
            'type' => 'text',
            'text' => "Привет, {$notifiable->name}! 🎉 Добро пожаловать.",
        ];
    }
}
```

---

## 🤖 Пример уведомления с выбором бота

```php
class BirthdayNotification extends Notification
{
    public function via($notifiable): array
    {
        return ['compass'];
    }

    public function toCompass($notifiable): array
    {
        $message = "С днём рождения, {$notifiable->name}! 🎉";

        return [
            'bot'  => 'birthday', // ключ из config('compass.bots')
            'type' => 'text',
            'text' => $message,
        ];
    }
}
```

---

## 📡 Отправка через route

```php
Notification::route('compass', [
    'group_id' => env('COMPASS_GROUP_ID'),
])
->notify(new WelcomeOnCompass());
```

```php
Notification::route('compass', [
    'group_id' => env('COMPASS_GROUP_ID'),
])
->notify(new BirthdayNotification());
```

---

## 📎 Отправка файла в тред

```php
Notification::route('compass', ['message_id' => $rootMessageId])
    ->notify(new class('/path/to/file.pdf') extends Notification {
        public function __construct(private string $path) {}
        public function via($n) { return ['compass']; }
        public function toCompass($n): array {
            return [
                'bot' => 'marketing',
                'type' => 'file',
                'file' => $this->path,
            ];
        }
    });
```

---

## 🧪 Тестирование

```php
Http::fake([
    'userbot.getcompass.com/api/v3/*' => Http::response([
        'status'   => 'ok',
        'response' => ['message_id' => 'abc123'],
    ], 200),
]);
```

---

## 📄 Лицензия

MIT License.

---

## 👨‍💻 Авторство

Разработано компанией **Rocont** — мы специализируемся на **веб-разработке на Laravel** и интеграциях.  
Если у вас есть проект или интеграция для Compass — свяжитесь с нами 🚀
