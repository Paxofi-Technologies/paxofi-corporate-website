<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Bootstrap;

use Paxofi\Core\Configuration\Environment;
use Paxofi\CorporateWebsite\Application\Careers\RecruitmentSettings;
use Paxofi\CorporateWebsite\Application\Mail\Email;
use Paxofi\CorporateWebsite\Application\Mail\MailSettings;
use Paxofi\CorporateWebsite\Application\Mail\MailTransport;
use Paxofi\CorporateWebsite\Infrastructure\Mail\PhpMailTransport;
use Paxofi\CorporateWebsite\Infrastructure\Mail\SmtpTransport;

/** Typed application settings derived from the PCF environment contract. */
final readonly class Settings
{
    /** @param list<string> $corsAllowedOrigins */
    public function __construct(
        public Environment $environment,
        public bool $debug,
        public array $corsAllowedOrigins,
        public int $contactRateLimitMax,
        public int $contactRateLimitWindowMinutes,
        public ?string $adminSetupToken = null,
        public ?string $mfaEncryptionKey = null,
        public ?string $mediaStoragePath = null,
        public ?MailTransport $mailTransport = null,
        public ?MailSettings $mail = null,
        public RecruitmentSettings $recruitment = new RecruitmentSettings(),
    ) {
    }

    public static function fromEnvironment(Environment $environment): self
    {
        $origins = array_values(array_filter(array_map(
            'trim',
            explode(',', $environment->get('CORS_ALLOWED_ORIGINS', '') ?? ''),
        )));

        return new self(
            environment: $environment,
            // Debug output is never permitted in production, whatever APP_DEBUG says.
            debug: !$environment->isProduction() && $environment->boolean('APP_DEBUG', false),
            corsAllowedOrigins: $origins,
            contactRateLimitMax: max(1, $environment->integer('CONTACT_RATE_LIMIT_MAX', 5) ?? 5),
            contactRateLimitWindowMinutes: max(1, $environment->integer('CONTACT_RATE_LIMIT_WINDOW_MINUTES', 10) ?? 10),
            // One-time code for creating the first administrator (D-009); ignored when shorter than 32 characters.
            adminSetupToken: ($token = trim($environment->get('ADMIN_SETUP_TOKEN', '') ?? '')) === '' ? null : $token,
            // Key for encrypting authenticator secrets (D-010); two-factor stays unavailable when shorter than 32 characters.
            mfaEncryptionKey: strlen($key = trim($environment->get('MFA_ENCRYPTION_KEY', '') ?? '')) >= 32 ? $key : null,
            // Folder for uploaded media (D-012), outside the release folders; uploads stay off while unset.
            mediaStoragePath: ($path = trim($environment->get('MEDIA_STORAGE_PATH', '') ?? '')) === '' ? null : $path,
            mailTransport: $transport = self::mailTransport($environment),
            mail: new MailSettings(
                enabled: $transport !== null,
                enquiryAlertTo: Email::addressList($environment->get('ENQUIRY_ALERT_TO', '')),
                errorAlertTo: Email::addressList($environment->get('ERROR_ALERT_TO', '')),
                siteUrl: rtrim(trim($environment->get('STAFF_AREA_URL', '') ?? '') ?: ($origins[0] ?? 'https://corporate.paxofi.com'), '/'),
            ),
            // careers.paxofi.com (D-018, D-019): who hears about new applications, and where candidates reply.
            recruitment: new RecruitmentSettings(
                alertTo: Email::addressList($environment->get('RECRUITMENT_ALERT_TO', '')),
                replyTo: Email::isAddress($reply = trim($environment->get('RECRUITMENT_REPLY_TO', '') ?? '')) ? $reply : 'hr@paxofi.com',
                careersSiteUrl: rtrim(trim($environment->get('CAREERS_SITE_URL', '') ?? '') ?: 'https://careers.paxofi.com', '/'),
            ),
        );
    }

    /**
     * Email sending (D-016): MAIL_TRANSPORT=smtp (the company mailbox, preferred)
     * or mail (the server's sendmail). Off when unset or incomplete.
     */
    private static function mailTransport(Environment $environment): ?MailTransport
    {
        $from = trim($environment->get('MAIL_FROM_ADDRESS', '') ?? '');
        $fromName = Email::oneLine($environment->get('MAIL_FROM_NAME', 'Paxofi Technologies') ?? 'Paxofi Technologies');
        if (!Email::isAddress($from)) {
            return null;
        }

        return match (strtolower(trim($environment->get('MAIL_TRANSPORT', '') ?? ''))) {
            'smtp' => ($host = trim($environment->get('MAIL_HOST', '') ?? '')) === '' ? null : new SmtpTransport(
                $host,
                $environment->integer('MAIL_PORT', 465) ?? 465,
                in_array($encryption = strtolower(trim($environment->get('MAIL_ENCRYPTION', 'ssl') ?? 'ssl')), ['ssl', 'tls', 'none'], true) ? $encryption : 'ssl',
                trim($environment->get('MAIL_USERNAME', '') ?? '') ?: null,
                $environment->get('MAIL_PASSWORD', '') ?: null,
                $from,
                $fromName,
            ),
            'mail' => new PhpMailTransport($from, $fromName),
            default => null,
        };
    }
}
