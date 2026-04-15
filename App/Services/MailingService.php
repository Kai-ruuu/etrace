<?php

namespace App\Services;

use Exception;
use PHPMailer\PHPMailer\PHPMailer;

class MailingService
{
    public $host;
    public $port;
    public $username;
    public $password;
    public $smtpAuth;
    public $smtpSecure;
    
    protected PHPMailer $mail;

    public static function forProd(): self
    {
        $host = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $port = (int) ($_ENV['SMTP_PORT'] ?? 587);
        $email = $_ENV['SMTP_MAIL'] ?? '';
        $pass = $_ENV['SMTP_PASS'] ?? '';

        if (!$email) {
            error_log("SMTP_MAIL is not set.");
            die();
        }

        if (!$pass) {
            error_log("SMTP_PASS is not set.");
            die();
        }

        return new self(
            $host, $port,
            $email, $pass
        );
    }

    public function __construct(
        string $host,
        int $port,
        string $email,
        string $pass
    )
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $email;
        $this->password = $pass;
        $this->mail = new PHPMailer(true);
        $this->mail->isSMTP();
        $this->mail->Host = $host;
        $this->mail->SMTPAuth = true;
        $this->mail->Username = $email;
        $this->mail->Password = $pass;
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port = $port;
    }

    public function send($to, $toName, $from, $subject, $body): bool
    {
        try {
            $this->mail->clearAddresses();
            $this->mail->setFrom($from, 'E-trace+');
            $this->mail->addAddress($to, $toName);
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $body;
            $this->mail->send();
            error_log("[MAILING SERVICE] Email sent.");
            return true;
        } catch (Exception $e) {
            error_log("[MAILING SERVICE] Unable to send email - " . $e->getMessage());
            return false;
        }
    }

    public function sendNewlyAssignedWithEmailVerification($sysad, $user, $defaultPassword, $emailVerificationUrl): bool
    {
        $sEmail     = $sysad['email'];
        $sFullName  = htmlspecialchars($sysad['profile']['first_name'] . ' ' . $sysad['profile']['last_name']);

        $uEmail     = $user['email'];
        $uFirstName = htmlspecialchars($user['profile']['first_name']);
        
        switch ($user['role']) {
            case 'sysad':  $uRole = 'System Administrator'; break;
            case 'dean':   $uRole = 'Dean'; break;
            case 'pstaff': $uRole = 'PESO Staff'; break;
            default:       $uRole = 'Authorized User'; break;
        }

        $subject = "You Have Been Assigned as {$uRole} on E-trace+";

        // 3. Email Template
        $body = <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$subject}</title>
        </head>
        <body style="margin:0; padding:0; background-color:#f9fafb; font-family: Arial, sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f9fafb; padding: 40px 0;">
                <tr>
                    <td align="center">
                        <table width="560" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:8px; border: 1px solid #e5e7eb; overflow:hidden;">
                            <tr>
                                <td style="background-color:#ffffff; padding: 24px 32px; border-bottom: 1px solid #e5e7eb;">
                                    <table width="100%" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td><span style="font-size:18px; font-weight:700; color:#111827; letter-spacing:1px;">E-trace+</span></td>
                                            <td align="right">
                                                <span style="display:inline-block; background-color:#dcfce7; color:#16a34a; font-size:11px; font-weight:600; padding:4px 10px; border-radius:999px; text-transform:uppercase; letter-spacing:1px;">New Account</span>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 32px;">
                                    <p style="margin:0 0 16px; font-size:14px; color:#6b7280;">Hello {$uFirstName},</p>
                                    <p style="margin:0 0 24px; font-size:15px; color:#111827; line-height:1.6;">
                                        You have been assigned as a <strong>{$uRole}</strong> on E-trace+ by <strong>{$sFullName} (Etrace+ System Administrator)</strong>.
                                    </p>

                                    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                                        <tr>
                                            <td style="background-color:#eff6ff; border:1px solid #bfdbfe; border-left: 3px solid #3b82f6; border-radius:6px; padding:16px;">
                                                <p style="margin:0 0 10px; font-size:11px; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:1px;">Your Default Credentials</p>
                                                <p style="margin:0 0 6px; font-size:14px; color:#374151;">
                                                    <strong>Email:</strong> <a href="mailto:{$uEmail}" style="color:#3b82f6; text-decoration:none;">{$uEmail}</a>
                                                </p>
                                                <p style="margin:0 0 12px; font-size:14px; color:#374151;">
                                                    <strong>Password:</strong> <code style="background-color:#dbeafe; color:#1d4ed8; padding:2px 8px; border-radius:4px; font-size:13px;">{$defaultPassword}</code>
                                                </p>
                                                <p style="margin:0; font-size:12px; color:#6b7280;">⚠️ Please change your password after your first login.</p>
                                            </td>
                                        </tr>
                                    </table>

                                    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                                        <tr>
                                            <td style="background-color:#f0f9ff; border:1px solid #bae6fd; border-left: 3px solid #0ea5e9; border-radius:6px; padding:16px;">
                                                <p style="margin:0 0 8px; font-size:11px; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:1px;">Verify Your Email</p>
                                                <p style="margin:0 0 12px; font-size:14px; color:#374151; line-height:1.6;">
                                                    Please verify your email address to activate your account. This link expires in <strong>24 hours</strong>.
                                                </p>
                                                <table cellpadding="0" cellspacing="0">
                                                    <tr>
                                                        <td style="background-color:#0ea5e9; border-radius:6px;">
                                                            <a href="{$emailVerificationUrl}" style="display:inline-block; padding:10px 20px; font-size:13px; font-weight:600; color:#ffffff; text-decoration:none;">Verify Email Address</a>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>

                                    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                                        <tr>
                                            <td style="background-color:#f0fdf4; border:1px solid #bbf7d0; border-left: 3px solid #16a34a; border-radius:6px; padding:16px;">
                                                <p style="margin:0 0 6px; font-size:11px; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:1px;">Assigned by</p>
                                                <p style="margin:0; font-size:14px; color:#374151; line-height:1.5;">{$sFullName} &mdash; <a href="mailto:{$sEmail}" style="color:#16a34a; text-decoration:none;">{$sEmail}</a></p>
                                            </td>
                                        </tr>
                                    </table>

                                </td>
                            </tr>
                            <tr>
                                <td style="background-color:#f9fafb; border-top:1px solid #e5e7eb; padding:20px 32px;">
                                    <p style="margin:0; font-size:12px; color:#9ca3af; line-height:1.5;">This is an automated message from E-trace+. Please do not reply directly to this email.</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        HTML;

        return $this->send($uEmail, $uFirstName, $sEmail, $subject, $body);
    }

    public function sendEmailVerified($user, $loginUrl): bool
    {
        $uEmail     = $user['email'];
        $uFirstName = htmlspecialchars($user['profile']['first_name']);
        
        switch ($user['role']) {
            case 'sysad':   $uRole = 'System Administrator'; break;
            case 'dean':    $uRole = 'Dean'; break;
            case 'pstaff':  $uRole = 'PESO Staff'; break;
            case 'company': $uRole = 'Company'; break;
            case 'alumni':  $uRole = 'Alumni'; break;
            default:        $uRole = 'Authorized User'; break;
        }

        $subject = "Your {$uRole} Account is Now Verified";

        // Email Template
        $body = <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$subject}</title>
        </head>
        <body style="margin:0; padding:0; background-color:#f9fafb; font-family: Arial, sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f9fafb; padding: 40px 0;">
                <tr>
                    <td align="center">
                        <table width="560" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:8px; border: 1px solid #e5e7eb; overflow:hidden;">
                            <tr>
                                <td style="background-color:#ffffff; padding: 24px 32px; border-bottom: 1px solid #e5e7eb;">
                                    <table width="100%" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td><span style="font-size:18px; font-weight:700; color:#111827; letter-spacing:1px;">E-trace+</span></td>
                                            <td align="right">
                                                <span style="display:inline-block; background-color:#dcfce7; color:#16a34a; font-size:11px; font-weight:600; padding:4px 10px; border-radius:999px; text-transform:uppercase; letter-spacing:1px;">Verified</span>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 32px;">
                                    <p style="margin:0 0 16px; font-size:14px; color:#6b7280;">Hello {$uFirstName},</p>
                                    <p style="margin:0 0 32px; font-size:15px; color:#111827; line-height:1.6;">
                                        Your email address has been successfully verified. Your account as a <strong>{$uRole}</strong> is now fully active. You can now access all the features associated with your role on the E-trace+ platform.
                                    </p>

                                    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:8px;">
                                        <tr>
                                            <td align="center">
                                                <table cellpadding="0" cellspacing="0">
                                                    <tr>
                                                        <td style="background-color:#111827; border-radius:6px;">
                                                            <a href="{$loginUrl}" style="display:inline-block; padding:14px 32px; font-size:14px; font-weight:600; color:#ffffff; text-decoration:none;">Login to Etrace+</a>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td style="background-color:#f9fafb; border-top:1px solid #e5e7eb; padding:20px 32px;">
                                    <p style="margin:0; font-size:12px; color:#9ca3af; line-height:1.5;">
                                        <strong>Registered Email:</strong> {$uEmail}<br>
                                        This is an automated notification. Please do not reply to this email.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        HTML;

        return $this->send($uEmail, $uFirstName, $this->username, $subject, $body);
    }

    public function sendNewlyRegisteredCompanyWithEmailVerification($user, $emailVerificationUrl): bool
    {
        $uEmail      = $user['email'];
        $companyName = htmlspecialchars($user['profile']['name']);

        $subject = "Verify Your Company Account on E-trace+";

        // Email Template
        $body = <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$subject}</title>
        </head>
        <body style="margin:0; padding:0; background-color:#f9fafb; font-family: Arial, sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f9fafb; padding: 40px 0;">
                <tr>
                    <td align="center">
                        <table width="560" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:8px; border: 1px solid #e5e7eb; overflow:hidden;">
                            <tr>
                                <td style="background-color:#ffffff; padding: 24px 32px; border-bottom: 1px solid #e5e7eb;">
                                    <table width="100%" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td><span style="font-size:18px; font-weight:700; color:#111827; letter-spacing:1px;">E-trace+</span></td>
                                            <td align="right">
                                                <span style="display:inline-block; background-color:#fef3c7; color:#d97706; font-size:11px; font-weight:600; padding:4px 10px; border-radius:999px; text-transform:uppercase; letter-spacing:1px;">Verification Required</span>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 32px;">
                                    <p style="margin:0 0 16px; font-size:14px; color:#6b7280;">Welcome, {$companyName},</p>
                                    <p style="margin:0 0 24px; font-size:15px; color:#111827; line-height:1.6;">
                                        Thank you for registering your company with <strong>E-trace+</strong>. To complete your registration and begin posting opportunities, please verify your email address.
                                    </p>

                                    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                                        <tr>
                                            <td style="background-color:#f0f9ff; border:1px solid #bae6fd; border-left: 3px solid #0ea5e9; border-radius:6px; padding:20px; text-align:center;">
                                                <p style="margin:0 0 16px; font-size:14px; color:#0369a1; line-height:1.5;">
                                                    Click the button below to confirm your email. This link will expire in 24 hours.
                                                </p>
                                                <table cellpadding="0" cellspacing="0" align="center">
                                                    <tr>
                                                        <td style="background-color:#0ea5e9; border-radius:6px;">
                                                            <a href="{$emailVerificationUrl}" style="display:inline-block; padding:12px 30px; font-size:14px; font-weight:600; color:#ffffff; text-decoration:none;">Verify Company Email</a>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>

                                    <p style="margin:0 0 12px; font-size:13px; color:#6b7280; line-height:1.5;">
                                        Once verified, our team will review your company profile. You will receive another notification once your account has been fully approved by the System Administrator or PESO Staff.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style="background-color:#f9fafb; border-top:1px solid #e5e7eb; padding:20px 32px;">
                                    <p style="margin:0; font-size:12px; color:#9ca3af; line-height:1.5;">
                                        <strong>Account Email:</strong> {$uEmail}<br>
                                        If you did not create this account, please ignore this email.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        HTML;

        return $this->send($uEmail, $companyName, $this->username, $subject, $body);
    }

    public function sendNewlyRegisteredAlumniWithEmailVerification($user, $emailVerificationUrl): bool
    {
        $uEmail   = $user['email'];
        $userName = htmlspecialchars($user['profile']['first_name']);

        $subject = "Verify Your Alumni Account - E-trace+";

        // Email Template
        $body = <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$subject}</title>
        </head>
        <body style="margin:0; padding:0; background-color:#f9fafb; font-family: Arial, sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f9fafb; padding: 40px 0;">
                <tr>
                    <td align="center">
                        <table width="560" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:8px; border: 1px solid #e5e7eb; overflow:hidden;">
                            <tr>
                                <td style="background-color:#ffffff; padding: 24px 32px; border-bottom: 1px solid #e5e7eb;">
                                    <table width="100%" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td><span style="font-size:18px; font-weight:700; color:#111827; letter-spacing:1px;">E-trace+</span></td>
                                            <td align="right">
                                                <span style="display:inline-block; background-color:#dcfce7; color:#15803d; font-size:11px; font-weight:600; padding:4px 10px; border-radius:999px; text-transform:uppercase; letter-spacing:1px;">Alumni Portal</span>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 32px;">
                                    <p style="margin:0 0 16px; font-size:14px; color:#6b7280;">Hi {$userName},</p>
                                    <p style="margin:0 0 24px; font-size:15px; color:#111827; line-height:1.6;">
                                        Welcome to the <strong>E-trace+</strong> community! We're excited to help you take the next step in your career. By verifying your account, you'll gain access to exclusive job opportunities and career tracking tools.
                                    </p>

                                    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                                        <tr>
                                            <td style="background-color:#f0fdf4; border:1px solid #bbf7d0; border-left: 3px solid #22c55e; border-radius:6px; padding:20px; text-align:center;">
                                                <p style="margin:0 0 16px; font-size:14px; color:#166534; line-height:1.5;">
                                                    Please confirm your email address to activate your portal and start exploring jobs.
                                                </p>
                                                <table cellpadding="0" cellspacing="0" align="center">
                                                    <tr>
                                                        <td style="background-color:#111827; border-radius:6px;">
                                                            <a href="{$emailVerificationUrl}" style="display:inline-block; padding:12px 30px; font-size:14px; font-weight:600; color:#ffffff; text-decoration:none;">Verify My Account</a>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>

                                    <p style="margin:0 0 12px; font-size:13px; color:#6b7280; line-height:1.5;">
                                        <strong>What's next?</strong><br>
                                        Once verified, you can complete your profile, upload your curriculum viate, and send it into job posts that are posted by our partner companies.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style="background-color:#f9fafb; border-top:1px solid #e5e7eb; padding:20px 32px;">
                                    <p style="margin:0; font-size:12px; color:#9ca3af; line-height:1.5;">
                                        <strong>Registered Email:</strong> {$uEmail}<br>
                                        If you did not sign up for E-trace+, you can safely ignore this message.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        HTML;

        return $this->send($uEmail, $userName, $this->username, $subject, $body);
    }
}