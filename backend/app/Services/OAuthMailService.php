<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OAuthMailService
{
    /**
     * Envoyer un email depuis le compte SSO du sender (Gmail ou Outlook).
     * Fallback SMTP si aucun compte SSO disponible ou si l'API échoue.
     */
    public function sendEmail(
        User $sender,
        string $to,
        string $subject,
        string $htmlBody,
        ?string $attachmentPath = null,
        ?string $attachmentName = null,
        ?string $attachmentMime = null
    ): void {
        $socialAccount = $sender->socialAccounts()->first();

        if ($socialAccount && $socialAccount->refresh_token) {
            try {
                if ($socialAccount->provider === 'google') {
                    $this->sendViaGmail($socialAccount, $sender, $to, $subject, $htmlBody, $attachmentPath, $attachmentName, $attachmentMime);

                    return;
                }

                if ($socialAccount->provider === 'azure') {
                    $this->sendViaGraph($socialAccount, $sender, $to, $subject, $htmlBody, $attachmentPath, $attachmentName, $attachmentMime);

                    return;
                }
            } catch (\Exception $e) {
                Log::warning("OAuthMailService: envoi OAuth échoué pour {$sender->email} via {$socialAccount->provider}, fallback SMTP. Erreur: ".$e->getMessage());
            }
        }

        // Fallback SMTP
        $this->sendViaSmtp($sender, $to, $subject, $htmlBody, $attachmentPath, $attachmentName, $attachmentMime);
    }

    private function sendViaGmail(
        $socialAccount,
        User $sender,
        string $to,
        string $subject,
        string $htmlBody,
        ?string $attachmentPath,
        ?string $attachmentName,
        ?string $attachmentMime
    ): void {
        $accessToken = $this->refreshGoogleToken($socialAccount);

        $mimeMessage = $this->buildMimeMessage(
            from: $sender->email,
            fromName: trim(($sender->firstname ?? '').' '.($sender->name ?? '')),
            to: $to,
            subject: $subject,
            htmlBody: $htmlBody,
            attachmentPath: $attachmentPath,
            attachmentName: $attachmentName,
            attachmentMime: $attachmentMime
        );

        $encoded = rtrim(strtr(base64_encode($mimeMessage), '+/', '-_'), '=');

        $response = Http::withToken($accessToken)
            ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
                'raw' => $encoded,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Gmail API error: '.$response->body());
        }

        Log::info("OAuthMailService: email envoyé via Gmail depuis {$sender->email} vers {$to}");
    }

    private function sendViaGraph(
        $socialAccount,
        User $sender,
        string $to,
        string $subject,
        string $htmlBody,
        ?string $attachmentPath,
        ?string $attachmentName,
        ?string $attachmentMime
    ): void {
        $accessToken = $this->refreshAzureToken($socialAccount);

        $payload = [
            'message' => [
                'subject' => $subject,
                'body' => [
                    'contentType' => 'HTML',
                    'content' => $htmlBody,
                ],
                'toRecipients' => [
                    ['emailAddress' => ['address' => $to]],
                ],
            ],
            'saveToSentItems' => true,
        ];

        if ($attachmentPath && file_exists($attachmentPath)) {
            $payload['message']['attachments'] = [
                [
                    '@odata.type' => '#microsoft.graph.fileAttachment',
                    'name' => $attachmentName ?? basename($attachmentPath),
                    'contentType' => $attachmentMime ?? 'application/octet-stream',
                    'contentBytes' => base64_encode(file_get_contents($attachmentPath)),
                ],
            ];
        }

        $response = Http::withToken($accessToken)
            ->post('https://graph.microsoft.com/v1.0/me/sendMail', $payload);

        if (! $response->successful()) {
            throw new \RuntimeException('Microsoft Graph API error: '.$response->body());
        }

        Log::info("OAuthMailService: email envoyé via Graph depuis {$sender->email} vers {$to}");
    }

    private function refreshGoogleToken($socialAccount): string
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $socialAccount->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful() || empty($response->json('access_token'))) {
            throw new \RuntimeException('Google token refresh failed: '.$response->body());
        }

        $newToken = $response->json('access_token');
        $socialAccount->update(['token' => $newToken]);

        return $newToken;
    }

    private function refreshAzureToken($socialAccount): string
    {
        $tenant = config('services.azure.tenant', 'common');

        $response = Http::asForm()->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", [
            'client_id' => config('services.azure.client_id'),
            'client_secret' => config('services.azure.client_secret'),
            'refresh_token' => $socialAccount->refresh_token,
            'grant_type' => 'refresh_token',
            'scope' => 'https://graph.microsoft.com/Mail.Send offline_access',
        ]);

        if (! $response->successful() || empty($response->json('access_token'))) {
            throw new \RuntimeException('Azure token refresh failed: '.$response->body());
        }

        $newToken = $response->json('access_token');
        $newRefresh = $response->json('refresh_token');

        $updateData = ['token' => $newToken];
        if ($newRefresh) {
            $updateData['refresh_token'] = $newRefresh;
        }
        $socialAccount->update($updateData);

        return $newToken;
    }

    /**
     * Construire un message MIME RFC 2822 (avec ou sans pièce jointe).
     */
    private function buildMimeMessage(
        string $from,
        string $fromName,
        string $to,
        string $subject,
        string $htmlBody,
        ?string $attachmentPath,
        ?string $attachmentName,
        ?string $attachmentMime
    ): string {
        $boundary = '----=_Part_'.bin2hex(random_bytes(8));
        $encodedSubject = '=?UTF-8?B?'.base64_encode($subject).'?=';
        $fromHeader = $fromName ? "\"{$fromName}\" <{$from}>" : $from;

        if ($attachmentPath && file_exists($attachmentPath)) {
            $headers = implode("\r\n", [
                "From: {$fromHeader}",
                "To: {$to}",
                "Subject: {$encodedSubject}",
                'MIME-Version: 1.0',
                "Content-Type: multipart/mixed; boundary=\"{$boundary}\"",
            ]);

            $htmlPart = implode("\r\n", [
                "--{$boundary}",
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
                '',
                chunk_split(base64_encode($htmlBody)),
            ]);

            $attachmentContent = file_get_contents($attachmentPath);
            $attachmentPart = implode("\r\n", [
                "--{$boundary}",
                'Content-Type: '.($attachmentMime ?? 'application/octet-stream')."; name=\"{$attachmentName}\"",
                'Content-Transfer-Encoding: base64',
                "Content-Disposition: attachment; filename=\"{$attachmentName}\"",
                '',
                chunk_split(base64_encode($attachmentContent)),
                "--{$boundary}--",
            ]);

            return $headers."\r\n\r\n".$htmlPart."\r\n".$attachmentPart;
        }

        // Sans pièce jointe
        $headers = implode("\r\n", [
            "From: {$fromHeader}",
            "To: {$to}",
            "Subject: {$encodedSubject}",
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ]);

        return $headers."\r\n\r\n".chunk_split(base64_encode($htmlBody));
    }

    private function sendViaSmtp(
        User $sender,
        string $to,
        string $subject,
        string $htmlBody,
        ?string $attachmentPath,
        ?string $attachmentName,
        ?string $attachmentMime
    ): void {
        Mail::html($htmlBody, function ($message) use ($sender, $to, $subject, $attachmentPath, $attachmentName, $attachmentMime) {
            $message->to($to)
                ->subject($subject)
                ->replyTo($sender->email, trim(($sender->firstname ?? '').' '.($sender->name ?? '')));

            if ($attachmentPath && file_exists($attachmentPath)) {
                $message->attach($attachmentPath, [
                    'as' => $attachmentName,
                    'mime' => $attachmentMime,
                ]);
            }
        });

        Log::info("OAuthMailService: email envoyé via SMTP (fallback) vers {$to}");
    }
}
