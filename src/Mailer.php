<?php

namespace Dataglimpse\Lib;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    /**
     * Send a custom email using PHPMailer (HTML supported)
     */
    public static function send(
        string $to,
        string $subject,
        string $body,
        string $fromEmail = null,
        string $fromName = 'Dataglimpse'
    ): bool {
        $mail = new PHPMailer(true);

        try {
            // --- SMTP Configuration ---
            $mail->isSMTP();
            $mail->Host       = getenv('MAIL_HOST') ?: 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = getenv('MAIL_USERNAME') ?: 'youremail@gmail.com';
            $mail->Password   = getenv('MAIL_PASSWORD') ?: 'your-app-password';
            $mail->SMTPSecure = getenv('MAIL_ENCRYPTION') ?: PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = getenv('MAIL_PORT') ?: 587;

            // --- Sender Info ---
            $fromEmail = $fromEmail ?: getenv('MAIL_FROM_ADDRESS') ?: $mail->Username;
            $fromName  = getenv('MAIL_FROM_NAME') ?: $fromName;
            $mail->setFrom($fromEmail, $fromName);
            $mail->addReplyTo($fromEmail, $fromName);

            // --- Recipient ---
            $mail->addAddress($to);

            // --- Content ---
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);

            // --- Optional Logging ---
            echo "\n📨 Sending email to: {$to}\n";

            // --- Send ---
            $mail->send();

            echo "✅ Mail sent successfully!\n";
            return true;

        } catch (Exception $e) {
            echo "❌ Mail could not be sent. Error: {$mail->ErrorInfo}\n";
            return false;
        }
    }
}
