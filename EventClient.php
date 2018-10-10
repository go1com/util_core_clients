<?php

namespace go1\clients;

use Exception;
use go1\util\user\UserHelper;
use GuzzleHttp\Client;

class EventClient
{
    private $client;
    private $eventUrl;

    public function __construct(Client $client, string $eventUrl)
    {
        $this->client = $client;
        $this->eventUrl = $eventUrl;
    }

    public function load(int $id): ?object
    {
        $jwt = UserHelper::ROOT_JWT;
        $res = $this->client->get("$this->eventUrl/events/{$id}?jwt=$jwt", ['http_errors' => false]);
        if (200 == $res->getStatusCode()) {
            return json_decode($res->getBody()->getContents());
        }

        return null;
    }

    public function getAvailableSeats(int $id): int
    {
        $event = self::load($id);
        if (!$event) {
            throw new Exception('Event not found');
        }

        return $event->available_seats ?? 0;
    }
}
