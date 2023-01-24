<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

use Exception;

class SimplePrivateEntity
{
    /**
     * @phpcsSuppress SlevomatCodingStandard.Classes.UnusedPrivateElements
     * @phpstan-ignore-next-line
     */
    private function setPrivate(mixed $value): void
    {
        throw new Exception('Should never be called');
    }

    /**
     * @phpcsSuppress SlevomatCodingStandard.Classes.UnusedPrivateElements
     * @phpstan-ignore-next-line
     */
    private function getPrivate(): void
    {
        throw new Exception('Should never be called');
    }

    protected function setProtected(mixed $value): void
    {
        throw new Exception('Should never be called');
    }

    protected function getProtected(): void
    {
        throw new Exception('Should never be called');
    }
}
