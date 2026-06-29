<?php

namespace App\Mail;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;

class BrevoTransport extends AbstractTransport
{
    public function __construct(private string $apiKey)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $payload = [
            'sender' => [
                'email' => $email->getFrom()[0]->getAddress(),
                'name'  => $email->getFrom()[0]->getName() ?: 'GeoIncidencias',
            ],
            'to' => array_map(
                fn($a) => ['email' => $a->getAddress()],
                $email->getTo()
            ),
            'subject'     => $email->getSubject(),
            'htmlContent' => $email->getHtmlBody() ?? nl2br($email->getTextBody()),
        ];

        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'api-key: ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \Exception('Brevo API error (' . $httpCode . '): ' . $response);
        }
    }

    public function __toString(): string
    {
        return 'brevo';
    }
}
