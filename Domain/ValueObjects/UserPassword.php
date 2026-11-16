<?php

require_once __DIR__ . '/../Exceptions/InvalidUserPasswordException.php';

class UserPassword
{
    private const MAX_BCRYPT_PASSWORD_BYTES = 72;

    private $value;

    public function __construct($value)
    {
        $normalizedValue = trim((string) $value);

        if ($normalizedValue === '') {
            throw InvalidUserPasswordException::becauseValueIsEmpty();
        }

        if (strlen($normalizedValue) < 8) {
            throw InvalidUserPasswordException::becauseLengthIsTooShort(8);
        }

        $this->value = $normalizedValue;
    }

    /**
     * Creates a UserPassword from plain text, validating and hashing it with bcrypt.
     * Use this when a user creates or changes their password.
     */
    public static function fromPlainText(string $raw): self
    {
        $normalizedValue = trim($raw);

        if ($normalizedValue === '') {
            throw InvalidUserPasswordException::becauseValueIsEmpty();
        }

        if (strlen($normalizedValue) < 8) {
            throw InvalidUserPasswordException::becauseLengthIsTooShort(8);
        }

        if (strlen($normalizedValue) > self::MAX_BCRYPT_PASSWORD_BYTES) {
            throw InvalidUserPasswordException::becauseLengthIsTooLong(self::MAX_BCRYPT_PASSWORD_BYTES);
        }

        // BCrypt hash is 60+ characters; passes the constructor min-8 check.
        $hash = password_hash($normalizedValue, PASSWORD_BCRYPT);

        return new self($hash);
    }

    /**
     * Creates a UserPassword from an already-hashed value loaded from storage.
     * No validation of min length is needed (hash is always > 8 chars).
     */
    public static function fromHash(string $hash): self
    {
        return new self($hash);
    }

    /**
     * Verifies a plain-text password against the stored bcrypt hash.
     */
    public function verifyPlain(string $plain): bool
    {
        return password_verify(trim($plain), $this->value);
    }

    public function value()
    {
        return $this->value;
    }

    public function equals(UserPassword $other)
    {
        return $this->value === $other->value();
    }

    public function __toString()
    {
        return $this->value;
    }
}

