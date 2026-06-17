<?php

namespace App\Enums;

/** Typ komentarza w tickecie. */
enum CommentType: string
{
    case Public = 'public';
    case Internal = 'internal';
    case CloseRequest = 'close_request';
    case System = 'system';

    public function label(): string
    {
        return __('enums.comment_type.'.$this->value);
    }

    /**
     * Czy komentarz jest widoczny tylko dla personelu (support/admin).
     * System (wpisy osi czasu: zmiany statusu, utworzenie, przypisanie) są
     * widoczne dla wszystkich uczestników – tylko Internal jest ukryty przed klientem.
     */
    public function isStaffOnly(): bool
    {
        return $this === self::Internal;
    }
}
