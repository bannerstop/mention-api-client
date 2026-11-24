<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use Bannerstop\MentionApi\Generator\Console\Application;
use PHPUnit\Framework\TestCase;

final class ConsoleTest extends TestCase
{
    /** @var string */
    private $work;
    /** @var string */
    private $stdout = '';
    /** @var string */
    private $stderr = '';

    protected function setUp(): void
    {
        $this->work = sys_get_temp_dir() . '/mention-api-client-cli-' . getmypid() . '-' . bin2hex(random_bytes(4));
        mkdir($this->work);
        copy(__DIR__ . '/Fixtures/composer-php74.json', $this->work . '/composer.json');
        copy(__DIR__ . '/Fixtures/openapi.json', $this->work . '/openapi.json');
    }

    protected function tearDown(): void
    {
        $this->remove($this->work);
    }

    private function remove(string $path): void
    {
        if (is_file($path)) {
            unlink($path);

            return;
        }
        foreach (glob($path . '/*') ?: [] as $child) {
            $this->remove($child);
        }
        if (is_dir($path)) {
            rmdir($path);
        }
    }

    /**
     * @param list<string>          $args
     * @param array<string, string> $env
     */
    private function cli(array $args, array $env = []): int
    {
        $this->stdout = '';
        $this->stderr = '';
        $application = new Application(function (string $text): void {
            $this->stdout .= $text;
        }, function (string $text): void {
            $this->stderr .= $text;
        });

        return $application->run($args, $this->work, $env);
    }

    /**
     * @return array<string, string>
     */
    private function filesIn(string $directory): array
    {
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $files[substr($file->getPathname(), \strlen($directory) + 1)] = (string) file_get_contents($file->getPathname());
        }
        ksort($files, SORT_STRING);

