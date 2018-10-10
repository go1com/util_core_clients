<?php

namespace go1\clients\tests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use RuntimeException;

class EventClientsTest extends UtilCoreClientsTestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testGetAvailableSeatsNull()
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
                    $this->assertContains("{$c['event_url']}/events/1", $url);

                    return new Response(200, [], json_encode([
                        "id" => "8",
                        "title" => "string",
                        "portal_id" => 1,
                        "lo_id" => 8,
                        "start_at" => "2018-03-14T02:01:55+00:00",
                        "end_at" => "2018-03-14T02:01:55+00:00",
                        "timezone" => "UTC",
                        "instructor_ids" => [],
                        "description" => "string",
                        "attendee_limit" => 0,
                        "available_seats" => null
                    ]));
                });

            return $client;
        });

        $client = $c['go1.client.event'];
        $this->assertEquals(0, $client->getAvailableSeats(1));
    }

    /**
     * @runInSeparateProcess
     */
    public function testGetAvailableSeats()
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
                    $this->assertContains("{$c['event_url']}/events/1", $url);

                    return new Response(200, [], json_encode([
                        "id" => "8",
                        "title" => "string",
                        "portal_id" => 1,
                        "lo_id" => 8,
                        "start_at" => "2018-03-14T02:01:55+00:00",
                        "end_at" => "2018-03-14T02:01:55+00:00",
                        "timezone" => "UTC",
                        "instructor_ids" => [],
                        "description" => "string",
                        "attendee_limit" => 0,
                        "available_seats" => 20
                    ]));
                });

            return $client;
        });

        $client = $c['go1.client.event'];
        $this->assertEquals(20, $client->getAvailableSeats(1));
    }

    /**
     * @runInSeparateProcess
     * @expectedException     Exception
     * @expectedExceptionMessage Event not found
     */
    public function testGetAvailableSeatsWithEventNotFound()
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
                    $this->assertContains("{$c['event_url']}/events/1", $url);

                    return new Response(200, [], false);
                });

            return $client;
        });


        $client = $c['go1.client.event'];
        $client->getAvailableSeats(1);
    }

    /**
     * @runInSeparateProcess
     */
    public function testGetAvailableSeatsException()
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
                ->willReturnCallback(function () {
                    throw new RuntimeException('Internal Server Error', 500);
                });

            return $client;
        });


        $client = $c['go1.client.event'];
        try {
            $client->getAvailableSeats(1);
        } catch (RuntimeException $e) {
            $this->assertEquals(500, $e->getCode());
            $this->assertEquals("Internal Server Error", $e->getMessage());
        }
    }
}
