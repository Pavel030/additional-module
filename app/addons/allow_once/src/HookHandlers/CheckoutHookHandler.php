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
     * @param array $productData What the user is trying to add
     *
     * @return array
     */
    public function onPreAddToCart(&$productData, $cart, $auth, $update)
    {
        $allowOnce = ServiceProvider::getAllowOnce();

        $alreadyOrdered = $allowOnce->alreadyOrdered($auth, $productData);
        $alreadyOrderedGroup_id = $allowOnce->alreadyOrderedGroupId($auth, $productData);
        $alreadyInCart = $allowOnce->alreadyInCart($cart, $productData);
        $allowOnce_mode = $allowOnce->getAllowOnceMode((int)array_key_first($productData));

        if ($allowOnce_mode == 2) {
            if ($alreadyInCart and !$alreadyOrdered) {
                $msg = __('already_added_to_cart');
            }
            if ($alreadyOrdered and $alreadyInCart) {
                $msg = __('this_or_similar');
            }
            if ($alreadyOrdered and !$alreadyInCart) {
                $msg = __('this_or_similar');
            }
            if ($alreadyOrderedGroup_id) {
                $msg = __('this_or_similar');
            }
            if (isset($msg)){
                fn_set_notification('N', __('notice'), $msg, 'I');
                $productData = [];
            }
        }
        return $productData;
    }
}
