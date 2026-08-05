<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Les tests rendent des pages Inertia sans construire les assets : Vite est
     * neutralisé, sinon la CI échoue sur un manifest absent. Les vrais assets
     * sont, eux, construits et vérifiés par la construction de l'image.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
