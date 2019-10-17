<?php

namespace go1\clients\tests;

use go1\clients\MqClient;
use go1\clients\UserClient;
use go1\util\queue\Queue;
use go1\util\user\UserHelper;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;

class UserCoreClientsTest extends UtilCoreClientsTestCase
{
    public function test()
    {
        $c = $this->getContainer();

        $mqClient = $this
            ->getMockBuilder(MqClient::class)
            ->disableOriginalConstructor()
            ->setMethods(['publish'])
            ->getMock();

        $mqClient
            ->expects($this->any())
            ->method('publish')
            ->willReturnCallback(
                function (array $body, string $routingKey) {
                    if ($routingKey == Queue::DO_USER_UNBLOCK_MAIL) {
                        $this->assertEquals($body['mail'], 'abc@mail.com');
                    }

                    if ($routingKey == Queue::DO_USER_UNBLOCK_IP) {
                        $this->assertEquals($body['ip'], '192.168.0.1');
                    }

                    return true;
                }
            );

        $c['go1.client.mq'] = $mqClient;
        $c['go1.client.user']->unblockEmail('abc@mail.com');
        $c['go1.client.user']->unblockIp('192.168.0.1');
    }

    public function dataGetJwt()
    {
        return [
            ['api-dev1.go1.co', 12345, "0000-abcd-1111-efgh-2222", "akastsuki.mygo1.co"],
            ['api-dev2.go1.co', 67890, "0000-abcd-1111-efgh-5555", null],
        ];
    }

    private function createPayload(\stdClass $user)
    {
        $payload = (object) [
            'id'         => $user->id,
            'instance'   => 'accounts-dev.gocatalyze.com',
            'profile_id' => $user->profile_id,
            'mail'       => $user->mail,
            'name'       => UserHelper::name($user, true),
            'roles'      => [
                "Admin on #Accounts",
                "developer",
            ],
            'accounts'   => [
                (object) [
                    'id'         => 11111,
                    'profile_id' => 22222,
                    'instance'   => 'akastsuki.mygo1.co',
                    'roles'      => ['Student', 'administrator'],
                ],
                (object) [
                    'id'         => 33333,
                    'profile_id' => 44444,
                    'instance'   => 'best-friend.mygo1.co',
                    'roles'      => ['Student', 'administrator'],
                ],
            ],
        ];

        return $payload;
    }

    private function fakeUserClient()
    {
        $userClient = $this->getMockBuilder(UserClient::class)
                           ->setMethods(['uuid2jwt'])
                           ->disableOriginalConstructor()
                           ->getMock();

        $userClient->expects($this->any())
                   ->method('uuid2jwt')
                   ->willReturnCallback(function ($client, $userUrl, $uuid, $portalName) use ($userClient) {
                       $uuid2jwt = new \ReflectionMethod(UserClient::class, 'uuid2jwt');
                       $rs = $uuid2jwt->invokeArgs($userClient, [$client, $userUrl, $uuid, $portalName]);

                       return $rs;
                   });

        return $userClient;
    }

    private function fakeClient(string &$urlResult, string $portalName = null, \stdClass $payload, int $id)
    {
        $client = $this->getMockBuilder(Client::class)
                       ->setMethods(['get'])
                       ->disableOriginalConstructor()
                       ->getMock();

        $client->expects($this->any())
               ->method('get')
               ->willReturnCallback(function ($url, $options) use (&$urlResult, $portalName, $payload, $id) {
                   if (-1 < strpos($url, 'account/masquerade')) {
                       return new Response(200, ['Content-Type' => 'application/json'], json_encode(UserHelper::load($this->go1, $id)));
                   }
                   $urlResult = $url;
                   if (!is_null($portalName)) {
                       foreach ($payload->accounts as $account) {
                           if ($portalName == $account->instance) {
                               $payload->accounts = [$account];
                               break;
                           }
                       }
                   }

                   return new Response(200, ['Content-Type' => 'application/json'], json_encode(['jwt' => UserHelper::encode($payload)]));
               });

        return $client;
    }

