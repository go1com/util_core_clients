<?php

namespace go1\clients\tests;

use go1\clients\PolicyClient;
use go1\util\queue\Queue;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use RuntimeException;

class PolicyClientTest extends UtilCoreClientsTestCase
{
    public function testCreatePolicyWithException()
    {
        /** @var PolicyClientTest $client */
        $container = $this->getContainer();
        $container->extend('client', function () use ($container) {
            $httpClient = $this
                ->getMockBuilder(Client::class)
                ->disableOriginalConstructor()
                ->setMethods(['request'])
                ->getMock();

            $httpClient
                ->expects($this->any())
                ->method('request')
                ->willReturnCallback(function () {
                    throw new RuntimeException('Internal Server Error', 500);
                });

            return $httpClient;
        });

        $client = $container['go1.client.policy'];
        try {
            $items = [
                [
                    'type'        => 2,
                    'entity_type' => 'portal',
                    'entity_ids'  => [
                        3,
                    ],
                ],
            ];
            
            $client->createPolicy(100, 'lo', 1000, $items);
        } catch (RuntimeException $e) {
            $this->assertEquals(500, $e->getCode());
            $this->assertEquals("Internal Server Error", $e->getMessage());
        }
    }
    
    public function testCreatePolicy()
    {
        /** @var PolicyClientTest $client */
        $container = $this->getContainer(true);
        $container->extend('client', function () use ($container) {
            $httpClient = $this
                ->getMockBuilder(Client::class)
                ->disableOriginalConstructor()
                ->setMethods(['request'])
                ->getMock();

            $httpClient
                ->expects($this->any())
                ->method('request')
                ->willReturnCallback(function (string $method, string $url, $options) use ($container) {
                    $this->assertEquals('PUT', $method);
                    $this->assertEquals("{$container['policy_url']}/100/lo/1000/items", $url);

                    return new Response(200, [], json_encode([
                        'ids' => ['2dn5g1314fgh36176a6vbhci9i9p'],
                    ]));
                });

            return $httpClient;
        });

        $items = [
            [
                'type'        => 2,
                'entity_type' => 'portal',
                'entity_ids'  => [
                    3,
                ],
            ],
        ];
        
        $client = $container['go1.client.policy'];
        $client->createPolicy(100, 'lo', 1000, $items);
    }
}
