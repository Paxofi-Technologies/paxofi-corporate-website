<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Careers;

/**
 * The canonical record statuses (PIF Readiness baseline, PIF-CHR-001 section 5; D-019, D-027):
 * Applied → Screening → Shortlisted → Assessment → Interview → Selected →
 * Agreement pending → Accepted → Onboarding → Active → Completed → Alumni, or
 * closed as Declined, Withdrawn, Rejected or Exited early.
 *
 * Applications that never became participants are deleted 12 months after
 * they close. Participant records (Accepted onwards) are never deleted
 * automatically (owner decision, 12 Oct 2026).
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
    case Completed = 'completed';
    case Alumni = 'alumni';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
    case Rejected = 'rejected';
    case ExitedEarly = 'exited_early';

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
            self::Active => 'Active',
            self::Completed => 'Completed',
            self::Alumni => 'Alumni',
            self::Declined => 'Declined by candidate',
            self::Withdrawn => 'Withdrawn',
            self::Rejected => 'Not selected',
            self::ExitedEarly => 'Exited early',
        };
    }

    /** Closed without finishing: the record has an end date. */
    public function isClosed(): bool
    {
        return in_array($this, [self::Declined, self::Withdrawn, self::Rejected, self::ExitedEarly], true);
    }

    /** A person who joined: accepted, taking part or finished. Never deleted automatically. */
    public function isParticipant(): bool
    {
        return in_array($this, [self::Accepted, self::Onboarding, self::Active, self::Completed, self::Alumni, self::ExitedEarly], true);
    }

    /** @return list<string> stages whose records the retention purge may delete (D-019, D-027) */
    public static function purgeable(): array
    {
        return array_values(array_map(
            static fn (self $stage): string => $stage->value,
            array_filter(self::cases(), static fn (self $stage): bool => !$stage->isParticipant()),
        ));
    }

    /** @return list<string> stages in which a second application for the same role is not needed */
    public static function inProgress(): array
    {
        return array_values(array_map(
            static fn (self $stage): string => $stage->value,
            array_filter(self::cases(), static fn (self $stage): bool => !$stage->isClosed() && $stage !== self::Completed && $stage !== self::Alumni),
        ));
    }

    /** @return list<array{value: string, label: string, closed: bool}> */
    public static function options(): array
    {
        return array_map(static fn (self $s): array => ['value' => $s->value, 'label' => $s->label(), 'closed' => $s->isClosed()], self::cases());
    }
}
