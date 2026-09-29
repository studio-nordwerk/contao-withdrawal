<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Tests;

use Contao\CoreBundle\InsertTag\ResolvedInsertTag;
use Contao\CoreBundle\InsertTag\ResolvedParameters;
use Nordwerk\WithdrawalBundle\InsertTag\WithdrawalLinkInsertTag;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class InsertTagTest extends TestCase
{
    public function testEsiSubrequestKeepsInstallationBasePath(): void
    {
        $main = Request::create('https://example.test/shop/_fragment', server: ['SCRIPT_NAME' => '/shop/index.php', 'SCRIPT_FILENAME' => '/var/www/index.php']);
        $this->assertSame('/shop', $main->getBasePath());
        $stack = new RequestStack();
        $stack->push($main);
        $fragment = $main->duplicate(server: [...$main->server->all(), 'REQUEST_URI' => '/']);
        $fragment->setLocale('en');
        $stack->push($fragment);
        $tag = new WithdrawalLinkInsertTag($stack, '/withdrawal');
        $result = $tag(new ResolvedInsertTag('withdrawal_link', new ResolvedParameters([]), []));
        $this->assertStringContainsString('href="/shop/withdrawal"', $result->getValue());
        $this->assertStringContainsString('Withdraw contract', $result->getValue());
    }
}
