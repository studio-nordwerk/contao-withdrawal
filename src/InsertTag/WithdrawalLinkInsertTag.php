<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\InsertTag;

use Contao\CoreBundle\DependencyInjection\Attribute\AsInsertTag;
use Contao\CoreBundle\InsertTag\InsertTagResult;
use Contao\CoreBundle\InsertTag\OutputType;
use Contao\CoreBundle\InsertTag\ResolvedInsertTag;
use Contao\CoreBundle\InsertTag\Resolver\InsertTagResolverNestedResolvedInterface;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsInsertTag('withdrawal_link')]
final readonly class WithdrawalLinkInsertTag implements InsertTagResolverNestedResolvedInterface
{
    public function __construct(
        private RequestStack $requestStack,
        private string $path,
    ) {
    }

    public function __invoke(ResolvedInsertTag $insertTag): InsertTagResult
    {
        $request = $this->requestStack->getCurrentRequest();
        $label = str_starts_with((string) $request?->getLocale(), 'en') ? 'Withdraw contract' : 'Vertrag widerrufen';
        $href = rtrim((string) $request?->getBasePath(), '/').'/'.ltrim($this->path, '/');

        return new InsertTagResult(\sprintf('<a class="withdrawal-link" href="%s">%s</a>', htmlspecialchars($href, ENT_QUOTES), $label), OutputType::html);
    }
}
