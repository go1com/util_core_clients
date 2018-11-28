<?php

namespace go1\clients\tests;

use go1\clients\MailClient;
use go1\util\portal\MailTemplate;
use go1\util\portal\PortalHelper;
use go1\util\queue\Queue;
use go1\util\schema\mock\PortalMockTrait;

class MailCoreClientsTest extends UtilCoreClientsTestCase
{
    use PortalMockTrait;

    /**
     * @runInSeparateProcess
     */
    public function testSmtpPortal()
    {
        /** @var MailClient $client */
        $container = $this->getContainer();
        $client = $container['go1.client.mail'];
        $portalId = $this->createPortal($this->go1, [
            'title' => $portalName = 'foo.bar',
            'data'  => [
                'configuration' => [PortalHelper::FEATURE_CUSTOM_SMTP => true],
            ],
        ]);

        $client
            ->instance($this->go1, $portalName)
            ->post('foo@bar.com', new MailTemplate('id', 'subject', 'body', 'html'));

        $this->assertArrayHasKey(Queue::DO_MAIL_SEND, $this->queueMessages);
        $this->assertCount(1, $this->queueMessages[Queue::DO_MAIL_SEND]);
        $this->assertEquals(
            [
                'instance'      => 'foo.bar',
                'from_instance' => $portalId,
                'recipient'     => 'foo@bar.com',
                'subject'       => 'subject',
                'body'          => 'body',
                'html'          => 'html',
                'context'       => [],
                'attachments'   => [],
                'options'       => [],
            ],
            $this->queueMessages[Queue::DO_MAIL_SEND][0]
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testNoSmtpPortal()
    {
        /** @var MailClient $client */
        $container = $this->getContainer();
        $client = $container['go1.client.mail'];
        $portalId = $this->createPortal($this->go1, ['title' => $portalName = 'foo.bar']);

        $client
            ->instance($this->go1, $portalName)
            ->post('foo@bar.com', new MailTemplate('id', 'subject', 'body', 'html'));

        $this->assertArrayHasKey(Queue::DO_MAIL_SEND, $this->queueMessages);
        $this->assertCount(1, $this->queueMessages[Queue::DO_MAIL_SEND]);
        $this->assertEquals(
            [
                'from_instance' => $portalId,
                'recipient'     => 'foo@bar.com',
                'subject'       => 'subject',
                'body'          => 'body',
                'html'          => 'html',
                'context'       => [],
                'attachments'   => [],
                'options'       => [],
            ],
            $this->queueMessages[Queue::DO_MAIL_SEND][0]
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testNoPortal()
    {
        /** @var MailClient $client */
        $container = $this->getContainer();
        $client = $container['go1.client.mail'];
        $client->post('foo@bar.com', new MailTemplate('id', 'subject', 'body', 'html'));

        $this->assertArrayHasKey(Queue::DO_MAIL_SEND, $this->queueMessages);
        $this->assertCount(1, $this->queueMessages[Queue::DO_MAIL_SEND]);
        $this->assertEquals(
            [
                'recipient'   => 'foo@bar.com',
                'subject'     => 'subject',
                'body'        => 'body',
                'html'        => 'html',
                'context'     => [],
                'attachments' => [],
                'options'     => [],
            ],
            $this->queueMessages[Queue::DO_MAIL_SEND][0]
        );
    }
}
