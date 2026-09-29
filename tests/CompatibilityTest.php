<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Tests;

use Composer\InstalledVersions;
use Composer\Semver\Semver;
use PHPUnit\Framework\TestCase;

final class CompatibilityTest extends TestCase
{
    public function testManifestSupportsTheTestedRuntimeWithoutAdvertisingUntestedContao53(): void
    {
        $manifest = json_decode((string) file_get_contents(__DIR__.'/../composer.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertFalse(Semver::satisfies('5.3.0', $manifest['require']['contao/core-bundle']));
        $this->assertTrue(Semver::satisfies((string) InstalledVersions::getVersion('contao/core-bundle'), $manifest['require']['contao/core-bundle']));
        $this->assertTrue(Semver::satisfies(PHP_VERSION, $manifest['require']['php']));
    }
}
