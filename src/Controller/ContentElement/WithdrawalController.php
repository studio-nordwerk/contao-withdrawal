<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Nordwerk\WithdrawalBundle\Domain\WithdrawalDeclaration;
use Nordwerk\WithdrawalBundle\Event\WithdrawalSubmittedEvent;
use Nordwerk\WithdrawalBundle\Http\ReceiptTime;
use Nordwerk\WithdrawalBundle\Mail\WithdrawalMailer;
use Nordwerk\WithdrawalBundle\Persistence\WithdrawalRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsContentElement('withdrawal', category: 'miscellaneous')]
final class WithdrawalController extends AbstractContentElementController
{
    public function __construct(
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly WithdrawalRepository $repository,
        private readonly WithdrawalMailer $mailer,
        private readonly EventDispatcherInterface $events,
        private readonly LoggerInterface $logger,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $receivedAt = ReceiptTime::fromRequest($request);
        $session = $request->getSession();
        $flowId = (string) $request->request->get('withdrawal_flow', '');
        if (!preg_match('/^[a-f0-9]{64}$/D', $flowId)) {
            $flowId = bin2hex(random_bytes(32));
        }
        $key = 'withdrawal_flow_'.$model->id.'_'.$flowId;
        /** @var array<string, mixed> $flow */
        $flow = $session->get($key, []);
        $stage = 'form';
        $errors = [];
        $values = ['name' => '', 'contractReference' => '', 'email' => ''];

        if ('POST' === $request->getMethod() && (string) $request->request->get('withdrawal_element') === (string) $model->id) {
            $action = (string) $request->request->get('withdrawal_action');

            if (isset($flow['id'])) {
                $row = $this->repository->find((int) $flow['id']);
                if (null !== $row) {
                    $stage = 'success';
                    $template->set('submittedAt', new \DateTimeImmutable((string) $row['submittedAt']));
                }
            } elseif ('edit' === $action && isset($flow['values'])) {
                $values = $flow['values'];
            } elseif ('review' === $action) {
                $values = [
                    'name' => trim((string) $request->request->get('name')),
                    'contractReference' => trim((string) $request->request->get('contractReference')),
                    'email' => trim((string) $request->request->get('email')),
                ];
                $errors = WithdrawalDeclaration::validate(...array_values($values));

                if ('' !== (string) $request->request->get('website')) {
                    $errors['spam'] = 'spam';
                }

                if ([] === $errors) {
                    // A new immutable snapshot also protects an older review after Back/Edit.
                    $flowId = bin2hex(random_bytes(32));
                    $key = 'withdrawal_flow_'.$model->id.'_'.$flowId;
                    $flow = ['token' => $flowId, 'values' => $values];
                    $session->set($key, $flow);
                    $stage = 'review';
                }
            } elseif ('confirm' === $action && isset($flow['token'], $flow['values'])) {
                $token = (string) $flow['token'];
                $row = $this->repository->findByToken($token);

                if (null === $row) {
                    /** @var array{name: string, contractReference: string, email: string} $stored */
                    $stored = $flow['values'];
                    $declaration = new WithdrawalDeclaration($stored['name'], $stored['contractReference'], $stored['email']);

                    $id = null;

                    try {
                        $id = $this->repository->create($token, $declaration, $receivedAt, $request->getLocale());
                    } catch (UniqueConstraintViolationException) {
                        // A concurrent confirmation already created this declaration.
                    }

                    $row = $this->repository->findByToken($token);

                    if (null !== $row) {
                        try {
                            $this->mailer->sendPending($row);
                        } catch (\Throwable $exception) {
                            $this->logger->error('Withdrawal mail delivery failed.', ['failure_type' => $exception::class, 'withdrawal' => $row['id']]);
                        }
                    }

                    if (null !== $id) {
                        try {
                            $this->events->dispatch(new WithdrawalSubmittedEvent($id, $declaration, $receivedAt));
                        } catch (\Throwable $exception) {
                            $this->logger->error('Withdrawal event listener failed.', ['failure_type' => $exception::class, 'withdrawal' => $id]);
                        }
                    }
                }

                if (null !== $row) {
                    $flow = ['token' => $token, 'id' => (int) $row['id']];
                    $session->set($key, $flow);
                    $stage = 'success';
                    $template->set('submittedAt', (new \DateTimeImmutable((string) $row['submittedAt'], new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/Berlin')));
                }
            }
        } elseif ('done' === $request->query->get('withdrawal') && isset($flow['id'])) {
            $row = $this->repository->find((int) $flow['id']);

            if (null !== $row) {
                $stage = 'success';
                $template->set('submittedAt', (new \DateTimeImmutable((string) $row['submittedAt'], new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/Berlin')));
            }
        }

        if ('review' === $stage) {
            $values = $flow['values'];
        }

        $template->set('stage', $stage);
        $template->set('values', $values);
        $template->set('errors', $errors);
        $template->set('token', $this->csrfTokenManager->getDefaultTokenValue());
        $template->set('elementId', $model->id);
        $template->set('flowId', $flowId);
        $template->set('formUrl', '/'.ltrim($request->getRequestUri(), '/'));

        return $template->getResponse();
    }
}
