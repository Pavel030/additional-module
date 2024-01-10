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
     * @param null|int $userId Current user's id
     *
     * @return array
     */
    public function getOrderedProductsIds(int $userId): array
    {
        if (!empty($userId)) {
            $conditions = $this->db->quote(' AND user_id = ?i', $userId);
            $conditions .= $this->db->quote(' AND status <> ?s', 'D');
            $join = $this->db->quote(" JOIN ?:orders ON ?:order_details.order_id = ?:orders.order_id");

            $productsIds[] = $this->db->getColumn("SELECT DISTINCT product_id FROM ?:order_details ?p WHERE 1 ?p", $join, $conditions);
        }
        return $productsIds;
    }

    /**
     * Getting a variation group_id by product_id
     *
     * @param null|int $productId Current product's id
     *
     * @return array|int
     */
    public function getVariationGroupId(int $productId)
    {
        if (!empty($productId)) {
            $variationGroupId = $this->db->getField("SELECT group_id FROM ?:productVariationGroup_products WHERE product_id = ?i", $productId);
        }
        return $variationGroupId;
    }

    /**
     * Getting a variation group_id by product_id
     *
     * @param null|int $productId Current product's id
     *
     * @return array|int
     */
    public function getPrevVariationGroupIds(int $userId)
    {
        if (!empty($userId)) {
            $conditions = $this->db->quote(' AND user_id = ?i', $userId);
            $conditions .= $this->db->quote(' AND status <> ?s', 'D');
            $join = $this->db->quote(" JOIN ?:order_details od ON pv.product_id = od.product_id");
            $join .= $this->db->quote(" JOIN ?:orders o ON od.order_id = o.order_id");
            $prevVariationGroupIds = $this->db->getColumn("SELECT DISTINCT pv.group_id FROM ?:productVariationGroup_products pv ?p WHERE 1 ?p", $join, $conditions);
        }
        return $prevVariationGroupIds;
    }

    /**
     * Checks whether the module is activated for the current product
     *
     * @param int $productId Current product's id
     *
     * @return bool
     */
    public function getAllowOnceMode(int $productId): bool
    {
        $allowOnceMode = 2;

        if (!empty($productId)) {
            $allowOnceMode = $this->db->getField("SELECT allow_once FROM ?:products WHERE product_id = ?i", $productId);
        }
        return $allowOnceMode;
    }

    /**
     * Checks if the current product is in the cart
     *
     * @param array $cart Info about current cart state
     * @param array $productData Preliminary product information
     *
     * @return bool
     */
    public function alreadyInCart(array $cart, array $productData): bool
    {
        $alreadyAddedToCart = false;

        $productId = (int) ($productData ? array_key_first($productData) : null);

        $alreadyAddedToCart = in_array($productId, array_column($cart['products'], 'product_id'));

        return $alreadyAddedToCart;
    }

    /**
     * Checks whether this particular product has been purchased previously
     *
     * @param array $auth Extensive user information
     * @param array $productData Preliminary product information
     *
     * @return bool
     */
    public function alreadyOrderedProductId(array $auth, array $productData): bool
    {
        $result = false;
        $alreadyUsedProductId = false;
        $orderedProductsIds = $this->getOrderedProductsIds($auth['user_id']);
        foreach ($productData as $key => $data) {
            $productId = (int)$data['product_id'];
            foreach ($orderedProductsIds as $orderedProductsId) {
                $alreadyUsedProductId[] = in_array($productId, $orderedProductsId);
            }
        }
        if (in_array(true, $alreadyUsedProductId)) {
            $result = true;
        }
        return $result;
    }

    /**
     * Checks the current product for alignment with the variation groups affected by previous orders
     *
     * @param array $auth Extensive user information
     * @param array $productData Preliminary product information
     *
     * @return bool
     */
    public function alreadyOrderedGroupId($auth, $productData): bool
    {
        $alreadyOrderedGroupId = false;

        foreach ($productData as $key => $data) {
            $productId = (int) $data['product_id'];
            $currentProductVariationGroup[] = $this->getVariationGroupId($productId);
        }

        $alreadyOrderedVariationGroup = $this->getPrevVariationGroupIds($auth['user_id']);

        if (!empty(array_intersect($currentProductVariationGroup, $alreadyOrderedVariationGroup)) && array_sum(array_filter($currentProductVariationGroup)) != 0) {
            $alreadyOrderedGroupId = true;
        }
        
        return $alreadyOrderedGroupId;
    }

    /**
     * Combines two boolean values
     * 1. checking for the use of a variation group
     * 2. checking for the use of product id from completed orders
     *
     * @param array $auth Extensive user information
     * @param array $productData Preliminary product information
     *
     * @return bool
     */
    public function alreadyOrdered(array $auth, array $productData): bool
    {
        $alreadyOrderedGroupId = $this->alreadyOrderedGroupId($auth, $productData);
        $alreadyOrderedProductId = $this->alreadyOrderedProductId($auth, $productData);
        return $alreadyOrderedGroupId or $alreadyOrderedProductId;
    }
}