        return $files;
    }

    public function testGenerateWritesTheTypedClassesForThePhpOfTheApp(): void
    {
        $status = $this->cli(['generate', '--source=openapi.json', '--namespace=Acme\\Mention', '--dir=src/Mention']);

        self::assertSame(0, $status, $this->stderr);
        self::assertSame($this->filesIn(__DIR__ . '/Snapshots/php74'), $this->filesIn($this->work . '/src/Mention'));
        self::assertStringContainsString('14 files', $this->stdout);
    }

    public function testGenerateWritesClassesWithoutTypedPropertiesForAnAppOnPhp72(): void
    {
        copy(__DIR__ . '/Fixtures/composer-php72.json', $this->work . '/composer.json');

        $status = $this->cli(['generate', '--source=openapi.json', '--namespace=Acme\\Mention', '--dir=src/Mention']);

        self::assertSame(0, $status, $this->stderr);
        self::assertSame($this->filesIn(__DIR__ . '/Snapshots/php72'), $this->filesIn($this->work . '/src/Mention'));
    }

    public function testGenerateWritesReadonlyClassesForAnAppOnPhp82(): void
    {
        copy(__DIR__ . '/Fixtures/composer-php82.json', $this->work . '/composer.json');

        $status = $this->cli(['generate', '--source=openapi.json', '--namespace=Acme\\Mention', '--dir=src/Mention']);

        self::assertSame(0, $status, $this->stderr);
        self::assertSame($this->filesIn(__DIR__ . '/Snapshots/php82'), $this->filesIn($this->work . '/src/Mention'));
    }

    public function testThePhpOptionOverridesComposerJson(): void
    {
        unlink($this->work . '/composer.json');

        $status = $this->cli(['generate', '--source=openapi.json', '--namespace=Acme\\Mention', '--dir=out', '--php=8.2']);

        self::assertSame(0, $status, $this->stderr);
        self::assertSame($this->filesIn(__DIR__ . '/Snapshots/php82'), $this->filesIn($this->work . '/out'));
    }

    public function testThePhpOptionAcceptsPhp72(): void
    {
        $status = $this->cli(['generate', '--source=openapi.json', '--namespace=Acme\\Mention', '--dir=out', '--php=7.2']);

        self::assertSame(0, $status, $this->stderr);
        self::assertSame($this->filesIn(__DIR__ . '/Snapshots/php72'), $this->filesIn($this->work . '/out'));
    }

    public function testOptionsMayBeGivenWithASpaceInsteadOfAnEqualsSign(): void
    {
        $status = $this->cli(['generate', '--source', 'openapi.json', '--namespace', 'Acme\\Mention', '--dir', 'out', '--composer', 'composer.json']);

        self::assertSame(0, $status, $this->stderr);
        self::assertFileExists($this->work . '/out/Queries.php');
    }

    public function testCheckPassesWhenTheCommittedClassesAreCurrent(): void
    {
        $this->cli(['generate', '--source=openapi.json', '--namespace=Acme\\Mention', '--dir=src/Mention']);

        $status = $this->cli(['generate', '--check', '--source=openapi.json', '--namespace=Acme\\Mention', '--dir=src/Mention']);

        self::assertSame(0, $status, $this->stderr);
        self::assertStringContainsString('up to date', $this->stdout);
    }

    public function testCheckFailsAndNamesTheFilesWhenTheCatalogChanged(): void
    {
        $this->cli(['generate', '--source=openapi.json', '--namespace=Acme\\Mention', '--dir=src/Mention']);
        $document = json_decode((string) file_get_contents($this->work . '/openapi.json'), true);
        unset($document['paths']['/v2/queries/pingNothing']);
        $document['paths']['/v2/queries/getInvoiceTotals']['post']['responses']['200']['content']['application/json']['schema']['properties']['data']['items']['properties']['currency'] = ['x-type' => 'char'];
        $document['paths']['/v2/routines/archiveWidget'] = $document['paths']['/v2/routines/touchGadget'];
        file_put_contents($this->work . '/openapi.json', json_encode($document));
        $before = $this->filesIn($this->work . '/src/Mention');

        $status = $this->cli(['generate', '--check', '--source=openapi.json', '--namespace=Acme\\Mention', '--dir=src/Mention']);

        self::assertSame(1, $status);
        self::assertStringContainsString('Routine/ArchiveWidgetParameters.php', $this->stderr, 'missing');
        self::assertStringContainsString('Query/GetInvoiceTotalsRow.php', $this->stderr, 'changed');
        self::assertStringContainsString('Query/PingNothingRow.php', $this->stderr, 'stale');
        self::assertStringContainsString('generate', $this->stderr);
        self::assertSame($before, $this->filesIn($this->work . '/src/Mention'), 'check must not write');
    }

    public function testCheckFailsWhenNothingWasGeneratedYet(): void
    {
        $status = $this->cli(['generate', '--check', '--source=openapi.json', '--namespace=Acme\\Mention', '--dir=src/Mention']);

        self::assertSame(1, $status);
        self::assertStringContainsString('Queries.php', $this->stderr);
        self::assertFalse(is_dir($this->work . '/src'));
    }

    public function testTheDocumentIsFetchedWithTheClientOfTheApp(): void
    {
        $status = $this->cli(['generate', '--client=' . __DIR__ . '/Fixtures/client-bootstrap.php', '--namespace=Acme\\Mention', '--dir=out']);

        self::assertSame(0, $status, $this->stderr);
        $request = $GLOBALS['mention_api_client_test_http']->lastRequest();
        self::assertSame('https://mention-api.example.com/v2/openapi.json', (string) $request->getUri());
        self::assertSame($this->filesIn(__DIR__ . '/Snapshots/php74'), $this->filesIn($this->work . '/out'));
    }

    public function testAClientFileThatReturnsNoClientIsRefused(): void
    {
        file_put_contents($this->work . '/client.php', '<?php return 5;');

        $status = $this->cli(['generate', '--client=client.php', '--namespace=Acme\\Mention', '--dir=out']);

        self::assertSame(2, $status);
        self::assertStringContainsString('MentionApiClient', $this->stderr);
    }

    public function testWithoutASourceTheEnvironmentNamesWhatIsMissing(): void
    {
        $status = $this->cli(['generate', '--namespace=Acme\\Mention', '--dir=out']);

        self::assertSame(2, $status);
        self::assertStringContainsString('MENTION_API_URL', $this->stderr);
        self::assertStringContainsString('--source', $this->stderr);
    }

    public function testTheEnvironmentNeedsACredentialToo(): void
    {
        $status = $this->cli(['generate', '--namespace=Acme\\Mention', '--dir=out'], ['MENTION_API_URL' => 'https://mention-api.example.com']);

        self::assertSame(2, $status);
        self::assertStringContainsString('MENTION_API_KEY', $this->stderr);
        self::assertStringContainsString('MENTION_API_CLIENT_ID', $this->stderr);
    }

    public function testNamespaceAndDirectoryAreRequired(): void
    {
        $status = $this->cli(['generate', '--source=openapi.json']);

        self::assertSame(2, $status);
        self::assertStringContainsString('--namespace', $this->stderr);
        self::assertStringContainsString('--dir', $this->stderr);
        self::assertStringContainsString('Usage:', $this->stderr);
    }

    public function testWithoutComposerJsonThePhpTargetMustBeGiven(): void
    {
        unlink($this->work . '/composer.json');

        $status = $this->cli(['generate', '--source=openapi.json', '--namespace=Acme\\Mention', '--dir=out']);

        self::assertSame(2, $status);
        self::assertStringContainsString('--php', $this->stderr);
    }

    public function testASourceThatIsNoJsonIsRefused(): void
    {
        file_put_contents($this->work . '/broken.json', '{nope');

        $status = $this->cli(['generate', '--source=broken.json', '--namespace=Acme\\Mention', '--dir=out']);

        self::assertSame(2, $status);
        self::assertStringContainsString('broken.json', $this->stderr);
    }

    public function testASourceThatDoesNotExistIsRefused(): void
    {
        $status = $this->cli(['generate', '--source=missing.json', '--namespace=Acme\\Mention', '--dir=out']);

        self::assertSame(2, $status);
        self::assertStringContainsString('missing.json', $this->stderr);
    }

    public function testADocumentTheGeneratorCannotUseIsRefusedWithItsReason(): void
    {
        file_put_contents($this->work . '/other.json', '{"hello":"world"}');

        $status = $this->cli(['generate', '--source=other.json', '--namespace=Acme\\Mention', '--dir=out']);

        self::assertSame(2, $status);
        self::assertStringContainsString('not an OpenAPI document', $this->stderr);
        self::assertFalse(is_dir($this->work . '/out'));
    }

    public function testAnUnknownOptionAndAnUnknownCommandAreRefused(): void
    {
        self::assertSame(2, $this->cli(['generate', '--sauce=openapi.json']));
        self::assertStringContainsString('--sauce', $this->stderr);
        self::assertSame(2, $this->cli(['frobnicate']));
        self::assertStringContainsString('frobnicate', $this->stderr);
    }

    public function testHelpShowsTheUsage(): void
    {
        self::assertSame(0, $this->cli(['--help']));
        self::assertStringContainsString('generate', $this->stdout);
        self::assertStringContainsString('--check', $this->stdout);
        self::assertSame(2, $this->cli([]));
    }
}
