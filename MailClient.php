<?php

namespace go1\clients;

use Doctrine\DBAL\Connection;
use go1\libraries\notify\util\MailTemplate;
use go1\util\portal\MailTemplate as Template;
use go1\util\portal\PortalChecker;
use go1\util\queue\Queue;
use InvalidArgumentException;

class MailClient
{
    private $queue;
    private $portalName;
    private $portalId;
    private $queueExchange = '';
    private $customSmtp    = false;

    public function __construct(MqClient $queue)
    {
        $this->queue = $queue;
    }

    /**
     * Usage: $mail
     *              ->instance($db, $instance)
     *              ->post(…);
     */
    public function instance(Connection $db, $portalName): MailClient
    {
        $helper = new PortalChecker;
        $portal = is_object($portalName) ? $portalName : $helper->load($db, $portalName);
        if ($portal) {
            $client = clone $this;
            $client->portalId = $portal->id;
            $client->portalName = $portal->title;
            if ($helper->useCustomSMTP($portal)) {
                $client->customSmtp = true;
            }

            return $client;
        }

        return $this;
    }

    public function withQueueExchange(string $exchange)
    {
        $this->queueExchange = $exchange;
    }

    public function post(
        $recipient,
        Template $template,
        array $context = [],
        array $options = [],
        $attachments = [],
        $cc = [],
        $bcc = [],
        array $queueContext = [],
        array $queueOptions = [],
        array $categories = []
    ) {
        $this->send($template->getId(), $recipient, $template->getSubject(), $template->getBody(), $template->getHtml(), $context, $options, $attachments, $cc, $bcc, $queueContext, $queueOptions, $categories);
    }

    /**
     * @deprecated
     */
    public function send(
        $templateKey,
        $recipient,
        $subject,
        $body,
        $html,
        array $context = [],
        array $options = [],
        $attachments = [],
        $cc = [],
        $bcc = [],
        array $queueContext = [],
        array $queueOptions = [],
        array $categories = []
    ) {
        $data = array_filter(['cc' => $cc, 'bcc' => $bcc]);

        if ($this->portalName) {
            $data['instance'] = $this->portalName;
        }

        if ($this->portalId) {
            $data['from_instance'] = $this->portalId;
        }

        $data += [
            'recipient'   => $recipient,
            'subject'     => $subject,
            'body'        => $body,
            'html'        => $html,
            'context'     => $context,
            'attachments' => $attachments, # array of ['name' => STRING, 'url' => STRING]
            'options'     => $options,
            'categories'  => $categories,
            'templateKey' => $templateKey ?? '',
        ];
        $data['custom_smtp'] = $this->customSmtp;
        $routingKey = isset($queueOptions['custom']) ? $queueOptions['custom'] : Queue::DO_MAIL_SEND;

        $this->queue->publish($data, $routingKey, $queueContext);
    }

    public function template(
        PortalClient $portalClient,
        string $portalName,
        string $mailKey,
        string $defaultSubject,
        string $defaultBody,
        string $defaultHtml = null,
        bool $strict = true): Template
    {
        if ($strict && !MailTemplate::has($mailKey)) {
            throw new InvalidArgumentException('Invalid mail key: ' . $mailKey);
        }

        try {
            return $portalClient->mailTemplate($portalName, $mailKey);
        } catch (InvalidArgumentException $e) {
            return new Template($mailKey, $defaultSubject, $defaultBody, $defaultHtml);
        }
    }
}
