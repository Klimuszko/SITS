<?php

namespace App\Enums;

/** Typ komentarza w tickecie. */
enum CommentType: string
{
    case Public = 'public';
    case Internal = 'internal';
    case CloseRequest = 'close_request';

    public function label(): string
    {
        return __('enums.comment_type.'.$this->value);
    }

    /** Czy komentarz jest widoczny tylko dla personelu (support/admin). */
    public function isStaffOnly(): bool
    {
        return $this === self::Internal;
    }
}
