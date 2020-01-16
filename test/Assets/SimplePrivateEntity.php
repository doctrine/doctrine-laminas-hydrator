<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

use Exception;

class SimplePrivateEntity
{
    private function setPrivate($value) : void
    {
        throw new Exception('Should never be called');
    }

    private function getPrivate() : void
    {
        throw new Exception('Should never be called');
    }

    protected function setProtected($value) : void
    {
        throw new Exception('Should never be called');
    }

    protected function getProtected() : void
    {
        throw new Exception('Should never be called');
    }
}
