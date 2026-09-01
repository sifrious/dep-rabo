<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Tests\Docs;

use PHPUnit\Framework\TestCase;

/**
 * The identifiers the documentation cites, checked against the things they name.
 *
 * `docs/project-memory.json` is read by nothing — not `src/`, not `tests/`, not CI — and it had
 * drifted exactly as far as that implies: it carried a D-016 with no entry in the decision
 * register, was missing four other decisions and half the assumptions, and still posed Q-006 in
 * its unanswered wording long after `composer.json` had answered it.
 *
 * `docs/glossary.md` binds sixteen terms to PHP symbols, and nothing checked that a rename had not
 * left one of them pointing at a class that no longer exists. So does
 * `docs/package-boundary-validation.md`, which cites two test methods as the evidence for a
 * "Passed" verdict.
 *
 * None of these had gone wrong yet. That is the point of writing the test now.
 */
final class DocumentedIdentifiersTest extends TestCase
{
    public function test_project_memory_records_every_decision_assumption_and_question(): void
    {
        $memory = self::memory();

        foreach ([
            ['decisions', 'docs/decisions.md', 'D'],
            ['assumptions', 'docs/assumptions.md', 'A'],
            ['open_questions', 'docs/open-questions.md', 'Q'],
        ] as [$key, $document, $prefix]) {
            $documented = self::headings($document, $prefix);
            $recorded = array_column($memory[$key], 'id');
            sort($recorded);

            self::assertSame(
                $documented,
                $recorded,
                "docs/project-memory.json '{$key}' does not match the {$prefix}- headings in {$document}.",
            );
        }
    }

    public function test_every_class_the_glossary_names_exists(): void
    {
        $symbols = self::symbols(self::read('docs/glossary.md'));
        self::assertGreaterThan(10, count($symbols), 'The glossary stopped naming the code it defines.');

        foreach ($symbols as $symbol) {
            [$class] = explode('::', $symbol, 2);
            self::assertFileExists(
                self::root().'/src/'.str_replace('\\', '/', $class).'.php',
                "docs/glossary.md names {$symbol}, which no longer exists.",
            );
        }
    }

    public function test_every_test_the_boundary_table_cites_as_evidence_exists(): void
    {
        $document = self::read('docs/package-boundary-validation.md');
        preg_match_all('/`(test_[a-z0-9_]+)`/', $document, $matches);
        self::assertNotEmpty($matches[1], 'The boundary table cites no test as evidence for its verdicts.');

        $suite = '';
        foreach (self::phpFiles(self::root().'/tests') as $file) {
            $suite .= (string) file_get_contents($file);
        }

        foreach (array_unique($matches[1]) as $method) {
            self::assertStringContainsString(
                'function '.$method.'(',
                $suite,
                "docs/package-boundary-validation.md cites {$method} as evidence, and no test has that name.",
            );
        }
    }

    /** @return list<string> */
    private static function symbols(string $document): array
    {
        preg_match_all('/`([A-Z][A-Za-z]+(?:\\\\[A-Za-z]+)+(?:::[A-Za-z]+)?)`/', $document, $matches);

        return array_values(array_unique($matches[1]));
    }

    /** @return list<string> */
    private static function headings(string $document, string $prefix): array
    {
        preg_match_all('/^## ('.$prefix.'-\d+)\b/m', self::read($document), $matches);
        $ids = array_values(array_unique($matches[1]));
        sort($ids);

        return $ids;
    }

    /** @return array<string,list<array<string,mixed>>> */
    private static function memory(): array
    {
        return json_decode(self::read('docs/project-memory.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return list<string> */
    private static function phpFiles(string $directory): array
    {
        $files = [];
        foreach ((array) scandir($directory) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory.'/'.$entry;
            if (is_dir($path)) {
                $files = [...$files, ...self::phpFiles($path)];

                continue;
            }
            if (str_ends_with((string) $entry, '.php')) {
                $files[] = $path;
            }
        }

        return $files;
    }

    private static function read(string $relative): string
    {
        return (string) file_get_contents(self::root().'/'.$relative);
    }

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
