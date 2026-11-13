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
        $this->apiKey = trim($apiKey);
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        if ($this->apiKey === '') {
            throw new TransportException('Brevo API key is not configured.');
        }

        // Format sender (omit empty name to comply strictly with Brevo v3 schema)
        $from = $email->getFrom()[0] ?? null;
        $senderEmail = $from ? trim((string) $from->getAddress()) : trim((string) config('mail.from.address', 'noreply@georx.ph'));
        $senderName = $from ? trim((string) $from->getName()) : '';
        if ($senderName === '') {
            $senderName = trim((string) config('mail.from.name', 'GEORX Medicine Hub'));
        }

        $sender = ['email' => $senderEmail];
        if ($senderName !== '') {
            $sender['name'] = $senderName;
        }

        // Format recipients (omit empty name rather than sending JSON null)
        $to = [];
        foreach ($email->getTo() as $recipient) {
            $entry = [
                'email' => trim((string) $recipient->getAddress()),
            ];
            $recipientName = trim((string) $recipient->getName());
            if ($recipientName !== '') {
                $entry['name'] = $recipientName;
            }
            $to[] = $entry;
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
