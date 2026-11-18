<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use Bannerstop\MentionApi\Generator\GeneratorException;
use Bannerstop\MentionApi\Generator\PhpTarget;
use Bannerstop\MentionApi\Generator\WrapperGenerator;
use PHPUnit\Framework\TestCase;

final class WrapperGeneratorTest extends TestCase
{
    /**
     * @return array<string, string>
     */
    private function generate(array $document, string $namespace = 'Acme\\Mention', ?PhpTarget $target = null): array
    {
        return (new WrapperGenerator())->generate($document, $namespace, $target ?? PhpTarget::php74());
    }

    public function testItWritesParametersAndRowPerQueryAndParametersPerRoutine(): void
    {
        $files = $this->generate(GeneratedCode::fixtureDocument());

        self::assertSame([
            'Queries.php',
            'Query/FindGadgetsByTagParameters.php',
            'Query/FindGadgetsByTagRow.php',
            'Query/GetInvoiceTotalsParameters.php',
            'Query/GetInvoiceTotalsRow.php',
            'Query/GetWidgetsByIdsParameters.php',
            'Query/GetWidgetsByIdsRow.php',
            'Query/ListWidgetsParameters.php',
            'Query/ListWidgetsRow.php',
            'Query/PingNothingParameters.php',
            'Query/PingNothingRow.php',
            'Routine/SetWidgetLabelParameters.php',
            'Routine/TouchGadgetParameters.php',
            'Routines.php',
        ], array_keys($files));
    }

    public function testTheOutputDoesNotDependOnTheOrderOfThePathsInTheDocument(): void
    {
        $document = GeneratedCode::fixtureDocument();
        $reversed = $document;
        $reversed['paths'] = array_reverse($document['paths'], true);

        self::assertSame($this->generate($document), $this->generate($reversed));
    }

    public function testTheSameDocumentGivesTheSameFilesEveryTime(): void
    {
        self::assertSame($this->generate(GeneratedCode::fixtureDocument()), $this->generate(GeneratedCode::fixtureDocument()));
    }

    public function testEveryFileCarriesTheMarkerAndTheNamespace(): void
    {
        foreach ($this->generate(GeneratedCode::fixtureDocument(), '\\App\\Mention\\') as $path => $code) {
            self::assertStringContainsString('// ' . WrapperGenerator::MARKER, $code, $path);
            self::assertMatchesRegularExpression('/^namespace App\\\\Mention(\\\\Query|\\\\Routine)?;$/m', $code, $path);
        }
    }

    public function testADocumentWithoutQueriesStillGivesEmptyQueriesAndRoutines(): void
    {
        $files = $this->generate(['openapi' => '3.1.0', 'paths' => ['/v2/queries' => []]]);

        self::assertSame(['Queries.php', 'Routines.php'], array_keys($files));
    }

    public function testADescriptionCannotEndTheCommentItIsIn(): void
    {
        $document = GeneratedCode::fixtureDocument();
        $document['paths']['/v2/queries/listWidgets']['post']['summary'] = "Evil */ echo 'x';\nsecond line";

        $code = $this->generate($document)['Queries.php'];

        self::assertStringContainsString("Evil * / echo 'x'; second line", $code);
        self::assertStringNotContainsString("*/ echo", $code);
    }

    public function testADocumentThatIsNotOpenApiIsRefused(): void
    {
        $this->expectException(GeneratorException::class);

        $this->generate(['data' => []]);
    }

    public function testANamespaceThatIsNotOneIsRefused(): void
    {
        $this->expectException(GeneratorException::class);

        $this->generate(GeneratedCode::fixtureDocument(), 'App Mention');
    }

    public function testAParameterTypeTheGeneratorDoesNotKnowIsRefusedByName(): void
    {
        $document = GeneratedCode::fixtureDocument();
        $document['paths']['/v2/queries/listWidgets']['post']['requestBody']['content']['application/json']['schema']['properties']['parameters']['properties'] = ['when' => ['type' => 'object']];

        try {
            $this->generate($document);
            self::fail('Expected an exception.');
        } catch (GeneratorException $e) {
            self::assertStringContainsString('"when"', $e->getMessage());
            self::assertStringContainsString('"listWidgets"', $e->getMessage());
        }
    }

    public function testAColumnThatCannotBeAPropertyIsRefusedByName(): void
    {
        $document = GeneratedCode::fixtureDocument();
        $document['paths']['/v2/queries/listWidgets']['post']['responses']['200']['content']['application/json']['schema']['properties']['data']['items']['properties']['Net Total'] = ['x-type' => 'decimal'];

        try {
            $this->generate($document);
            self::fail('Expected an exception.');
        } catch (GeneratorException $e) {
            self::assertStringContainsString('"Net Total"', $e->getMessage());
        }
    }

    public function testTwoNamesThatDifferOnlyInCaseAreRefused(): void
    {
        $document = GeneratedCode::fixtureDocument();
        $document['paths']['/v2/queries/ListWidgets'] = $document['paths']['/v2/queries/listWidgets'];

        $this->expectException(GeneratorException::class);

        $this->generate($document);
    }
}
