<?php

namespace PictaStudio\Translatable\Tests;

class MiddlewareDisabledTestCase extends TestCase
{
    public function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        config()->set('translatable.register_locale_middleware', false);
    }
}
