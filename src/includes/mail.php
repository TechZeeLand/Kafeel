<?php
/**
 * Thin wrapper around PHPMailer so the rest of the app just calls
 * send_email(...) without touching SMTP details. If SMTP_HOST isn't
 * configured, falls back to PHP's built-in mail() transport (fine for
 * quick testing, but most hosts need real SMTP creds to actually deliver).
 */
require_once __DIR__ . '/functions.php';
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
    $GLOBALS['__mail_error'] = null;
    $cfg = smtp_settings();
    $mail = new PHPMailer(true);
    try {
        if ($cfg['host'] !== '') {
            $mail->isSMTP();
            $mail->Host = $cfg['host'];
            $mail->Port = $cfg['port'];
            $mail->Timeout = 15;
            if ($cfg['user'] !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = $cfg['user'];
                $mail->Password = $cfg['pass'];
            }
            if ($cfg['secure'] === 'tls') $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            elseif ($cfg['secure'] === 'ssl') $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            else $mail->SMTPAutoTLS = false;
        } else {
            $mail->isMail();
        }

        $mail->CharSet = 'UTF-8';
        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
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
        $GLOBALS['__mail_error'] = $mail->ErrorInfo ?: $e->getMessage();
        error_log('[mail] Failed to send "' . $subject . '" to ' . $toEmail . ': ' . $GLOBALS['__mail_error']);
        return false;
    }
}

/** Reason the most recent send_email() call failed (for the admin "send test email" button). */
function mail_last_error(): ?string {
    return $GLOBALS['__mail_error'] ?? null;
}

/** Wraps a body of content in a minimal, on-brand HTML email shell (store name or logo, store email in the footer). */
function email_wrap(string $title, string $bodyHtml): string {
    $store = store_info();
    $dark = theme_settings()['dark'];
    $logo = brand_logo_email_url();
    // A logo goes on a white band (any logo colours are readable there); without one, the store name sits on the theme colour.
    $head = $logo
        ? '<div style="background:#ffffff;padding:16px 24px;border-bottom:4px solid ' . e($dark) . ';"><img src="' . e($logo) . '" alt="' . e($store['name']) . '" style="display:block;max-height:44px;max-width:220px;height:auto;width:auto;border:0;"></div>'
        : '<div style="background:' . e($dark) . ';color:' . e(contrast_text($dark)) . ';padding:18px 24px;font-size:1.1rem;font-weight:bold;">' . e($store['name']) . '</div>';
    $foot = e($store['name']) . ($store['email'] !== '' ? ' &middot; ' . e($store['email']) : '') . ($store['phone'] !== '' ? ' &middot; ' . e($store['phone']) : '') . ($store['phone2'] !== '' ? ' &middot; ' . e($store['phone2']) : '');
    return '<div style="font-family:Arial,Helvetica,sans-serif;background:#efece2;padding:32px 16px;">'
        . '<div style="max-width:520px;margin:0 auto;background:#fffdf8;border:1px solid #d9d4c3;border-radius:8px;overflow:hidden;">'
        . $head
        . '<div style="padding:24px;color:#20293b;line-height:1.6;">'
        . '<h2 style="margin-top:0;color:#20293b;">' . e($title) . '</h2>'
        . $bodyHtml
        . '</div>'
        . '<div style="padding:16px 24px;background:#f8f6ee;color:#8791a6;font-size:0.78rem;">' . $foot . '</div>'
        . '</div></div>';
}
