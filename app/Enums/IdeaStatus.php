<?php

namespace App\Enums;

enum IdeaStatus: string
{
    case Draft = 'draft';
    case Testing = 'testing';
    case Validated = 'validated';
    case Rejected = 'rejected';
    case Building = 'building';
    case Launched = 'launched';

    public function label(): string
    {
        return config("statuses.idea.{$this->value}", $this->value);
    }

    /**
     * وضعیت‌هایی که از وضعیت فعلی می‌توان به آن‌ها رفت.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Testing, self::Rejected],
            self::Testing => [self::Validated, self::Rejected, self::Draft],
            self::Validated => [self::Building, self::Testing, self::Rejected],
            self::Rejected => [self::Draft],
            self::Building => [self::Launched, self::Validated],
            self::Launched => [self::Building],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
