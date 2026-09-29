<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Tests;

use Contao\ContentModel;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Doctrine\DBAL\DriverManager;
use Nordwerk\WithdrawalBundle\Controller\ContentElement\WithdrawalController;
use Nordwerk\WithdrawalBundle\Mail\WithdrawalMailer;
use Nordwerk\WithdrawalBundle\Persistence\WithdrawalRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class ControllerTest extends TestCase
{
    public function testReceiptPrecedesIntegrationListenersAndTheirErrorsStayPrivate(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_mysql', 'host' => 'db', 'user' => 'contao', 'password' => 'contao', 'dbname' => 'contao']);
        $repository = new WithdrawalRepository($connection);
        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__.'/../templates', 'NordwerkWithdrawal');
        $sequence = [];
        $transport = $this->createMock(TransportInterface::class);
        $transport
            ->method('send')
            ->willReturnCallback(
                static function (Email $email) use (&$sequence): SentMessage {
                    $sequence[] = 'mail';

                    return new SentMessage($email, Envelope::create($email));
                },
            )
        ;
        $events = $this->createMock(EventDispatcherInterface::class);
        $events
            ->method('dispatch')
            ->willReturnCallback(
                static function () use (&$sequence): never {
                    $sequence[] = 'event';

                    throw new \RuntimeException('PRIVATE CUSTOMER private@example.test');
                },
            )
        ;
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Withdrawal event listener failed.',
                $this->callback(static fn (array $context): bool => !str_contains(serialize($context), 'PRIVATE CUSTOMER') && !str_contains(serialize($context), 'private@example.test')),
            )
        ;
        $csrf = $this->createMock(ContaoCsrfTokenManager::class);
        $csrf
            ->method('getDefaultTokenValue')
            ->willReturn('csrf')
        ;
        $controller = new WithdrawalController($csrf, $repository, new WithdrawalMailer($transport, new Environment($loader), $repository, 'shop@example.test'), $events, $logger);
        $token = bin2hex(random_bytes(32));
        $request = Request::create('/withdrawal', 'POST', ['withdrawal_element' => '7', 'withdrawal_action' => 'confirm', 'withdrawal_flow' => $token]);
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);
        $session->set('withdrawal_flow_7_'.$token, ['token' => $token, 'values' => ['name' => 'Ada', 'contractReference' => 'EVENT', 'email' => 'a@example.test']]);
        $model = $this->createMock(ContentModel::class);
        $model
            ->method('__get')
            ->willReturn(7)
        ;
        $template = new FragmentTemplate('test', static fn (): Response => new Response());

        try {
            (new \ReflectionMethod($controller, 'getResponse'))->invoke($controller, $template, $model, $request);
            $this->assertSame(['mail', 'mail', 'event'], $sequence);
            $this->assertSame('success', $template->get('stage'));
        } finally {
            $connection->delete('tl_withdrawal', ['submissionToken' => $token]);
            $connection->close();
        }
    }
}
