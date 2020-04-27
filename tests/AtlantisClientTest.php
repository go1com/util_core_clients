<?php

namespace go1\clients\tests;

use go1\clients\AtlantisClient;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

class AtlantisClientTest extends UtilCoreClientsTestCase
{
    public function testFeatureIsEnabled()
    {
        $options = ['headers' => ['JWT-Private-Key' => 'INTERNAL']];
        $c = $this->getContainer(true);
        $c->extend('client', function () use ($c, $options) {
            $client =
                $this->getMockBuilder(Client::class)
                    ->setMethods(['get'])
                    ->disableOriginalConstructor()
                    ->getMock();

            $client
                ->expects($this->any())
                ->method('get')
                ->willReturnCallback(function (string $url, array $o) use ($c, $options) {
                    $this->assertEquals("{$c['atlantis_url']}/features", $url);
                    $this->assertEquals($options + ['query' => ['jwt' => 'JWT']], $o);


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
        $this->assertTrue($client->isEnabled('houston.apex', 'JWT', $options));
        $this->assertFalse($client->isEnabled('none-existing', 'JWT', $options));
    }

    public function testGetFeatureData()
    {
        $options = ['headers' => ['JWT-Private-Key' => 'INTERNAL']];
        $c = $this->getContainer(true);
        $c->extend('client', function () use ($c, $options) {
            $client =
                $this->getMockBuilder(Client::class)
                    ->setMethods(['get'])
                    ->disableOriginalConstructor()
                    ->getMock();

            $client
                ->expects($this->any())
                ->method('get')
                ->willReturnCallback(function (string $url, array $o) use ($c, $options) {
                    $this->assertEquals("{$c['atlantis_url']}/features", $url);
                    $this->assertEquals($options + ['query' => ['jwt' => 'JWT']], $o);


                    return new Response(200, [], json_encode([
                        'houston.apex' => [
                            'location' => 'AU',
                            '_meta'    => []
                        ]
                    ]));
                });

            return $client;
        });

        /**
         * @var $client AtlantisClient
         */
        $client = $c['go1.client.atlantis'];
        $data = $client->getFeatureData('houston.apex', 'JWT', $options);
        $this->assertEquals([
            'location' => 'AU',
            '_meta'    => []
        ], $data);
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
