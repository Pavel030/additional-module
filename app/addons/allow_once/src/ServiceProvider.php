<?php

namespace Tygh\Addons\AllowOnce;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Tygh\Addons\AllowOnce\Checkout\AllowOnce;
use Tygh\Addons\AllowOnce\HookHandlers\CheckoutHookHandler;
use Tygh\Addons\AllowOnce\HookHandlers\WishlistHookHandler;
use Tygh\Tygh;

/**
 * Class ServiceProvider is intended to register services and components of the "Allow Once" add-on to the application
 * container.
 *
 * @package Tygh\Addons\AllowOnce
 */
class ServiceProvider implements ServiceProviderInterface
{
    /**
     * @inheritDoc
     */
    public function register(Container $app)
    {
        $app['addons.allow_once'] = function (Container $app) {
            return new AllowOnce($app);
        };
        $app['addons.allow_once.hook_handlers.checkout'] = function (Container $app) {
            return new CheckoutHookHandler($app);
        };
        $app['addons.allow_once.hook_handlers.wishlist'] = function (Container $app) {
            return new WishlistHookHandler($app);
        };
    }

    /**
     * @return Service
     */
    public static function getAllowOnce()
    {
        return Tygh::$app['addons.allow_once'];
    }
}
