<?php

namespace Tygh\Addons\AllowOnce\HookHandlers;

use Tygh\Addons\AllowOnce\ServiceProvider;
use Tygh\Application;

/**
 * This class describes the hook handlers related to how the
 * addon works regarding adding a product to the wishlist
 *
 * @package Tygh\Addons\AllowOnce\HookHandlers
 */
class WishlistHookHandler
{
    protected $application;

    public function __construct(Application $application)
    {
        $this->application = $application;
    }

    /**
     * The "pre_add_to_wishlist" hook handler.
     *
     * Actions performed:
     *  - The hook fills in the logic of the module regarding the wish list.
     * First, flag variables are declared that reflect the state of the cart
     * and purchase history. Notifications are assigned depending on the flags
     * and mode of the module.
     *
     * @param array $productData What the user is trying to add
     *
     * @return array
     */
    public function onPreAddToWishlist(&$productData, $wishlist, $auth)
    {
        $allowOnce = ServiceProvider::getAllowOnce();

        $alreadyOrdered = $allowOnce->alreadyOrdered($auth, $productData);
        $allowOnceMode = $allowOnce->getAllowOnceMode((int)array_key_first($productData));
        if (in_array($allowOnceMode, [2, 3])) {
            if (!$alreadyOrdered) {
                $msg = __('cannot_add_to_wishlist');
            } else {
                $msg = __('cannot_add_to_wishlist_and_ordered');
            }
            if (isset($msg)){
                fn_set_notification('N', __('notice'), $msg, 'I');
                $productData = [];
            }
        }
        return $productData;
    }
}



