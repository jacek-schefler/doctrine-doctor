<?php

/*
 * This file is part of the Doctrine Doctor.
 * (c) 2025 Ahmed EBEN HASSINE
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace AhmedBhs\DoctrineDoctor\Tests\Analyzer;

use AhmedBhs\DoctrineDoctor\Analyzer\IneffectiveLikeAnalyzer;
use AhmedBhs\DoctrineDoctor\Tests\Integration\PlatformAnalyzerTestHelper;
use AhmedBhs\DoctrineDoctor\Tests\Support\QueryDataBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test for IneffectiveLikeAnalyzer.
 *
 * This analyzer detects LIKE patterns with leading wildcards that prevent index usage.
 * Leading wildcards force full table scans because the database cannot use indexes.
 */
final class IneffectiveLikeAnalyzerTest extends TestCase
{
    private IneffectiveLikeAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new IneffectiveLikeAnalyzer(
            PlatformAnalyzerTestHelper::createSuggestionFactory(),
        );
    }

    #[Test]
    public function it_returns_empty_collection_when_no_queries(): void
    {
        // Arrange: No queries at all
        $queries = QueryDataBuilder::create()->build();

        // Act
        $issues = $this->analyzer->analyze($queries);

        // Assert
        self::assertCount(0, $issues);
    }

    #[Test]
    public function it_returns_empty_collection_for_safe_like_patterns(): void
    {
        // Arrange: LIKE patterns without leading wildcards (safe, can use index)
        $queries = QueryDataBuilder::create()
            ->addQuery("SELECT * FROM users WHERE name LIKE 'John%'")  // Trailing only - OK
            ->addQuery("SELECT * FROM users WHERE name LIKE 'Smith'")  // No wildcard - OK
            ->addQuery("SELECT * FROM users WHERE email = 'test@example.com'") // Not LIKE
            ->build();

        // Act
        $issues = $this->analyzer->analyze($queries);

        // Assert
        self::assertCount(0, $issues);
    }

    #[Test]
    public function it_detects_like_with_leading_and_trailing_wildcard(): void
    {
        // Arrange: Contains search pattern (most problematic)
        $queries = QueryDataBuilder::create()
            ->addQuery("SELECT * FROM users WHERE name LIKE '%John%'", 100.0)
            ->build();

        // Act
        $issues = $this->analyzer->analyze($queries);

        // Assert
        self::assertCount(1, $issues);
        $issue = $issues->toArray()[0];
        self::assertEquals('Ineffective LIKE Pattern Detected', $issue->getTitle());
        self::assertStringContainsString('leading wildcard', $issue->getDescription());
        self::assertStringContainsString('%John%', $issue->getDescription());
        self::assertStringContainsString('contains search', $issue->getDescription());
        self::assertEquals('performance', $issue->getCategory());
    }

    #[Test]
    public function it_detects_like_with_leading_wildcard_only(): void
    {
        // Arrange: Ends-with search pattern
        $queries = QueryDataBuilder::create()
            ->addQuery("SELECT * FROM users WHERE email LIKE '%@example.com'", 80.0)
            ->build();

        // Act
        $issues = $this->analyzer->analyze($queries);

        // Assert
        self::assertCount(1, $issues);
        $issue = $issues->toArray()[0];
        self::assertStringContainsString('%@example.com', $issue->getDescription());
        self::assertStringContainsString('ends-with search', $issue->getDescription());
    }

    #[Test]
    public function it_detects_multiple_like_patterns_in_single_query(): void
    {
        // Arrange: Query with multiple problematic LIKE patterns
        $queries = QueryDataBuilder::create()
            ->addQuery(
                "SELECT * FROM users WHERE name LIKE '%John%' OR email LIKE '%@example.com'",
                200.0,
            )
            ->build();

        // Act
        $issues = $this->analyzer->analyze($queries);

        // Assert: Should detect both patterns
        self::assertCount(2, $issues);
    }

    #[Test]
    public function it_deduplicates_same_pattern_across_queries(): void
    {
        // Arrange: Multiple queries using same LIKE pattern
        $queries = QueryDataBuilder::create()
            ->addQuery("SELECT * FROM users WHERE name LIKE '%John%' AND status = 'active'", 100.0)
            ->addQuery("SELECT * FROM users WHERE name LIKE '%John%' AND city = 'Paris'", 100.0)
            ->addQuery("SELECT * FROM posts WHERE title LIKE '%John%'", 100.0)
            ->build();

        // Act
        $issues = $this->analyzer->analyze($queries);

        // Assert: Should deduplicate based on pattern (MD5 hash of '%John%')
        self::assertCount(1, $issues);
    }

    #[Test]
    public function it_detects_different_patterns_as_separate_issues(): void
    {
        // Arrange: Different LIKE patterns
        $queries = QueryDataBuilder::create()
            ->addQuery("SELECT * FROM users WHERE name LIKE '%John%'", 100.0)
            ->addQuery("SELECT * FROM users WHERE email LIKE '%@example.com'", 100.0)
            ->build();

        // Act
        $issues = $this->analyzer->analyze($queries);

        // Assert: Two different patterns = 2 issues
        self::assertCount(2, $issues);
    }

    #[Test]
    public function it_sets_severity_to_critical_for_very_slow_queries(): void
    {
        // Arrange: Query taking > 200ms
        $queries = QueryDataBuilder::create()
            ->addQuery("SELECT * FROM users WHERE name LIKE '%search%'", 250.0)
            ->build();

        // Act
        $issues = $this->analyzer->analyze($queries);

        // Assert
        self::assertCount(1, $issues);
        $issue = $issues->toArray()[0];
        self::assertEquals('critical', $issue->getSeverity()->value);
        self::assertStringContainsString('250.00ms', $issue->getDescription());
    }

    #[Test]
    public function it_sets_severity_to_warning_for_moderate_queries(): void
    {
        // Arrange: Query taking 50-200ms
        $queries = QueryDataBuilder::create()
            ->addQuery("SELECT * FROM users WHERE name LIKE '%search%'", 100.0)
            ->build();

        // Act
        $issues = $this->analyzer->analyze($queries);

        // Assert
        self::assertCount(1, $issues);
        $issue = $issues->toArray()[0];
        self::assertEquals('warning', $issue->getSeverity()->value);
    }

    #[Test]
    public function it_sets_severity_to_info_for_fast_queries(): void
    {
        // Arrange: Query taking < 50ms
        $queries = QueryDataBuilder::create()
            ->addQuery("SELECT * FROM users WHERE name LIKE '%search%'", 30.0)
            ->build();

        // Act
        $issues = $this->analyzer->analyze($queries);

        // Assert
        self::assertCount(1, $issues);
        $issue = $issues->toArray()[0];
        self::assertEquals('info', $issue->getSeverity()->value);
    }

    #[Test]
    public function it_handles_case_insensitive_like_keyword(): void
    {
        // Arrange: lowercase 'like'
        $queries = QueryDataBuilder::create()
            ->addQuery("SELECT * FROM users WHERE name like '%John%'", 100.0)
            ->build();

        // Act
        $issues = $this->analyzer->analyze($queries);

        // Assert
        self::assertCount(1, $issues);
    }

    #[Test]
    public function it_handles_double_quotes_in_like_pattern(): void
    {
        // Arrange: Pattern with double quotes
        $queries = QueryDataBuilder::create()
            ->addQuery('SELECT * FROM users WHERE name LIKE "%John%"', 100.0)
            ->build();

        // Act
        $issues = $this->analyzer->analyze($queries);

        // Assert
        self::assertCount(1, $issues);
        $issue = $issues->toArray()[0];
        self::assertStringContainsString('%John%', $issue->getDescription());
    }

    #[Test]
    public function it_handles_backtrace_information(): void
    {
        // Arrange: Query with backtrace
        $backtrace = [
            ['file' => 'UserRepository.php', 'line' => 42, 'class' => 'UserRepository', 'function' => 'search'],
        ];
        $queries = QueryDataBuilder::create()
            ->addQueryWithBacktrace(
                "SELECT * FROM users WHERE name LIKE '%search%'",
                $backtrace,
                100.0,
            )
            ->build();

        // Act
        $issues = $this->analyzer->analyze($queries);

        // Assert
        self::assertCount(1, $issues);
        $issue = $issues->toArray()[0];
        self::assertNotNull($issue->getBacktrace());
        self::assertEquals($backtrace, $issue->getBacktrace());
    }

    #[Test]
    public function it_skips_queries_without_like(): void
    {
        // Arrange: Queries without LIKE patterns
        $queries = QueryDataBuilder::create()
            ->addQuery("SELECT * FROM users WHERE status = 'active'", 100.0)
            ->addQuery("SELECT * FROM users WHERE name LIKE '%John%'", 100.0)
            ->build();

        // Act
        $issues = $this->analyzer->analyze($queries);

        // Assert: Should only detect the query with LIKE
        self::assertCount(1, $issues);
    }

    #[Test]
    public function it_provides_suggestion_with_context(): void
    {
        // Arrange
        $queries = QueryDataBuilder::create()
            ->addQuery("SELECT * FROM users WHERE name LIKE '%John%'", 100.0)
            ->build();

        // Act
        $issues = $this->analyzer->analyze($queries);

        // Assert
        self::assertCount(1, $issues);
        $issue = $issues->toArray()[0];
        $suggestion = $issue->getSuggestion();
        self::assertNotNull($suggestion);
        /** @var \AhmedBhs\DoctrineDoctor\Suggestion\ModernSuggestion $suggestion */

        // Verify suggestion has the expected context
        self::assertEquals('ineffective_like', $suggestion->getTemplateName());
        $context = $suggestion->getContext();
        self::assertArrayHasKey('pattern', $context);
        self::assertArrayHasKey('like_type', $context);
        self::assertEquals('%John%', $context['pattern']);
        self::assertEquals('contains search', $context['like_type']);

        // Verify metadata
        $metadata = $suggestion->getMetadata();
        self::assertEquals('performance', $metadata->type->value);
        self::assertEquals('warning', $metadata->severity->value);
    }

    #[Test]
    public function it_correctly_identifies_like_type_for_contains(): void
    {
        // Arrange: %pattern%
        $queries = QueryDataBuilder::create()
            ->addQuery("SELECT * FROM users WHERE name LIKE '%John%'", 100.0)
            ->build();

        // Act
        $issues = $this->analyzer->analyze($queries);

        // Assert
        $issue = $issues->toArray()[0];
        self::assertStringContainsString('contains search', $issue->getDescription());
    }

    #[Test]
    public function it_correctly_identifies_like_type_for_ends_with(): void
    {
        // Arrange: %pattern (no trailing wildcard)
        $queries = QueryDataBuilder::create()
            ->addQuery("SELECT * FROM users WHERE email LIKE '%@domain.com'", 100.0)
            ->build();

        // Act
        $issues = $this->analyzer->analyze($queries);

        // Assert
        $issue = $issues->toArray()[0];
        self::assertStringContainsString('ends-with search', $issue->getDescription());
    }

    #[Test]
    public function it_handles_complex_queries_with_multiple_conditions(): void
    {
        // Arrange: Complex query with multiple WHERE conditions
        $queries = QueryDataBuilder::create()
            ->addQuery(
                "SELECT u.* FROM users u " .
                "LEFT JOIN posts p ON u.id = p.user_id " .
                "WHERE u.name LIKE '%John%' AND u.status = 'active' " .
                "ORDER BY u.created_at DESC",
                150.0,
            )
            ->build();

        // Act
        $issues = $this->analyzer->analyze($queries);

        // Assert
        self::assertCount(1, $issues);
        $issue = $issues->toArray()[0];
        self::assertStringContainsString('%John%', $issue->getDescription());
    }

    #[Test]
    public function it_detects_issue_in_having_clause(): void
    {
        // Arrange: LIKE in HAVING clause
        $queries = QueryDataBuilder::create()
            ->addQuery(
                "SELECT name, COUNT(*) as cnt FROM users " .
                "GROUP BY name HAVING name LIKE '%Smith%'",
                100.0,
            )
            ->build();

        // Act
        $issues = $this->analyzer->analyze($queries);

        // Assert
        self::assertCount(1, $issues);
        $issue = $issues->toArray()[0];
        self::assertStringContainsString('%Smith%', $issue->getDescription());
    }

    #[Test]
    public function it_returns_correct_analyzer_metadata(): void
    {
        // Act
        $name = $this->analyzer->getName();
        $description = $this->analyzer->getDescription();

        // Assert
        self::assertEquals('Ineffective LIKE Pattern Analyzer', $name);
        self::assertStringContainsString('leading wildcards', $description);
        self::assertStringContainsString('index usage', $description);
    }
}
