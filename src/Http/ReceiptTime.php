<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle\Http;

use Symfony\Component\HttpFoundation\Request;

final class ReceiptTime
{
    public static function fromRequest(Request $request): \DateTimeImmutable
    {
        // PHP sets this before routing, session locks, listeners or mail delivery.
        $arrival = $request->server->get('REQUEST_TIME_FLOAT');
        if (\is_float($arrival) || \is_int($arrival)) {
            $time = \DateTimeImmutable::createFromFormat('U.u', \sprintf('%.6F', $arrival));
            if (false !== $time) {
                return $time->setTimezone(new \DateTimeZone('UTC'));
            }
        }

        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
