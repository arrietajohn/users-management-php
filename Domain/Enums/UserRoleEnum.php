<?php

require_once __DIR__ . '/../Exceptions/InvalidUserRoleException.php';

class UserRoleEnum
{
    const ADMIN = 'ADMIN';
    const SUPERVISOR = 'SUPERVISOR';
    const OPERATOR = 'OPERADOR';
    const CONSULTATION = 'CONSULTA';

    public static function values()
    {
        return array(
            self::ADMIN,
            self::SUPERVISOR,
            self::OPERATOR,
            self::CONSULTATION
        );
    }

    public static function isValid($value)
    {
        return in_array($value, self::values(), true)
            || in_array($value, array('MEMBER', 'REVIEWER'), true);
    }

    public static function ensureIsValid($value)
    {
        if (!self::isValid($value)) {
            throw InvalidUserRoleException::becauseValueIsInvalid($value);
        }
    }
}

