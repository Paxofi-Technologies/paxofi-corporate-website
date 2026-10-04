<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Careers;

/**
 * Candidate emails from the PIF 2026 launch pack (D-019). The acknowledgement
 * is sent automatically; staff send the others from the application page and
 * can edit the wording first. Formal decisions are always by email.
 * Placeholders: {first_name}, {role}, {reference}, {hours}.
 */
final class CandidateEmails
{
    public const TEMPLATES = [
        'acknowledgement' => [
            'label' => 'Application received (sent automatically)',
            'subject' => 'We received your application: {role} ({reference})',
            'body' => "Hello {first_name},\n\nThank you for applying to become a {role} in the Paxofi Innovation Fellowship. Your application reference is {reference}.\n\nWhat happens next:\n1. We check every application against the role's eligibility and evidence criteria.\n2. If you progress, we invite you to a short role assessment, then a structured interview.\n3. We email you at every step, whether or not your application moves forward.\n\nRecruitment is rolling, so this can take a few weeks. You do not need to apply again.\n\nA reminder of the fellowship terms: remote, at least 15 hours a week (20 is the normal target), and no stipend, allowance or salary at this time.\n\nQuestions? Reply to this email.\n\nPaxofi Technologies — Recruitment",
            'stage' => null,
        ],
        'screening_progress' => [
            'label' => 'Screening: moving forward',
            'subject' => 'Your application is moving forward: {role} ({reference})',
            'body' => "Hello {first_name},\n\nGood news: your application for {role} has passed our first review and you are on our shortlist.\n\nThe next step is a short role assessment. We will email you the details and a deadline shortly.\n\nPaxofi Technologies — Recruitment",
            'stage' => 'shortlisted',
        ],
        'screening_not_progressing' => [
            'label' => 'Screening: not moving forward',
            'subject' => 'Your application for {role} ({reference})',
            'body' => "Hello {first_name},\n\nThank you for your interest in the Paxofi Innovation Fellowship and for the time you put into your application for {role}.\n\nAfter careful review, we will not be moving your application forward at this time. This decision reflects the needs of this intake and the evidence in this application; it is not a judgement of your potential.\n\nRecruitment is continuous, so you are welcome to apply again in future, for this or another role, when you can show new evidence.\n\nWe keep your application for 12 months and then delete it.\n\nPaxofi Technologies — Recruitment",
            'stage' => 'rejected',
        ],
        'assessment_invitation' => [
            'label' => 'Assessment invitation',
            'subject' => 'Your role assessment: {role} ({reference})',
            'body' => "Hello {first_name},\n\nWe would like to invite you to the role assessment for {role}.\n\nWhat to do: [describe the task]\nDeadline: [date and time, with time zone]\nHow to submit: reply to this email with your work attached or linked.\n\nThe assessment tests capability; it is not production work for Paxofi. Please spend no more than [hours] on it.\n\nPaxofi Technologies — Recruitment",
            'stage' => 'assessment',
        ],
        'interview_invitation' => [
            'label' => 'Interview invitation',
            'subject' => 'Interview invitation: {role} ({reference})',
            'body' => "Hello {first_name},\n\nThank you for your assessment. We would like to invite you to a structured interview for {role}.\n\nDate and time: [date and time, with time zone]\nFormat: [video call link]\nLength: about [minutes] minutes\nHow to prepare: be ready to talk through your assessment and examples of your work.\n\nPlease reply to confirm, or suggest another time.\n\nPaxofi Technologies — Recruitment",
            'stage' => 'interview',
        ],
        'selection' => [
            'label' => 'Selected (with PIF Participant Agreement)',
            'subject' => 'You have been selected: {role} ({reference})',
            'body' => "Hello {first_name},\n\nCongratulations! We are delighted to offer you a place in the Paxofi Innovation Fellowship as a {role}.\n\nAttached / linked is the PIF Participant Agreement. It sets out the fellowship terms: remote, at least 15 hours a week (20 is the normal target), a [3, 6 or 12]-month term, and no stipend, allowance or salary at this time.\n\nPlease read it carefully and return it signed by [date]. Reply with any questions.\n\nPaxofi Technologies — Recruitment",
            'stage' => 'agreement_pending',
        ],
        'onboarding' => [
            'label' => 'Onboarding instructions',
            'subject' => 'Welcome to the Paxofi Innovation Fellowship: next steps',
            'body' => "Hello {first_name},\n\nThank you for returning your PIF Participant Agreement. Welcome to the Paxofi Innovation Fellowship!\n\nYour onboarding:\nStart date: [date]\nFirst session: [date, time and link]\nCollaboration: you will receive an invitation to our Slack workspace.\nYour supervisor: [name]\n\nPaxofi Technologies — Recruitment",
            'stage' => 'onboarding',
        ],
        'not_selected' => [
            'label' => 'Not selected (after assessment or interview)',
            'subject' => 'Your application for {role} ({reference})',
            'body' => "Hello {first_name},\n\nThank you for the time and effort you gave to the assessment and interview for {role}.\n\nAfter careful consideration, we will not be offering you a place in this intake. We know this is disappointing. Your application showed real strengths, and you are welcome to apply again in future.\n\nWe keep your application for 12 months and then delete it.\n\nPaxofi Technologies — Recruitment",
            'stage' => 'rejected',
        ],
        'withdrawal' => [
            'label' => 'Withdrawal acknowledged',
            'subject' => 'Your application has been withdrawn ({reference})',
            'body' => "Hello {first_name},\n\nWe have withdrawn your application for {role}, as you asked. Thank you for your interest in Paxofi.\n\nWe keep the application for 12 months and then delete it; reply to this email if you would like it deleted sooner.\n\nPaxofi Technologies — Recruitment",
            'stage' => 'withdrawn',
        ],
    ];

    /** @param array<string, string> $values */
    public static function fill(string $text, array $values): string
    {
        return strtr($text, ['{first_name}' => $values['first_name'] ?? '', '{role}' => $values['role'] ?? '', '{reference}' => $values['reference'] ?? '', '{hours}' => $values['hours'] ?? '']);
    }

    /** @param array<string, mixed> $application */
    public static function values(array $application): array
    {
        $first = strtok((string) $application['full_name'], ' ') ?: (string) $application['full_name'];

        return ['first_name' => $first, 'role' => (string) $application['role_title'], 'reference' => (string) $application['reference'], 'hours' => (string) $application['hours_per_week']];
    }

    /** @return list<array{key: string, label: string, subject: string, body: string, stage: ?string}> templates filled in for one application */
    public static function forApplication(array $application): array
    {
        $values = self::values($application);
        $out = [];
        foreach (self::TEMPLATES as $key => $template) {
            if ($key === 'acknowledgement') {
                continue;
            }
            $out[] = ['key' => $key, 'label' => $template['label'], 'subject' => self::fill($template['subject'], $values), 'body' => self::fill($template['body'], $values), 'stage' => $template['stage']];
        }

        return $out;
    }
}
