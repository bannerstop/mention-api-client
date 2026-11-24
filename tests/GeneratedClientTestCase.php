<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use Bannerstop\MentionApi\Exception\InvalidParametersException;
use Bannerstop\MentionApi\Generator\PhpTarget;
use Bannerstop\MentionApi\Tests\Fixtures\Answers;

abstract class GeneratedClientTestCase extends ClientTestCase
{
    abstract protected function target(): PhpTarget;

    public function testATypedQueryMethodRunsTheQueryAndReturnsTypedRows(): void
    {
        $code = new GeneratedCode(GeneratedCode::fixtureDocument(), $this->target());
        $queriesClass = $code->class('Queries');
        $parametersClass = $code->class('Query\\GetInvoiceTotalsParameters');
        $rowClass = $code->class('Query\\GetInvoiceTotalsRow');
        $this->http->enqueue(Answers::rows([
            ['invoiceId' => 7, 'total' => '12.50', 'issuedAt' => '2026-01-05 10:30:00', 'paid' => true, 'reference' => 'R-7', 'ratio' => 0.5],
            ['invoiceId' => 8, 'total' => '3.00', 'issuedAt' => '2026-01-06 08:00:00', 'paid' => false, 'reference' => null, 'ratio' => 1.0],
        ], 'getInvoiceTotals', 'req_typed'));

        $result = (new $queriesClass($this->client()))->getInvoiceTotals(new $parametersClass(12, '2026-01-01', '2026-01-31'), 'example_db');

        $request = $this->http->lastRequest();
        self::assertSame('https://mention-api.example.com/v2/queries/getInvoiceTotals', (string) $request->getUri());
        self::assertSame(['parameters' => ['customerId' => 12, 'issuedFrom' => '2026-01-01', 'issuedTo' => '2026-01-31'], 'database' => 'example_db'], $this->http->lastJsonBody());
        self::assertCount(2, $result);
        self::assertContainsOnlyInstancesOf($rowClass, $result->getRows());
        self::assertSame(7, $result->getRows()[0]->invoiceId);
        self::assertNull($result->getRows()[1]->reference);
        self::assertSame([7, 8], array_map(static function ($row) {
            return $row->invoiceId;
        }, iterator_to_array($result)));
        self::assertSame('req_typed', $result->getRequestId());
        self::assertSame('getInvoiceTotals', $result->getQuery());
        self::assertSame(3, $result->getVersion());
        self::assertSame(12, $result->getDurationMs());
        self::assertFalse($result->isDeprecated());
    }

    public function testATypedQueryMethodReportsADeprecatedQuery(): void
    {
        $code = new GeneratedCode(GeneratedCode::fixtureDocument(), $this->target());
        $queriesClass = $code->class('Queries');
        $parametersClass = $code->class('Query\\ListWidgetsParameters');
        $this->http->enqueue(Answers::rows([], 'listWidgets', 'req_old', ['Deprecation' => '@1767225600']));

        $result = (new $queriesClass($this->client()))->listWidgets(new $parametersClass());

        self::assertTrue($result->isDeprecated());
        self::assertSame('2026-01-01', $result->getDeprecatedSince()->format('Y-m-d'));
        self::assertSame([], $this->http->lastJsonBody()['parameters']);
    }

    public function testATypedQueryMethodThrowsWhatTheGenericClientThrows(): void
    {
        $code = new GeneratedCode(GeneratedCode::fixtureDocument(), $this->target());
        $queriesClass = $code->class('Queries');
        $parametersClass = $code->class('Query\\GetWidgetsByIdsParameters');
        $this->http->enqueue(Answers::problem(422, 'invalid-parameters', 'Invalid parameters', 'Fix them.', 'req_bad', ['errors' => ['widgetIds' => 'Expected a list.']]));

        try {
            (new $queriesClass($this->client()))->getWidgetsByIds(new $parametersClass([1], false));
            self::fail('Expected an exception.');
        } catch (InvalidParametersException $e) {
            self::assertSame('req_bad', $e->getRequestId());
        }
    }

    public function testATypedRoutineMethodRunsTheRoutineWithoutRetries(): void
    {
        $code = new GeneratedCode(GeneratedCode::fixtureDocument(), $this->target());
        $routinesClass = $code->class('Routines');
        $parametersClass = $code->class('Routine\\SetWidgetLabelParameters');
        $this->http->enqueue(Answers::json(
            ['data' => ['action' => 'updated'], 'meta' => ['routine' => 'setWidgetLabel', 'rows' => 1, 'duration_ms' => 4, 'request_id' => 'req_r']],
            200,
            ['X-Request-Id' => 'req_r']
        ));

        $result = (new $routinesClass($this->client()))->setWidgetLabel(new $parametersClass(4, 'Blue', '0192a3b4-1111-7222-8333-444455556666'));

        self::assertSame('https://mention-api.example.com/v2/routines/setWidgetLabel', (string) $this->http->lastRequest()->getUri());
        self::assertSame(['parameters' => ['widgetId' => 4, 'label' => 'Blue', 'spark' => '0192a3b4-1111-7222-8333-444455556666']], $this->http->lastJsonBody());
        self::assertSame('updated', $result->getAction());
        self::assertSame('req_r', $result->getRequestId());
    }

    public function testATypedRoutineMethodIsNeverRetried(): void
    {
        $code = new GeneratedCode(GeneratedCode::fixtureDocument(), $this->target());
        $routinesClass = $code->class('Routines');
        $parametersClass = $code->class('Routine\\TouchGadgetParameters');
        $this->http->enqueue(Answers::problem(503, 'mention-unavailable'));

        try {
            (new $routinesClass($this->client()))->touchGadget(new $parametersClass(1));
            self::fail('Expected an exception.');
        } catch (\Bannerstop\MentionApi\Exception\UnavailableException $e) {
            self::assertCount(1, $this->http->requests);
        }
    }

    public function testAQueryWithoutRequiredParametersCanBeCalledWithoutThem(): void
    {
        $code = new GeneratedCode(GeneratedCode::fixtureDocument(), $this->target());
        $queriesClass = $code->class('Queries');
        $this->http->enqueue(Answers::rows([['widgetId' => 1, 'label' => 'a']], 'listWidgets'));

        $result = (new $queriesClass($this->client()))->listWidgets();

        self::assertSame('{"parameters":{}}', (string) $this->http->lastRequest()->getBody());
        self::assertSame(1, $result->getRows()[0]->widgetId);
    }
}
