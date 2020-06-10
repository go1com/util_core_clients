<?php

namespace go1\clients\tests;

use Exception;
use go1\clients\MqClient;
use go1\clients\UtilCoreClientServiceProvider;
use go1\util\publishing\event\Event;
use go1\util\schema\mock\UserMockTrait;
use go1\util\Service;
use go1\util\UtilCoreServiceProvider;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Pimple\Container;
use Prophecy\Argument;
use Prophecy\Prophecy\MethodProphecy;
use ReflectionClass;
use ReflectionObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccess;
use function dump;

class MqCoreClientsTest extends UtilCoreClientsTestCase
{
    use UserMockTrait;

    public function dataMessage()
    {
        return [
            [(object) ['foo' => 'bar'], 'user.update', 'Missing entity ID or original data.'],
            [(object) ['id' => 1, 'original' => ['id']], 'user.update', ''],
            [(object) ['id' => null], 'user.update', 'Missing entity ID or original data.'],
            [(object) ['original' => null], 'user.update', 'Missing entity ID or original data.'],
            [(object) [], 'user.update', 'Missing entity ID or original data.'],
            [['foo' => 'bar'], 'user.update', 'Missing entity ID or original data.'],
            [['id' => 1, 'original' => ['id']], 'user.update', ''],
            [['id' => null], 'user.update', 'Missing entity ID or original data.'],
            [['original' => null], 'user.update', 'Missing entity ID or original data.'],
            [[], 'user.update', 'Missing entity ID or original data.'],
            [[], '', ''],
            [[], 'do.enrolment.update', ''],
        ];
    }

    /** @dataProvider dataMessage */
    public function testProcessMessage($body, $routingKey, string $expectedString)
    {
        $queue = $this->getMockBuilder(MqClient::class)->disableOriginalConstructor()->getMock();
        $class = new ReflectionClass(MqClient::class);

        $rPropertyAccessor = $class->getProperty('propertyAccessor');
        $rPropertyAccessor->setAccessible(true);
        $rPropertyAccessor->setValue($queue, $propertyAccessor = PropertyAccess::createPropertyAccessor());

        $method = $class->getMethod('processMessage');
        $method->setAccessible(true);
        if ($expectedString) {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage($expectedString);
        }
        $body = $method->invokeArgs($queue, [$body, $routingKey]);
        !$expectedString && $this->assertEmpty($body);
    }

    public function testInjectRequest()
    {
        $container = new Container(['accounts_name' => 'accounts.test']);
        $container
            ->register(new UtilCoreServiceProvider)
            ->register(new UtilCoreClientServiceProvider, ['queueOptions' => Service::queueOptions()])
            ->extend('go1.client.mq', function (MqClient $queue) {
                $channel = $this
                    ->getMockBuilder(AMQPChannel::class)
                    ->disableOriginalConstructor()
                    ->setMethods(['basic_publish'])
                    ->getMock();

                $timestamp = time();
                $channel
                    ->expects($this->any())
                    ->method('basic_publish')
                    ->willReturnCallback(function (AMQPMessage $message, string $exchange, string $routingKey) use ($timestamp) {
                        $properties = $message->get_properties();

                        /* @var $context AMQPTable */
                        $context = $properties['application_headers'];
                        $context = $context->getNativeData();

                        $this->assertEquals('foo.bar', $routingKey);
                        $this->assertEquals('events', $exchange);
                        $this->assertEquals('X-foo', $context['request_id']);
                        $this->assertEquals(999, $context['actor_id']);
                        $this->assertEquals($timestamp, $context[MqClient::CONTEXT_TIMESTAMP]);
                    });

                $rQueue = new ReflectionObject($queue);
                $rChannels = $rQueue->getProperty('channels');
                $rChannels->setAccessible(true);
                $channels['events']['topic'] = $channel;
                $rChannels->setValue($queue, $channels);

                return $queue;
            });

        $req = Request::create("/");
        $req->headers->add(['X-Request-Id' => 'X-foo']);
        $req->attributes->set('jwt.payload', $this->getPayload(['id' => 999]));

        $requestStack = new RequestStack();
        $requestStack->push($req);
        $container->offsetSet('request_stack', $requestStack);

        /* @var $queue MqClient */
        $queue = $container['go1.client.mq'];
        $queue->publish(['foo' => 'bar'], 'foo.bar');
    }

