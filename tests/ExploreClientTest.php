<?php

namespace go1\clients\tests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use RuntimeException;

class ExploreClientTest extends UtilCoreClientsTestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testCanAccess()
    {
        $c = $this->getContainer();
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
                    $this->assertEquals("{$c['explore_url']}/lo", $url);
                    $this->assertEquals(100, $options['query']['portal']);
                    $this->assertEquals(1000, $options['query']['id'][0]);

                    return new Response(200, [], json_encode([
                        'total' => 1,
                        'hits'  => [
                            [
                                'id' => 518526,
                            ],
                        ]]));
                });

            return $client;
        });

        $client = $c['go1.client.explore'];
        $this->assertTrue($client->canAccess(100, 1000, 'foo'));
    }

    /**
     * @runInSeparateProcess
     */
    public function testCanAccessWithException()
    {
        $c = $this->getContainer();
        $c->extend('client', function () {
            $client =
                $this->getMockBuilder(Client::class)
                     ->setMethods(['get'])
                     ->disableOriginalConstructor()
                     ->getMock();

            $client
                ->expects($this->any())
                ->method('get')
                ->willReturnCallback(function () {
                    throw new RuntimeException('Internal Server Error', 500);
                });

            return $client;
        });

        $client = $c['go1.client.explore'];
        try {
            $client->canAccess(100, 1000, 'foo');
        } catch (RuntimeException $e) {
            $this->assertEquals(500, $e->getCode());
            $this->assertEquals("Internal Server Error", $e->getMessage());
        }
    }

    /**
     * @runInSeparateProcess
     */
    public function testGetLearningObject()
    {
        $c = $this->getContainer();
        $c->extend('client', function () use ($c) {
            $httpClient = $this
                ->getMockBuilder(Client::class)
                ->disableOriginalConstructor()
                ->setMethods(['get'])
                ->getMock();

            $httpClient
                ->expects($this->any())
                ->method('get')
                ->willReturnCallback(function (string $url, array $options) use ($c) {
                    $this->assertEquals("{$c['explore_url']}/lo", $url);
                    $this->assertEquals(1, $options['query']['portal']);
                    $this->assertEquals(2, $options['query']['id'][0]);

                    return new Response(200, [], json_encode([
                        'total' => 1,
                        'hits'  => [
                            [
                                'id' => 518526,
                            ],
                        ]]));
                });

            return $httpClient;
        });

        $client = $c['go1.client.explore'];
        $client->getLearningObject(1, 2);
    }
}
