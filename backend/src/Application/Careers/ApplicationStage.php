<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Careers;

/**
 * Application stages from the PIF 2026 launch pack (decision D-019):
 * Applied → Screening → Shortlisted → Assessment → Interview → Selected →
 * Agreement pending → Accepted → Onboarding → Active, or closed as Declined,
 * Withdrawn or Rejected. Closed applications are deleted 12 months later.
 */
enum ApplicationStage: string
{
    case Applied = 'applied';
    case Screening = 'screening';
    case Shortlisted = 'shortlisted';
    case Assessment = 'assessment';
    case Interview = 'interview';
    case Selected = 'selected';
    case AgreementPending = 'agreement_pending';
    case Accepted = 'accepted';
    case Onboarding = 'onboarding';
    case Active = 'active';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Applied => 'Applied',
            self::Screening => 'Screening',
            self::Shortlisted => 'Shortlisted',
            self::Assessment => 'Assessment',
            self::Interview => 'Interview',
            self::Selected => 'Selected',
            self::AgreementPending => 'Agreement pending',
            self::Accepted => 'Accepted',
            self::Onboarding => 'Onboarding',
            self::Active => 'Active fellow',
            self::Declined => 'Declined by candidate',
            self::Withdrawn => 'Withdrawn',
            self::Rejected => 'Not selected',
        };
    }

    /** Closed without joining: the application is deleted 12 months later. */
    public function isClosed(): bool
    {
        return in_array($this, [self::Declined, self::Withdrawn, self::Rejected], true);
    }

    /** @return list<array{value: string, label: string, closed: bool}> */
    public static function options(): array
    {
        return array_map(static fn (self $s): array => ['value' => $s->value, 'label' => $s->label(), 'closed' => $s->isClosed()], self::cases());
    }
}
