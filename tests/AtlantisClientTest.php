<?php

namespace go1\clients\tests;

use go1\clients\AtlantisClient;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

class AtlantisClientTest extends UtilCoreClientsTestCase
{
    public function testOk()
    {
        $c = $this->getContainer(true);
        $c->extend('client', function () use ($c) {
            $client =
                $this->getMockBuilder(Client::class)
                    ->setMethods(['get'])
                    ->disableOriginalConstructor()
                    ->getMock();

            $client
                ->expects($this->any())
                ->method('get')
                ->willReturnCallback(function (string $url, array $options) use ($c) {
                    $this->assertEquals("{$c['atlantis_url']}/features", $url);
                    $this->assertEquals(['query' => ['jwt' => 'JWT']], $options);


                    return new Response(200, [], json_encode([
                        'houston.apex' => [
                            '_meta' => []
                        ]
                    ]));
                });

            return $client;
        });

        /**
         * @var $client AtlantisClient
         */
        $client = $c['go1.client.atlantis'];
        $this->assertTrue($client->isEnabled('houston.apex', 'JWT'));
        $this->assertFalse($client->isEnabled('none-existing', 'JWT'));
    }

    public function testBadRequest()
    {
        $c = $this->getContainer(true);
        $c->extend('client', function () use ($c) {
            $client =
                $this->getMockBuilder(Client::class)
                    ->setMethods(['get'])
                    ->disableOriginalConstructor()
                    ->getMock();

            $client
                ->expects($this->any())
                ->method('get')
                ->willReturnCallback(function (string $url, array $options) use ($c) {
                    $this->assertStringContainsString("{$c['atlantis_url']}/features", $url);

                    return new Response(400, []);
                });

            return $client;
        });

        /**
         * @var $client AtlantisClient
         */
        $client = $c['go1.client.atlantis'];
        $this->assertFalse($client->isEnabled('houston.apex'));
    }
}
