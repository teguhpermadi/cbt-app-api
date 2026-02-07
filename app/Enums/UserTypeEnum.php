<?php

namespace App\Enums;

enum UserTypeEnum
{
    const ADMIN = 'admin';
    const TEACHER = 'teacher';
    const STUDENT = 'student';

    public function getLabel(): string
    {
        return match ($this) {
            self::ADMIN => 'Admin',
            self::TEACHER => 'Teacher',
            self::STUDENT => 'Student',
        };
    }
}
