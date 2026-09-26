<?php

namespace App\Enums;

enum ChangedBy: string
{
    case Admin = 'admin';
    case Customer = 'customer';
    case System = 'system';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
