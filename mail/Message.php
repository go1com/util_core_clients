<?php

namespace go1\clients\mail;

use go1\clients\MailClient;
use go1\util\portal\MailTemplate as Template;

class Message
{
    private string   $recipient;
    private Template $template;
    private array    $context      = [];
    private array    $options      = [];
    private          $attachments  = [];
    private          $cc           = [];
    private          $bcc          = [];
    private array    $queueContext = [];
    private array    $queueOptions = [];
    private array    $categories   = [];

    public static function create(string $recipient, Template $template): Message
    {
        $message = new Message;
        $message->recipient = $recipient;
        $message->template = $template;

        return $message;
    }

    public function send(MailClient $client)
    {
        return $client->post(
            $this->recipient, $this->template, $this->context,
            $this->options, $this->attachments, $this->cc, $this->bcc,
            $this->queueContext,
            $this->queueOptions,
            $this->categories
        );
    }

    public function setContext(array $context): Message
    {
        $this->context = $context;

        return $this;
    }

    public function setOptions(array $options): Message
    {
        $this->options = $options;

        return $this;
    }

    public function setAttachments(array $attachments): Message
    {
        $this->attachments = $attachments;

        return $this;
    }

    public function setCc(array $cc): Message
    {
        $this->cc = $cc;

        return $this;
    }

    public function setBcc(array $bcc): Message
    {
        $this->bcc = $bcc;

        return $this;
    }

    public function setQueueContext(array $queueContext): Message
    {
        $this->queueContext = $queueContext;

        return $this;
    }

    public function setQueueOptions(array $queueOptions): Message
    {
        $this->queueOptions = $queueOptions;

        return $this;
    }

    public function setCategories(array $categories): Message
    {
        $this->categories = $categories;

        return $this;
    }
}
