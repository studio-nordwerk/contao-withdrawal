<?php

declare(strict_types=1);

namespace Nordwerk\WithdrawalBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

final class NordwerkWithdrawalBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
