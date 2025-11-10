<?php

/*
 * This file is part of the Doctrine Doctor.
 * (c) 2025 Ahmed EBEN HASSINE
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace AhmedBhs\DoctrineDoctor\Issue;

use AhmedBhs\DoctrineDoctor\ValueObject\IssueCategory;
use AhmedBhs\DoctrineDoctor\ValueObject\IssueType;

/**
 * Represents a code quality issue detected by Doctrine Doctor.
 * Code quality issues are anti-patterns, violations of best practices,
 * or architectural problems in entity code that don't necessarily cause
 * immediate bugs but lead to maintainability, testability, or performance issues.
 */
class CodeQualityIssue extends AbstractIssue
{
    public function __construct(array $data)
    {
        parent::__construct(array_merge([
            'type'        => IssueType::CODE_QUALITY,
            'title'       => 'Code Quality Issue',
            'description' => 'Code quality needs improvement.',
            'severity'    => $data['severity'] ?? 'warning',
        ], $data));
    }

    public function getType(): string
    {
        return 'Code Quality';
    }

    public function getCategory(): string
    {
        return IssueCategory::CODE_QUALITY->value;
    }
}
