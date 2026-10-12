<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Careers;

/**
 * The three PIF engagement types (Launch Decision Pack Revision 1, PTMS-HR-STD-001,
 * Role Activation Pack v2.0 section 3) and the rules that differ between them:
 * hours, term, who it is for, the agreement and the selection thresholds.
 */
enum EngagementType: string
{
    case Fellow = 'fellow';
    case Intern = 'intern';
    case Volunteer = 'volunteer';

    public function label(): string
    {
        return match ($this) {
            self::Fellow => 'Fellow',
            self::Intern => 'Intern',
            self::Volunteer => 'Volunteer',
        };
    }

    public function minHours(): int
    {
        return $this === self::Volunteer ? 10 : 15;
    }

    public function targetHours(): int
    {
        return $this === self::Volunteer ? 15 : 20;
    }

    /** The programme term, as published. */
    public function term(): string
    {
        return match ($this) {
            self::Fellow => '24 programme weeks',
            self::Intern => '12 or 24 weeks',
            self::Volunteer => '8 to 24 weeks, project-based',
        };
    }

    public function forWhom(): string
    {
        return match ($this) {
            self::Fellow => 'People with foundation skills who want to own real deliverables.',
            self::Intern => 'Students, recent graduates and early-career learners. SIWES and industrial-training placements are welcome.',
            self::Volunteer => 'People who want to contribute to a defined project and build experience.',
        };
    }

    /** The agreement sent with the offer letter. */
    public function agreement(): string
    {
        return match ($this) {
            self::Fellow => 'Fellowship Agreement',
            self::Intern => 'Internship Agreement',
            self::Volunteer => 'Volunteer Agreement',
        };
    }

    /** "at least 15 hours a week, with 20 as the normal target" */
    public function hoursRule(): string
    {
        return "at least {$this->minHours()} hours a week, with {$this->targetHours()} as the normal target";
    }

    /** Fellows and Interns score the evidence review and do a role task; Volunteers have a short conversation only. */
    public function hasEvidenceGate(): bool
    {
        return $this !== self::Volunteer;
    }

    /** Evidence review: progress at this score out of 35 (Launch Production Pack section 8). */
    public function evidencePass(): ?int
    {
        return match ($this) {
            self::Fellow => 21,
            self::Intern => 18,
            self::Volunteer => null,
        };
    }

    /** Interview (Volunteers: short conversation): select at this score. */
    public function interviewPass(): int
    {
        return match ($this) {
            self::Fellow => 24,
            self::Intern => 21,
            self::Volunteer => 15,
        };
    }

    public function interviewOutOf(): int
    {
        return $this === self::Volunteer ? 25 : 35;
    }

    /** @return array{type: string, label: string, term: string, hours_min: int, hours_target: int, for_whom: string} what the careers site shows */
    public function toPublicArray(): array
    {
        return ['type' => $this->value, 'label' => $this->label(), 'term' => $this->term(), 'hours_min' => $this->minHours(), 'hours_target' => $this->targetHours(), 'for_whom' => $this->forWhom()];
    }
}
