<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Reopened = 'reopened';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Assigned => 'Assigned',
            self::InProgress => 'In Progress',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
            self::Reopened => 'Reopened',
        };
    }

    /**
     * Transisi status yang diizinkan (PRD FR-06).
     *
     * Catatan: penugasan (termasuk penugasan ulang) tidak lewat peta ini,
     * tetapi lewat TicketWorkflow::assign().
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::Assigned],
            self::Assigned => [self::InProgress],
            self::InProgress => [self::Resolved],
            self::Resolved => [self::Closed, self::Reopened],
            self::Reopened => [self::Assigned, self::InProgress],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /**
     * Apakah tiket pada status ini masih boleh ditugaskan atau ditugaskan ulang.
     */
    public function isAssignable(): bool
    {
        return in_array($this, [self::Open, self::Assigned, self::InProgress, self::Reopened], true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }
}
