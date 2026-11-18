<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use Bannerstop\MentionApi\Generator\PhpTarget;
use PHPUnit\Framework\TestCase;

final class GeneratedQueryTest extends TestCase
{
    public function testTheParametersClassCollectsTheParametersOfAQueryByName(): void
    {
        $code = new GeneratedCode(GeneratedCode::fixtureDocument(), PhpTarget::php74());

        $class = $code->class('Query\\GetInvoiceTotalsParameters');
        $parameters = new $class(12, '2026-01-01', '2026-01-31');

        self::assertSame(['customerId' => 12, 'issuedFrom' => '2026-01-01', 'issuedTo' => '2026-01-31'], $parameters->toArray());
    }

    public function testListAndBooleanParametersKeepTheirTypes(): void
    {
        $code = new GeneratedCode(GeneratedCode::fixtureDocument(), PhpTarget::php74());

        $class = $code->class('Query\\GetWidgetsByIdsParameters');
        $parameters = new $class([3, 4], false);

        self::assertSame(['widgetIds' => [3, 4], 'includeRetired' => false], $parameters->toArray());
    }

    public function testAQueryWithoutParametersHasNoArguments(): void
    {
        $code = new GeneratedCode(GeneratedCode::fixtureDocument(), PhpTarget::php74());

        $class = $code->class('Query\\ListWidgetsParameters');

        self::assertSame([], (new $class())->toArray());
    }

    public function testTheParametersClassRefusesTheWrongType(): void
    {
        $code = new GeneratedCode(GeneratedCode::fixtureDocument(), PhpTarget::php74());
        $class = $code->class('Query\\GetInvoiceTotalsParameters');

        $this->expectException(\TypeError::class);

        new $class('12', '2026-01-01', '2026-01-31');
    }

    public function testAnOptionalParameterComesLastAndIsLeftOutWhenNull(): void
    {
        $document = GeneratedCode::fixtureDocument();
        $schema = &$document['paths']['/v2/queries/getInvoiceTotals']['post']['requestBody']['content']['application/json']['schema']['properties']['parameters'];
        $schema['required'] = ['issuedFrom', 'issuedTo'];
        unset($schema);
        $code = new GeneratedCode($document, PhpTarget::php74());
        $class = $code->class('Query\\GetInvoiceTotalsParameters');

        self::assertSame(['issuedFrom' => '2026-01-01', 'issuedTo' => '2026-01-31'], (new $class('2026-01-01', '2026-01-31'))->toArray());
        self::assertSame(['issuedFrom' => '2026-01-01', 'issuedTo' => '2026-01-31', 'customerId' => 5], (new $class('2026-01-01', '2026-01-31', 5))->toArray());
    }

    public function testARowTypesItsColumnsByTheirDatabaseType(): void
    {
        $code = new GeneratedCode(GeneratedCode::fixtureDocument(), PhpTarget::php74());
        $class = $code->class('Query\\GetInvoiceTotalsRow');

        $row = $class::fromArray(['invoiceId' => 7, 'total' => '12.50', 'issuedAt' => '2026-01-05 10:30:00', 'paid' => true, 'reference' => 'R-7', 'ratio' => 0.5]);

        self::assertSame(7, $row->invoiceId);
        self::assertSame('12.50', $row->total);
        self::assertSame('2026-01-05 10:30:00', $row->issuedAt);
        self::assertTrue($row->paid);
        self::assertSame('R-7', $row->reference);
        self::assertSame(0.5, $row->ratio);
    }

    public function testARowTakesNumbersThatCameAsTextAndNullColumns(): void
    {
        $code = new GeneratedCode(GeneratedCode::fixtureDocument(), PhpTarget::php74());
        $class = $code->class('Query\\FindGadgetsByTagRow');

        $row = $class::fromArray(['gadgetId' => '9007199254740993', 'name' => null, 'weight' => '1.5', 'retired' => 0]);

        self::assertSame(9007199254740993, $row->gadgetId);
        self::assertNull($row->name);
        self::assertSame(1.5, $row->weight);
        self::assertFalse($row->retired);
    }

    public function testAMissingColumnIsNullAndAnUnknownOneIsIgnored(): void
    {
        $code = new GeneratedCode(GeneratedCode::fixtureDocument(), PhpTarget::php74());
        $class = $code->class('Query\\FindGadgetsByTagRow');

        $row = $class::fromArray(['gadgetId' => 1, 'extra' => 'x']);

        self::assertSame(1, $row->gadgetId);
        self::assertNull($row->name);
        self::assertFalse(property_exists($row, 'extra'));
    }

    public function testAColumnOfAnUnknownTypeKeepsItsValueAsDelivered(): void
    {
        $code = new GeneratedCode(GeneratedCode::fixtureDocument(), PhpTarget::php74());
        $class = $code->class('Query\\ListWidgetsRow');

        $row = $class::fromArray(['widgetId' => 2, 'label' => 'a', 'shape' => ['kind' => 'point'], 'note' => 5]);

        self::assertSame(['kind' => 'point'], $row->shape);
        self::assertSame(5, $row->note);
    }
}
