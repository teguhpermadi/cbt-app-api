<?php

namespace App\Enums;

enum UserTypeEnum: string
{
    case ADMIN = 'admin';
    case TEACHER = 'teacher';
    case STUDENT = 'student';

    public function getLabel(): string
    {
        return match ($this) {
            self::ADMIN => 'Admin',
            self::TEACHER => 'Teacher',
            self::STUDENT => 'Student',
        };
    }
}
