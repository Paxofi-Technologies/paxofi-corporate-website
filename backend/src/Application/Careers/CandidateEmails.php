<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Careers;

/**
 * Candidate emails from the PIF 2026 launch pack (D-019, D-027; Launch
 * Production Pack section 9). The acknowledgement is sent automatically; staff
 * send the others from the application page and can edit the wording first.
 * Formal decisions are always by email from hr@paxofi.com.
 *
 * Wording follows the public messaging rules: always the engagement type, never
 * "job" or "salary", no promise of pay, no internal stage names.
 *
 * Placeholders in curly braces are filled in by the system: {first_name},
 * {role}, {reference}, {hours}, {engagement} (Fellow, Intern or Volunteer),
 * {agreement} (the agreement for that type), {hours_rule}, {term_line},
 * {conversation} (interview or short conversation), {timeline}, {privacy_url}.
 * Square brackets are for staff to fill in before sending.
 */
final class CandidateEmails
{
    /** @var array<string, array{label: string, subject: string, body: string, stage: ?string, types?: list<string>}> */
    public const TEMPLATES = [
        'acknowledgement' => [
            'label' => 'Application received (sent automatically)',
            'subject' => 'We received your application: {role} ({reference})',
            'body' => "Hello {first_name},\n\nThank you for applying to the Paxofi Innovation Fellowship (PIF) as a {role}. Your application reference is {reference}.\n\nWhat happens next:\n1. We check every application against the role's eligibility and evidence criteria.\n2. If you progress, we invite you to the next step: {steps}.\n3. We email you at every step, from hr@paxofi.com, whether or not your application moves forward. WhatsApp is only ever used for reminders, never for decisions.\n\n{timeline}\n\nA reminder of the terms for a PIF {engagement}: remote, {hours_rule}, a term of {term}. PIF is an unpaid learning and development programme, not employment.\n\nHow we use your information is explained at {privacy_url}. A person reviews every decision; none is made by a machine alone.\n\nQuestions? Reply to this email.\n\nPaxofi Technologies — Recruitment",
            'stage' => null,
        ],
        'screening_progress' => [
            'label' => 'Screening: moving forward',
            'subject' => 'Your application is moving forward: {role} ({reference})',
            'body' => "Hello {first_name},\n\nGood news: your application for {role} has passed our first review and you are on our shortlist.\n\nThe next step is {next_step}. We will email you the details shortly.\n\nPaxofi Technologies — Recruitment",
            'stage' => 'shortlisted',
        ],
        'screening_not_progressing' => [
            'label' => 'Screening: not moving forward',
            'subject' => 'Your application for {role} ({reference})',
            'body' => "Hello {first_name},\n\nThank you for your interest in the Paxofi Innovation Fellowship and for the time you put into your application for {role}.\n\nAfter careful review, we will not be moving your application forward at this time. This decision reflects the needs of this intake and the evidence in this application; it is not a judgement of your potential.\n\nYou are welcome to apply again in future, for this or another role, when you can show new evidence.\n\nWe keep your application for 12 months and then delete it.\n\nPaxofi Technologies — Recruitment",
            'stage' => 'rejected',
        ],
        'assessment_invitation' => [
            'label' => 'Assessment invitation (Fellows and Interns)',
            'subject' => 'Your role assessment: {role} ({reference})',
            'body' => "Hello {first_name},\n\nWe would like to invite you to the role assessment for {role}.\n\nWhat to do: [describe the task]\nDeadline: [date and time, with time zone]\nHow to submit: reply to this email with your work attached or linked.\n\nThe assessment tests capability and should take no more than 3 hours. It is never work that Paxofi intends to use.\n\nPaxofi Technologies — Recruitment",
            'stage' => 'assessment',
            'types' => ['fellow', 'intern'],
        ],
        'interview_invitation' => [
            'label' => 'Interview or conversation invitation',
            'subject' => 'Invitation to a {conversation}: {role} ({reference})',
            'body' => "Hello {first_name},\n\nWe would like to invite you to a {conversation} for {role}.\n\nDate and time: [date and time, with time zone]\nFormat: [video call link]\nLength: about [minutes] minutes\nHow to prepare: be ready to talk about your motivation, your experience and your availability, and bring any questions you have about the programme.\n\nPlease reply to confirm, or suggest another time.\n\nPaxofi Technologies — Recruitment",
            'stage' => 'interview',
        ],
        'selection' => [
            'label' => 'Selected (offer letter and agreement)',
            'subject' => 'You have been selected: {role} ({reference})',
            'body' => "Hello {first_name},\n\nCongratulations! We are delighted to offer you a place in the Paxofi Innovation Fellowship as a {role}.\n\nAttached are your offer letter and the {agreement}. They set out the terms: remote, {hours_rule}. {term_line} PIF is an unpaid learning and development programme, not employment.\n\nPlease read both documents carefully and return the signed {agreement} within 5 working days, by [date]. Reply with any questions.\n\nPaxofi Technologies — Recruitment",
            'stage' => 'agreement_pending',
        ],
        'onboarding' => [
            'label' => 'Onboarding instructions',
            'subject' => 'Welcome to the Paxofi Innovation Fellowship: next steps',
            'body' => "Hello {first_name},\n\nThank you for returning your signed {agreement}. Welcome to the Paxofi Innovation Fellowship as a {role}!\n\nYour onboarding:\nStart date: [date]\nFirst session: [date, time and link]\nCollaboration: you will receive an invitation to our Slack workspace.\nYour supervisor: [name]\n\nPaxofi Technologies — Recruitment",
            'stage' => 'onboarding',
        ],
        'not_selected' => [
            'label' => 'Not selected (after assessment or interview)',
            'subject' => 'Your application for {role} ({reference})',
            'body' => "Hello {first_name},\n\nThank you for the time and effort you gave to the {conversation} for {role}.\n\nAfter careful consideration, we will not be offering you a place in this intake. We know this is disappointing. Your application showed real strengths, and you are welcome to apply again in future.\n\nWe keep your application for 12 months and then delete it.\n\nPaxofi Technologies — Recruitment",
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
        $replace = [];
        foreach (['first_name', 'role', 'reference', 'hours', 'engagement', 'agreement', 'hours_rule', 'term', 'term_line', 'steps', 'next_step', 'conversation', 'timeline', 'privacy_url'] as $key) {
            $replace['{' . $key . '}'] = $values[$key] ?? '';
        }

        return strtr($text, $replace);
    }

    /**
     * The placeholder values for one application.
     *
     * @param array<string, mixed> $application full_name, role_title, reference, hours_per_week, engagement_type
     * @return array<string, string>
     */
    public static function values(array $application, string $privacyUrl = 'https://careers.paxofi.com/privacy', string $timeline = ''): array
    {
        $first = strtok((string) $application['full_name'], ' ') ?: (string) $application['full_name'];
        $type = EngagementType::tryFrom((string) ($application['engagement_type'] ?? '')) ?? EngagementType::Fellow;
        $volunteer = $type === EngagementType::Volunteer;

        return [
            'first_name' => $first,
            'role' => (string) $application['role_title'],
            'reference' => (string) $application['reference'],
            'hours' => (string) $application['hours_per_week'],
            'engagement' => $type->label(),
            'agreement' => $type->agreement(),
            'hours_rule' => $type->hoursRule(),
            'term' => $type->term(),
            'term_line' => match ($type) {
                EngagementType::Fellow => 'Term: 24 programme weeks.',
                EngagementType::Intern => 'Term: [12 or 24] weeks, as set out in your offer letter.',
                EngagementType::Volunteer => 'Term: [8 to 24] weeks, as set out in your offer letter.',
            },
            'steps' => $volunteer ? 'a short conversation' : 'a short role assessment, then a structured interview',
            'next_step' => $volunteer ? 'a short conversation with the team' : 'a short role assessment',
            'conversation' => $volunteer ? 'short conversation' : 'interview',
            'timeline' => $timeline,
            'privacy_url' => $privacyUrl,
        ];
    }

    /** @return list<array{key: string, label: string, subject: string, body: string, stage: ?string}> templates filled in for one application */
    public static function forApplication(array $application, string $privacyUrl = 'https://careers.paxofi.com/privacy'): array
    {
        $values = self::values($application, $privacyUrl);
        $type = EngagementType::tryFrom((string) ($application['engagement_type'] ?? '')) ?? EngagementType::Fellow;
        $out = [];
        foreach (self::TEMPLATES as $key => $template) {
            if ($key === 'acknowledgement' || !in_array($type->value, $template['types'] ?? ['fellow', 'intern', 'volunteer'], true)) {
                continue;
            }
            $out[] = ['key' => $key, 'label' => $template['label'], 'subject' => self::fill($template['subject'], $values), 'body' => self::fill($template['body'], $values), 'stage' => $template['stage']];
        }

        return $out;
    }
}
