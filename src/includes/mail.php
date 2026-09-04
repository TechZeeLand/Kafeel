<?php
/**
 * Thin wrapper around PHPMailer so the rest of the app just calls
 * send_email(...) without touching SMTP details. If SMTP_HOST isn't
 * configured, falls back to PHP's built-in mail() transport (fine for
 * quick testing, but most hosts need real SMTP creds to actually deliver).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * @param string      $toEmail
 * @param string      $toName
 * @param string      $subject
 * @param string      $htmlBody   HTML body (a plain-text version is auto-derived).
 * @param string|null $replyToEmail
 * @param string|null $replyToName
 * @return bool true on success. Failures are logged, never thrown — a mail
 *              hiccup should never break checkout, registration, etc.
 */
function send_email(string $toEmail, string $toName, string $subject, string $htmlBody, ?string $replyToEmail = null, ?string $replyToName = null): bool {
    $mail = new PHPMailer(true);
    try {
        if (SMTP_HOST !== '') {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->Port = SMTP_PORT;
            if (SMTP_USER !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USER;
                $mail->Password = SMTP_PASS;
            }
            if (SMTP_SECURE === 'tls') $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            elseif (SMTP_SECURE === 'ssl') $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            else $mail->SMTPAutoTLS = false;
        } else {
            $mail->isMail();
        }

        $mail->CharSet = 'UTF-8';
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        if ($replyToEmail) {
            $mail->addReplyTo($replyToEmail, $replyToName ?: $replyToEmail);
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody)));

        $mail->send();
        return true;
    } catch (PHPMailerException | Throwable $e) {
        error_log('[mail] Failed to send "' . $subject . '" to ' . $toEmail . ': ' . $e->getMessage());
        return false;
    }
}

/** Wraps a body of content in a minimal, on-brand HTML email shell. */
function email_wrap(string $title, string $bodyHtml): string {
    $site = e(SITE_NAME);
    return '<div style="font-family:Arial,Helvetica,sans-serif;background:#efece2;padding:32px 16px;">'
        . '<div style="max-width:520px;margin:0 auto;background:#fffdf8;border:1px solid #d9d4c3;border-radius:8px;overflow:hidden;">'
        . '<div style="background:#20293b;color:#e9d5a8;padding:18px 24px;font-size:1.1rem;font-weight:bold;">' . $site . '</div>'
        . '<div style="padding:24px;color:#20293b;line-height:1.6;">'
        . '<h2 style="margin-top:0;color:#20293b;">' . e($title) . '</h2>'
        . $bodyHtml
        . '</div>'
        . '<div style="padding:16px 24px;background:#f8f6ee;color:#8791a6;font-size:0.78rem;">' . $site . ' &middot; ' . e(CONTACT_EMAIL) . '</div>'
        . '</div></div>';
}
