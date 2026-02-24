<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Enums;

enum ParticipantRole: string
{
    case Chairperson = 'chairperson';
    case Secretary = 'secretary';
    case Presenter = 'presenter';
    case VotingMember = 'voting_member';
    case NonVotingMember = 'non_voting_member';
    case Observer = 'observer';
    case Guest = 'guest';
    case Translator = 'translator';
    case TechSupport = 'tech_support';

    public function label(): string
    {
        return match ($this) {
            self::Chairperson => 'رئیس جلسه',
            self::Secretary => 'دبیر',
            self::Presenter => 'ارائه‌دهنده',
            self::VotingMember => 'عضو رأی‌دهنده',
            self::NonVotingMember => 'عضو بدون رأی',
            self::Observer => 'ناظر',
            self::Guest => 'مهمان',
            self::Translator => 'مترجم',
            self::TechSupport => 'پشتیبانی فنی',
        };
    }

    public function canVote(): bool
    {
        return in_array($this, [
            self::Chairperson,
            self::VotingMember,
        ], true);
    }

    public function isKey(): bool
    {
        return in_array($this, [self::Chairperson, self::Secretary], true);
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($c) => [$c->value => $c->label()])
            ->toArray();
    }
}
