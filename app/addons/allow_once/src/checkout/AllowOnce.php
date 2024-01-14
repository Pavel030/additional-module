<?php

namespace Tygh\Addons\AllowOnce\Checkout;

use Tygh\Application;

/**
 * Class AllowOnce
 *
 * @package Product
 */
class AllowOnce
{
    protected $db;

    /**
     * @param Application $app Application instance
     */
    public function __construct(Application $app)
    {
        $this->db = $app['db'];
    }

    /**
     * Getting a list of product's ids that the user has ever ordered
     *
     * @param null|int $user_id Current user's id
     *
     * @return array
     */
    public function getOrderedProductsIds(int $user_id): array
    {
        if (!empty($user_id)) {
            $conditions = $this->db->quote(' AND user_id = ?i', $user_id);
            $conditions .= $this->db->quote(' AND status <> ?s', 'D');
            $join = $this->db->quote(" JOIN ?:orders ON ?:order_details.order_id = ?:orders.order_id");

            $products_ids[] = $this->db->getColumn("SELECT DISTINCT product_id FROM ?:order_details ?p WHERE 1 ?p", $join, $conditions);
        }
        return $products_ids;
    }

    /**
     * Getting a variation group_id by product_id
     *
     * @param null|int $product_id Current product's id
     *
     * @return array|int
     */
    public function getVariationGroupId(int $product_id)
    {
        if (!empty($product_id)) {
            $variation_group_id = $this->db->getField("SELECT group_id FROM ?:product_variation_group_products WHERE product_id = ?i", $product_id);
        }
        return $variation_group_id;
    }

    /**
     * Getting a variation group_id by product_id
     *
     * @param null|int $product_id Current product's id
     *
     * @return array|int
     */
    public function getPrevVariationGroupIds(int $user_id)
    {
        if (!empty($user_id)) {
            $conditions = $this->db->quote(' AND user_id = ?i', $user_id);
            $conditions .= $this->db->quote(' AND status <> ?s', 'D');
            $join = $this->db->quote(" JOIN ?:order_details od ON pv.product_id = od.product_id");
            $join .= $this->db->quote(" JOIN ?:orders o ON od.order_id = o.order_id");
            $prev_variation_group_ids = $this->db->getColumn("SELECT DISTINCT pv.group_id FROM ?:product_variation_group_products pv ?p WHERE 1 ?p", $join, $conditions);
        }
        return $prev_variation_group_ids;
    }

    /**
     * Checks whether the module is activated for the current product
     *
     * @param int $product_id Current product's id
     *
     * @return bool
     */
    public function getAllowOnceMode(int $product_id): bool
    {
        $allow_once_mode = 2;

        if (!empty($product_id)) {
            $allow_once_mode = $this->db->getField("SELECT allow_once FROM ?:products WHERE product_id = ?i", $product_id);
        }
        return $allow_once_mode;
    }

    /**
     * Checks if the current product is in the cart
     *
     * @param array $cart Info about current cart state
     * @param array $product_data Preliminary product information
     *
     * @return bool
     */
    public function alreadyInCart(array $cart, array $product_data): bool
    {
        $already_added_to_cart = false;

        $product_id = (int) ($product_data ? array_key_first($product_data) : null);

        $already_added_to_cart = in_array($product_id, array_column($cart['products'], 'product_id'));

        return $already_added_to_cart;
    }

    /**
     * Checks whether this particular product has been purchased previously
     *
     * @param array $auth Extensive user information
     * @param array $product_data Preliminary product information
     *
     * @return bool
     */
    public function alreadyOrderedProductId(array $auth, array $product_data): bool
    {
        $result = false;
        $already_used_product_id = false;
        $ordered_product_ids = $this->getOrderedProductsIds($auth['user_id']);
        foreach ($product_data as $key => $data) {
            $product_id = (int)$data['product_id'];
            foreach ($ordered_product_ids as $ordered_product_ids) {
                $already_used_product_id[] = in_array($product_id, $ordered_product_ids);
            }
        }
        if (in_array(true, $already_used_product_id)) {
            $result = true;
        }
        return $result;
    }

    /**
     * Checks the current product for alignment with the variation groups affected by previous orders
     *
     * @param array $auth Extensive user information
     * @param array $product_data Preliminary product information
     *
     * @return bool
     */
    public function alreadyOrderedGroupId($auth, $product_data): bool
    {
        $already_ordered_group_id = false;

        foreach ($product_data as $key => $data) {
            $product_id = (int) $data['product_id'];
            $current_product_variation_group[] = $this->getVariationGroupId($product_id);
        }

        $already_ordered_variation_group = $this->getPrevVariationGroupIds($auth['user_id']);

        if (!empty(array_intersect($current_product_variation_group, $already_ordered_variation_group)) && array_sum(array_filter($current_product_variation_group)) != 0) {
            $already_ordered_group_id = true;
        }
        
        return $already_ordered_group_id;
    }

    /**
     * Combines two boolean values
     * 1. checking for the use of a variation group
     * 2. checking for the use of product id from completed orders
     *
     * @param array $auth Extensive user information
     * @param array $product_data Preliminary product information
     *
     * @return bool
     */
    public function alreadyOrdered(array $auth, array $product_data): bool
    {
        $already_ordered_group_id = $this->alreadyOrderedGroupId($auth, $product_data);
        $already_ordered_product_id = $this->alreadyOrderedProductId($auth, $product_data);
        return $already_ordered_group_id or $already_ordered_product_id;
    }
}
