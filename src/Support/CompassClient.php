<?php

namespace Rocont\CompassChannel\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Rocont\CompassChannel\Exceptions\CompassException;

class CompassClient
{
    public function __construct(
        private readonly Client $http,
        private readonly string $token,
        private readonly string $baseUrl
    )
    {
    }

    public static function make(array $cfg): self
    {
        return new self(
            new Client(['timeout' => $cfg['timeout'] ?? 10]),
            $cfg['token'],
            $cfg['base_url']
        );
    }

    /**
     * @throws GuzzleException|CompassException
     */
    public function uploadFile(string $absolutePath): string
    {
        $meta = $this->postJson('file/getUrl', []);

        if (empty($meta['url'])) {
            $uploadUrl = $meta['upload_url'] ?? null;
        } else {
            $uploadUrl = $meta['url'];
        }

        if (!$uploadUrl) {
            throw new CompassException('Compass: upload URL not received');
        }

        $resp = $this->http->post($uploadUrl, [
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => fopen($absolutePath, 'r'),
                    'filename' => basename($absolutePath),
                ],
            ],
        ]);

        $json = json_decode((string)$resp->getBody(), true);
        $fileId = $json['file_id'] ?? ($json['response']['file_id'] ?? null);

        if (!$fileId) {
            throw new CompassException('Compass: file_id missing after upload');
        }

        return $fileId;
    }

    /**
     * @throws CompassException
     */
    public function postJson(string $endpoint, array $payload): array
    {
        try {
            $resp = $this->http->post($this->baseUrl . ltrim($endpoint, '/'), [
                'headers' => [
                    'Authorization' => 'bearer=' . $this->token,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload
            ]);

            $json = json_decode((string)$resp->getBody(), true);
            if (($json['status'] ?? null) !== 'ok') {
                $apiMessage = $json['response']['message'] ?? 'Unknown error';
                throw new CompassException(
                    "Compass API error: $apiMessage",
                    $json['response']['error_code'] ?? null,
                    $json['response'] ?? []
                );
            }

            return $json['response'] ?? [];
        } catch (GuzzleException $e) {
            throw new CompassException(
                '[Compass] HTTP error',
                null,
                [],
                0,
                $e
            );
        }
    }
}