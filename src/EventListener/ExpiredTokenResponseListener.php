<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\EventListener;

use Contao\CoreBundle\Exception\InvalidRequestTokenException;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 128)]
final readonly class ExpiredTokenResponseListener
{
    public function __construct(
        private Environment $twig,
        private ScopeMatcher $scopeMatcher,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$event->getThrowable() instanceof InvalidRequestTokenException || !$this->scopeMatcher->isFrontendRequest($request) || !$request->request->has('withdrawal_element')) {
            return;
        }

        $response = new Response($this->twig->render('@NordwerkWithdrawal/error/expired.html.twig', [
            'formUrl' => '/'.ltrim($request->getRequestUri(), '/'),
        ]), Response::HTTP_BAD_REQUEST);
        $response->headers->set('Cache-Control', 'private, no-store');
        $event->setResponse($response);
    }
}
