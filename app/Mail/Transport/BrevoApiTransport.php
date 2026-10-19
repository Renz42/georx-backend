<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

class BrevoApiTransport extends AbstractTransport
{
    protected string $apiKey;

    public function __construct(string $apiKey)
    {
        parent::__construct();
        $this->apiKey = $apiKey;
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        if (empty($this->apiKey)) {
            throw new TransportException('Brevo API key is not configured.');
        }

        // Format sender
        $from = $email->getFrom()[0] ?? null;
        $sender = [
            'email' => $from ? $from->getAddress() : config('mail.from.address', 'noreply@georx.ph'),
            'name'  => $from ? ($from->getName() ?: config('mail.from.name', 'GEORX Medicine Hub')) : config('mail.from.name', 'GEORX Medicine Hub'),
        ];

        // Format recipients
        $to = [];
        foreach ($email->getTo() as $recipient) {
            $to[] = [
                'email' => $recipient->getAddress(),
                'name'  => $recipient->getName() ?: null,
            ];
        }

        $payload = [
            'sender'      => $sender,
            'to'          => $to,
            'subject'     => $email->getSubject() ?: 'GEORX Verification Code',
            'htmlContent' => $email->getHtmlBody() ?: nl2br($email->getTextBody() ?? ''),
        ];

        if ($email->getTextBody()) {
            $payload['textContent'] = $email->getTextBody();
        }

        $response = Http::withHeaders([
            'api-key'      => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ])->timeout(15)->post('https://api.brevo.com/v3/smtp/email', $payload);

        if (!$response->successful()) {
            $errData = $response->json();
            $message = $errData['message'] ?? ('HTTP ' . $response->status());
            throw new TransportException('Brevo API delivery failed: ' . $message);
        }
    }

    public function __toString(): string
    {
        return 'brevo-api';
    }
}
