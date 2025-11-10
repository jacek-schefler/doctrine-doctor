<?php

/*
 * This file is part of the Doctrine Doctor.
 * (c) 2025 Ahmed EBEN HASSINE
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace AhmedBhs\DoctrineDoctor\ValueObject;

/**
 * Issue severity levels.
 * PHP 8.1+ native enum for type safety and better IDE support.
 */
enum Severity: string
{
    case CRITICAL = 'critical';
    case WARNING = 'warning';
    case INFO = 'info';

    /**
     * Create from string value (for backward compatibility).
     */
    public static function fromString(string $value): self
    {
        return self::from($value);
    }

    /**
     * Get the string value (for backward compatibility).
     */
    public function getValue(): string
    {
        return $this->value;
    }

    public function isCritical(): bool
    {
        return self::CRITICAL === $this;
    }

    public function isWarning(): bool
    {
        return self::WARNING === $this;
    }

    public function isInfo(): bool
    {
        return self::INFO === $this;
    }

    /**
     * Named constructor for critical severity.
     */
    public static function critical(): self
    {
        return self::CRITICAL;
    }

    /**
     * Named constructor for warning severity.
     */
    public static function warning(): self
    {
        return self::WARNING;
    }

    /**
     * Named constructor for info severity.
     */
    public static function info(): self
    {
        return self::INFO;
    }

    /**
     * Get emoji representation for severity.
     *  Critical,  Warning,  Info
     */
    public function getEmoji(): string
    {
        return match ($this) {
            self::CRITICAL => '🔴',
            self::WARNING => '🟠',
            self::INFO => '🟡',
        };
    }
}
