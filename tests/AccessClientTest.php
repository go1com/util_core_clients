<?php


namespace go1\clients\tests;


use go1\clients\AccessClient;
use go1\clients\exception\access_client\BadRequestException;
use go1\clients\exception\access_client\ServerErrorException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class AccessClientTest extends UtilCoreClientsTestCase
{
    public function testSignServerJwt()
    {
        $c = $this->getContainer(true);
        $c->extend('client', function () use ($c) {
            $client =
                $this->getMockBuilder(Client::class)
                    ->setMethods(['post'])
                    ->disableOriginalConstructor()
                    ->getMock();

            $client
                ->expects($this->any())
                ->method('post')
                ->willReturnCallback(function (string $url, array $o) use ($c) {
                    $this->assertEquals("{$c['access_url']}/v1.0/serverJwt/sign", $url);
                    $this->assertEquals([
                        'json'    => [
                            'service'  => 'internal',
                            'password' => 'short',
                            'scopes'   => ['access.session.write'],
                            'audience' => 'access',
                        ],
                        'headers' => ['Content-Type' => 'application/json'],
                    ], $o);

                    return new Response(200, [], json_encode(['jwt' => 'JWT']));
                });

            return $client;
        });

        /**
         * @var $client AccessClient
         */
        $client = $c['go1.client.access'];
        $this->assertEquals('JWT', $client->signServerJwt('access', ['access.session.write']));
    }

    public function testSignServerJwtWithBadRequestException()
    {
        $c = $this->getContainer(true);
        $c->extend('client', function () {
            $handlers = new MockHandler([new Response(400, [], json_encode(['message' => 'invalid scope']))]);
            $handlerStack = HandlerStack::create($handlers);
            return new Client(['handler' => $handlerStack]);
        });

        /**
         * @var $client AccessClient
         */
        $client = $c['go1.client.access'];

        $this->expectException(BadRequestException::class);
        $this->assertEquals('JWT', $client->signServerJwt('access', ['access.session.write']));
    }

    public function testSignServerJwtWithServerError()
    {
        $c = $this->getContainer(true);
        $c->extend('client', function () {
            $handlers = new MockHandler([new Response(500, [], json_encode(['message' => 'internal error']))]);
            $handlerStack = HandlerStack::create($handlers);
            return new Client(['handler' => $handlerStack]);
        });

        /**
         * @var $client AccessClient
         */
        $client = $c['go1.client.access'];

        $this->expectException(ServerErrorException::class);
        $this->assertEquals('JWT', $client->signServerJwt('access', ['access.session.write']));
    }

    public function testGenerateSession()
    {
        $c = $this->getContainer(true);
        $c->extend('client', function () use ($c) {
            $client =
                $this->getMockBuilder(Client::class)
                    ->setMethods(['post'])
                    ->disableOriginalConstructor()
                    ->getMock();

            $client
                ->expects($this->any())
                ->method('post')
                ->willReturnCallback(function (string $url, array $o) use ($c) {
                    $this->assertEquals("{$c['access_url']}/v1.0/session", $url);
                    $this->assertEquals([
                        'json'    => [
                            'userId'   => 30,
                            'portalId' => 1,
                            'scope'    => 'ott',
                        ],
                        'headers' => [
                            'Content-Type'  => 'application/json',
                            'Authorization' => 'Bearer ACCESS_TOKEN',
                        ],
                    ], $o);

                    return new Response(200, [], json_encode(['token' => 'SESSION_TOKEN']));
                });

            return $client;
        });

        /**
         * @var $client AccessClient
         */
        $client = $c['go1.client.access'];
        $this->assertEquals('SESSION_TOKEN', $client->generateSession(30, 1, 'ACCESS_TOKEN'));
    }

    public function testGenerateSessionWithBadRequestException()
    {
        $c = $this->getContainer(true);
        $c->extend('client', function () {
            $handlers = new MockHandler([new Response(400, [], json_encode(['message' => 'invalid scope']))]);
            $handlerStack = HandlerStack::create($handlers);
            return new Client(['handler' => $handlerStack]);
        });

        /**
         * @var $client AccessClient
         */
        $client = $c['go1.client.access'];

        $this->expectException(BadRequestException::class);
        $this->assertEquals('JWT', $client->generateSession(30, 1, 'accessToken'));
    }

    public function testGenerateSessionWithServerError()
    {
        $c = $this->getContainer(true);
        $c->extend('client', function () {
            $handlers = new MockHandler([new Response(500, [], json_encode(['message' => 'internal error']))]);
            $handlerStack = HandlerStack::create($handlers);
            return new Client(['handler' => $handlerStack]);
        });

        /**
         * @var $client AccessClient
         */
        $client = $c['go1.client.access'];

        $this->expectException(ServerErrorException::class);
        $this->assertEquals('JWT', $client->generateSession(30, 1, 'accessToken'));
    }

    public function testTranslateToken()
    {
        $token = 'SESSION_TOKEN';
        $c = $this->getContainer(true);
        $c->extend('client', function () use ($c, $token) {
            $client =
                $this->getMockBuilder(Client::class)
                    ->setMethods(['get'])
                    ->disableOriginalConstructor()
                    ->getMock();

            $client
                ->expects($this->any())
                ->method('get')
                ->willReturnCallback(function (string $url, array $o) use ($c, $token) {
                    $this->assertEquals("{$c['access_url']}/v1.0/translateToken", $url);
                    $this->assertEquals([
                        'headers' => [
                            'Content-Type'  => 'application/json',
                            'Authorization' => "Bearer {$token}"
                        ],
                    ], $o);

                    return new Response(200, [], json_encode(['jwt' => 'JWT']));
                });

            return $client;
        });

        /**
         * @var $client AccessClient
         */
        $client = $c['go1.client.access'];
        $this->assertEquals('JWT', $client->translateToken($token));
    }

    public function testTranslateTokenWithBadRequestException()
    {
        $c = $this->getContainer(true);
        $c->extend('client', function () {
            $handlers = new MockHandler([new Response(400, [], json_encode(['message' => 'token is expired']))]);
            $handlerStack = HandlerStack::create($handlers);
            return new Client(['handler' => $handlerStack]);
        });

        /**
         * @var $client AccessClient
         */
        $client = $c['go1.client.access'];
        $this->expectException(BadRequestException::class);
        $this->assertEquals('JWT', $client->translateToken('EXPIRED_TOKEN'));
    }

    public function testTranslateTokenWithServerError()
    {
        $c = $this->getContainer(true);
        $c->extend('client', function () {
            $handlers = new MockHandler([new Response(500, [], json_encode(['message' => 'internal error']))]);
            $handlerStack = HandlerStack::create($handlers);
            return new Client(['handler' => $handlerStack]);
        });

        /**
         * @var $client AccessClient
         */
        $client = $c['go1.client.access'];
        $this->expectException(ServerErrorException::class);
        $this->assertEquals('JWT', $client->translateToken('ANY_TOKEN'));
    }

}

