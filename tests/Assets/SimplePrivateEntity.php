<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

use Exception;

class SimplePrivateEntity
{
    /**
     * @param         mixed $value
     *
     * @phpcsSuppress SlevomatCodingStandard.Classes.UnusedPrivateElements
     * @phpstan-ignore-next-line
     */
    private function setPrivate($value)
    {
        throw new Exception('Should never be called');
    }

    /**
     * @phpcsSuppress SlevomatCodingStandard.Classes.UnusedPrivateElements
     * @phpstan-ignore-next-line
     */
    private function getPrivate()
    {
        throw new Exception('Should never be called');
    }

    /**
     * @param mixed $value
     */
    protected function setProtected($value)
    {
        throw new Exception('Should never be called');
    }

    protected function getProtected()
    {
        throw new Exception('Should never be called');
    }
}
