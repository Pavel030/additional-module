<?php

namespace Tygh\Addons\AllowOnce\HookHandlers;

use Tygh\Addons\AllowOnce\ServiceProvider;
use Tygh\Application;

/**
 * This class describes the hook handlers related to product management
 *
 * @package Tygh\Addons\AllowOnce\HookHandlers
 */
class CheckoutHookHandler
{
    protected $application;

    public function __construct(Application $application)
    {
        $this->application = $application;
    }

    /**
     * The "pre_add_to_cart" hook handler.
     *
     * Actions performed:
     *  - The hook completes the main logic of the module.
     * First, flag variables are declared that reflect the
     * state of the cart and purchase history.
     * Notifications are assigned depending on the flags.
     *
     * @param array $product_data What the user is trying to add
     *
     * @return array
     */
    public function onPreAddToCart(&$product_data, $cart, $auth, $update)
    {
        $allow_once = ServiceProvider::getAllowOnce();

        $already_ordered = $allow_once->already_ordered($auth, $product_data);
        $already_ordered_group_id = $allow_once->already_ordered_group_id($auth, $product_data);
        $already_in_cart = $allow_once->already_in_cart($cart, $product_data);
        $allow_once_mode = $allow_once->get_allow_once_mode((int)array_key_first($product_data));

        if ($allow_once_mode == 2) {
            if ($already_in_cart and !$already_ordered) {
                $msg = __('already_added_to_cart');
            }
            if ($already_ordered and $already_in_cart) {
                $msg = __('this_or_similar');
            }
            if ($already_ordered and !$already_in_cart) {
                $msg = __('this_or_similar');
            }
            if ($already_ordered_group_id) {
                $msg = __('this_or_similar');
            }
            if (isset($msg)){
                fn_set_notification('N', __('notice'), $msg, 'I');
                $product_data = [];
            }
        }
        return $product_data;
    }
}
