<?php

namespace Tygh\Addons\AllowOnce;

use Tygh\Core\ApplicationInterface;
use Tygh\Core\BootstrapInterface;
use Tygh\Core\HookHandlerProviderInterface;
/**
 * This class describes instructions for loading the allow_once add-on
 *
 * @package Tygh\Addons\AllowOnce
 */
class Bootstrap implements BootstrapInterface, HookHandlerProviderInterface
{
    /**
     * @inheritDoc
     */
    public function boot(ApplicationInterface $app)
    {
        $app->register(new ServiceProvider());
    }

    public function getHookHandlerMap()
    {
        return [
            'pre_add_to_cart' => [
                'addons.allow_once.hook_handlers.checkout',
                'onPreAddToCart'
            ],
            'pre_add_to_wishlist' => [
                'addons.allow_once.hook_handlers.wishlist',
                'onPreAddToWishlist'
            ],
        ];
    }
}
