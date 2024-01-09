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
     * @param array $product_data What the user is trying to add
     *
     * @return array
     */
    public function onPreAddToWishlist(&$product_data, $wishlist, $auth)
    {
        $allow_once = ServiceProvider::getAllowOnce();

        $already_ordered = $allow_once->already_ordered($auth, $product_data);
        $allow_once_mode = $allow_once->get_allow_once_mode((int)array_key_first($product_data));
        if (in_array($allow_once_mode, [2, 3]) and !$already_ordered) {
            $msg = __('cannot_add_to_wishlist');
            $product_data = [];
        } elseif (in_array($allow_once_mode, [2, 3]) and $already_ordered) {
            $msg = __('cannot_add_to_wishlist_and_ordered');
            $product_data = [];
        }
        fn_set_notification('N', __('notice'), $msg, 'I');

        return $product_data;
    }
}