    public function testInjectSessionId()
    {
        $container = new Container(['accounts_name' => 'accounts.test']);
        $container
            ->register(new UtilCoreServiceProvider)
            ->register(new UtilCoreClientServiceProvider, ['queueOptions' => Service::queueOptions()])
            ->extend('go1.client.mq', function (MqClient $queue) {
                $channel = $this
                    ->getMockBuilder(AMQPChannel::class)
                    ->disableOriginalConstructor()
                    ->setMethods(['basic_publish'])
                    ->getMock();

                $timestamp = time();
                $channel
                    ->expects($this->any())
                    ->method('basic_publish')
                    ->willReturnCallback(function (AMQPMessage $message, string $exchange, string $routingKey) use ($timestamp) {
                        $properties = $message->get_properties();

                        /* @var $context AMQPTable */
                        $context = $properties['application_headers'];
                        $context = $context->getNativeData();

                        $this->assertEquals('foo.bar', $routingKey);
                        $this->assertEquals('events', $exchange);
                        $this->assertEquals('xxxx', $context['sessionId']);
                        $this->assertEquals(999, $context['actor_id']);
                        $this->assertEquals($timestamp, $context[MqClient::CONTEXT_TIMESTAMP]);
                    });

                $rQueue = new ReflectionObject($queue);
                $rChannels = $rQueue->getProperty('channels');
                $rChannels->setAccessible(true);
                $channels['events']['topic'] = $channel;
                $rChannels->setValue($queue, $channels);

                return $queue;
            });

        $req = Request::create("/consume?a=b", 'POST');
        $req->request->replace([
            'routingKey' => 'ANY',
            'body'       => [],
            'context'    => ['sessionId' => 'xxxx']
        ]);
        $req->attributes->set('jwt.payload', $this->getPayload(['id' => 999]));

        $requestStack = new RequestStack();
        $requestStack->push($req);
        $container->offsetSet('request_stack', $requestStack);

        /* @var $queue MqClient */
        $queue = $container['go1.client.mq'];
        $queue->publish(['foo' => 'bar'], 'foo.bar');
    }

    public function testPublishEvent()
    {
        $container = new Container(['accounts_name' => 'accounts.test']);
        $container
            ->register(new UtilCoreServiceProvider)
            ->register(new UtilCoreClientServiceProvider, ['queueOptions' => Service::queueOptions()])
            ->extend('go1.client.mq', function (MqClient $queue) {
                $channel = $this
                    ->getMockBuilder(AMQPChannel::class)
                    ->disableOriginalConstructor()
                    ->setMethods(['basic_publish'])
                    ->getMock();

                $timestamp = time();
                $channel
                    ->expects($this->any())
                    ->method('basic_publish')
                    ->willReturnCallback(function (AMQPMessage $message, string $exchange, string $routingKey) use ($timestamp) {
                        $properties = $message->get_properties();

                        /* @var $context AMQPTable */
                        $context = $properties['application_headers'];
                        $context = $context->getNativeData();

                        $this->assertEquals('foo.bar', $routingKey);
                        $this->assertEquals('events', $exchange);
                        $this->assertEquals('X-foo', $context['request_id']);
                        $this->assertEquals(999, $context['actor_id']);
                        $this->assertEquals($timestamp, $context[MqClient::CONTEXT_TIMESTAMP]);
                    });

                $rQueue = new ReflectionObject($queue);
                $rChannels = $rQueue->getProperty('channels');
                $rChannels->setAccessible(true);
                $channels['events']['topic'] = $channel;
                $rChannels->setValue($queue, $channels);

                return $queue;
            });

        $req = Request::create("/");
        $req->headers->add(['X-Request-Id' => 'X-foo']);
        $req->attributes->set('jwt.payload', $this->getPayload(['id' => 999]));

        $requestStack = new RequestStack();
        $requestStack->push($req);
        $container->offsetSet('request_stack', $requestStack);

        /* @var $queue MqClient */
        $queue = $container['go1.client.mq'];
        $event = new Event(['foo' => 'bar'], 'foo.bar');
        $queue->publishEvent($event);
    }

    public function testBatch()
    {
        $container = new Container(['accounts_name' => 'accounts.test']);
        $container
            ->register(new UtilCoreServiceProvider)
            ->register(new UtilCoreClientServiceProvider, ['queueOptions' => Service::queueOptions()])
            ->extend('go1.client.mq', function (MqClient $queue) {
                $ch = $this
                    ->getMockBuilder(AMQPChannel::class)
                    ->disableOriginalConstructor()
                    ->setMethods(['basic_publish', 'batch_basic_publish'])
                    ->getMock();

                $ch
                    ->expects($this->any())
                    ->method('batch_basic_publish')
                    ->willReturnCallback(function (AMQPMessage $msg, string $exchange, string $routingKey) {
                        $this->assertEquals('{"foo":"bar"}', $msg->getBody());
                        $this->assertEquals('events', $exchange);
                        $this->assertEquals('qa-routingKey', $routingKey);
                    });

                $ch
                    ->expects($this->any())
                    ->method('basic_publish')
                    ->willReturnCallback(function ($msg, $exchange, $routingKey) {
                        $this->assertEquals('quit', $msg->getBody());
                        $this->assertEquals('events', $exchange);
                        $this->assertEquals('', $routingKey);
                    });

                $rQueue = new ReflectionObject($queue);
                $rChannels = $rQueue->getProperty('channels');
                $rChannels->setAccessible(true);
                $chs['events']['topic'] = $ch;
                $rChannels->setValue($queue, $chs);

                return $queue;
            });

        /** @var MqClient $client */
        $client = $container['go1.client.mq'];
        $client->batchAdd('{"foo":"bar"}', 'qa-routingKey', []);
        $client->batchDone();
    }
}