    /** @dataProvider dataGetJwt */
    public function testUuid2jwt(string $apiUrl, int $profileId, string $uuid, string $portalName = null)
    {
        $clientMock = $this->prophesize(Client::class);
        $mqClientMock = $this->prophesize(MqClient::class);

        $testSubject = new UserClient(
            $clientMock->reveal(),
            $apiUrl,
            $mqClientMock->reveal()
        );

        $response = new Response(200, [], json_encode(['jwt' => 'a jwt']));
        $expectedUrl = implode('/', array_filter([$apiUrl, 'account/current', $uuid, $portalName]));
        $clientMock->get($expectedUrl, ['http_errors' => false])
            ->shouldBeCalled()
            ->willReturn($response);

        $this->assertEquals('a jwt', $testSubject->uuid2jwt($uuid, $portalName));
    }

    /** @dataProvider dataGetJwt */
    public function testUuid2jwtLegacy(string $apiUrl, int $profileId, string $uuid, string $portalName = null)
    {
        $urlResult = '';

        $userId = $this->createUser($this->go1, [
            'uuid'       => $uuid,
            'mail'       => $email = 'dawn.do@test.com',
            'instance'   => 'accounts-dev.gocatalyze.com',
            'profile_id' => $profileId,
        ]);

        $user = UserHelper::load($this->go1, $userId);
        $payload = $this->createPayload($user);
        $client = $this->fakeClient($urlResult, $portalName, $this->createPayload($user), $userId);
        $userClient = $this->fakeUserClient();

        $uuid2jwt = new \ReflectionMethod(UserClient::class, 'uuid2jwt');
        $rs = $uuid2jwt->invokeArgs($userClient, [$client, $apiUrl, $uuid, $portalName]);

        $jwt = UserHelper::encode($payload);

        if (is_null($portalName)) {
            $this->assertEquals($rs, $jwt);
        } else {
            $this->assertNotEquals($rs, $jwt);
        }

        $this->assertEquals($urlResult, "{$apiUrl}/account/current/{$uuid}" . (!is_null($portalName) ? "/{$portalName}" : ''));
    }

    /** @dataProvider dataGetJwt */
    public function testProfileId2jwtLegacy(string $apiUrl, int $profileId, string $uuid, string $portalName = null)
    {
        $urlResult = '';

        $userId = $this->createUser($this->go1, [
            'uuid'       => $uuid,
            'mail'       => $email = 'dawn.do@test.com',
            'instance'   => 'accounts-dev.gocatalyze.com',
            'profile_id' => $profileId,
        ]);

        $user = UserHelper::load($this->go1, $userId);
        $payload = $this->createPayload($user);

        $client = $this->fakeClient($urlResult, $portalName, $this->createPayload($user), $userId);
        $userClient = $this->fakeUserClient();

        $profileId2jwt = new \ReflectionMethod(UserClient::class, 'profileId2jwt');
        $rs = $profileId2jwt->invokeArgs($userClient, [$client, $apiUrl, $profileId, $portalName]);

        $jwt = UserHelper::encode($payload);

        if (is_null($portalName)) {
            $this->assertEquals($rs, $jwt);
        } else {
            $this->assertNotEquals($rs, $jwt);
        }

        $this->assertEquals($urlResult, "{$apiUrl}/account/current/{$uuid}" . (!is_null($portalName) ? "/{$portalName}" : ''));
    }

    /**
     * @runInSeparateProcess
     */
    public function testIsCourseAuthor()
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
                ->willReturnCallback(function ($url, $options) use ($c) {
                    return new Response(200, [], json_encode([
                            'accounts' => [
                                ['courseAuthor' => 1],
                            ],
                        ])
                    );
                });

            return $httpClient;
        });

        /** @var UserClient $client */
        $client = $c['go1.client.user'];
        $this->assertTrue($client->isCourseAuthor('qa.mygo1.com', 'jwt'));
    }

    public function testFindUsers()
    {
        $clientMock = $this->prophesize(Client::class);
        $mqClientMock = $this->prophesize(MqClient::class);
        $testSubject = new UserClient(
            $clientMock->reveal(),
            'http://example.com',
            $mqClientMock->reveal()
        );

        $options = [
            RequestOptions::VERIFY => true,
        ];

        $response = new Response(200, [], json_encode([1,2]));
        $clientMock->get(
            'http://example.com/account/find/foo.mygo1.com/administrator,student?limit=30&offset=2',
            $options
        )->shouldBeCalled()
            ->willReturn($response);


        $result = $testSubject->findUsers('foo.mygo1.com', ['administrator', 'student'], true, 30, 2, $options);
        $this->assertIsIterable($result);
        $entries = iterator_to_array($result);
        $this->assertEquals([1, 2], $entries);
    }
}
