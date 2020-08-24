<?php

namespace go1\clients;

use go1\util\user\UserHelper;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

trait BearerClientTrait
{
    /** @var Client */
    private Client $client;

    public function withBearerToken(string $token): self
    {
        $retval = clone $this;
        $retval->setBearerToken($token);

        return $retval;
    }

    public function withRootJwt(): self
    {
        return $this->withBearerToken(UserHelper::ROOT_JWT);
    }

    private function setBearerToken(string $token)
    {
        $this->client = clone $this->client;
        $this->client->__construct(array_merge_recursive(
            $this->client->getConfig(),
            [
                RequestOptions::HEADERS => ['Authorization' => 'Bearer ' . $token],
            ]
        ));
    }
}
