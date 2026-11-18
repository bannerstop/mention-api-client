<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use Bannerstop\MentionApi\MentionApiConfig;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testATrailingSlashOfTheBaseUrlIsIgnored(): void
    {
        self::assertSame('https://mention-api.example.com', MentionApiConfig::forApiKey('https://mention-api.example.com/', 'key')->getBaseUrl());
    }

    public function testTheBaseUrlMustBeAnHttpUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        MentionApiConfig::forApiKey('mention-api.example.com', 'key');
    }

    public function testTheTokenUrlMustBeAnHttpUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        MentionApiConfig::forClientCredentials('https://mention-api.example.com', 'sso.example.com/token', 'app', 'secret');
    }

    public function testAnEmptyCredentialIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        MentionApiConfig::forApiKey('https://mention-api.example.com', '');
    }
}
