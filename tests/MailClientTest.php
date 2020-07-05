<?php

namespace go1\clients\tests;

use go1\clients\MailClient;
use go1\clients\MqClient;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use function func_get_args;
use function json_decode;

/**
 * Make sure mail-client publish messages we are expecting to.
 */
class MailClientTest extends TestCase
{
    private $log;

    private function getMailClient()
    {
        $queue = $this
            ->getMockBuilder(MqClient::class)
            ->disableOriginalConstructor()
            ->setMethods(['channel'])
            ->getMock();

        $ch = $this
            ->getMockBuilder(AMQPChannel::class)
            ->disableOriginalConstructor()
            ->setMethods(['basic_publish'])
            ->getMock();

        $queue
            ->expects($this->any())
            ->method('channel')
            ->willReturn($ch);

        $ch
            ->expects($this->any())
            ->method('basic_publish')
            ->willReturnCallback(
                function () {
                    $this->log[] = func_get_args();
                }
            );

        $queue->setLogger(new NullLogger());

        return new MailClient($queue);
    }

    public function testLegacySend()
    {
        $mail = $this->getMailClient();
        $mail->send('', 'user@qa.com', 'test subject', 'test body', 'test <strong>body</strong>');

        /** @var AMQPMessage $msg */
        $msg = $this->log[0][0];
        $payload = json_decode($msg->getBody());

        $this->assertEquals('events', $this->log[0][1], 'no exchange');
        $this->assertEquals('do.mail.send', $this->log[0][2], 'routing of #worker');
        $this->assertEquals('user@qa.com', $payload->recipient);
        $this->assertEquals('test subject', $payload->subject);
        $this->assertEquals('test body', $payload->body);
    }

    public function testSendWithQueueOptions()
    {
        $mail = $this->getMailClient();
        $mail->withQueueExchange('events');
        $mail->send('', 'user@qa.com', 'test subject', 'test body', 'test <strong>body</strong>');

        /** @var AMQPMessage $msg */
        $msg = $this->log[0][0];
        $payload = json_decode($msg->getBody());

        $this->assertEquals('events', $this->log[0][1], 'exchange');
        $this->assertEquals('do.mail.send', $this->log[0][2], 'simple routing key');
        $this->assertEquals('user@qa.com', $payload->recipient);
        $this->assertEquals('test subject', $payload->subject);
        $this->assertEquals('test body', $payload->body);
    }

    public function testSendWithCategories()
    {
        $mail = $this->getMailClient();
        $mail->withQueueExchange('events');
        $mail->send('', 'user@qa.com', '', '', '<strong>body</strong>',[], [], [], [], [], [], [], ['dogs', 'animals', 'pets', 'mammals']);

        /** @var AMQPMessage $msg */
        $msg = $this->log[0][0];
        $payload = json_decode($msg->getBody());

        $this->assertEquals('events', $this->log[0][1], 'exchange');
        $this->assertEquals('do.mail.send', $this->log[0][2], 'simple routing key');
        $this->assertEquals('user@qa.com', $payload->recipient);
        $this->assertEquals(['dogs', 'animals', 'pets', 'mammals'], $payload->categories);
    }
}
