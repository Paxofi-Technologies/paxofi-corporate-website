<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Contact;

use Paxofi\CorporateWebsite\Application\Mail\Email;
use Paxofi\CorporateWebsite\Application\Mail\MailSettings;

/** The email staff receive for a new enquiry (D-016). Reply-To is the visitor, so "Reply" answers them. */
final class EnquiryAlert
{
    public static function email(MailSettings $settings, string $enquiryId, EnquirySubmission $submission): ?Email
    {
        if (!$settings->enabled || $settings->enquiryAlertTo === []) {
            return null;
        }
        $company = $submission->company !== null && $submission->company !== '' ? $submission->company : 'not given';
        $text = implode("\n", [
            'A new enquiry arrived through the contact form on ' . $settings->siteUrl . '.',
            '',
            'Name: ' . Email::oneLine($submission->name),
            'Email: ' . $submission->email,
            'Company: ' . Email::oneLine($company),
            '',
            'Message:',
            str_replace(["\r\n", "\r"], "\n", $submission->message),
            '',
            'Open it in the staff area (sign in first): ' . $settings->staffLink('/admin/enquiries/' . $enquiryId),
            'Replying to this email answers the sender directly. Remember to update the enquiry status in the staff area; the target is a reply within 2 business days (D-007).',
        ]) . "\n";

        return new Email(
            $settings->enquiryAlertTo,
            'New enquiry from ' . Email::oneLine($submission->name) . ($submission->company ? ' (' . Email::oneLine($submission->company) . ')' : ''),
            $text,
            $submission->email,
            'enquiry_alert',
        );
    }
}
