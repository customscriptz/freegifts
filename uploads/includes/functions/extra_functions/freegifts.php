<?php
/**
 * Free Gifts for Zen-Cart
 * @link https://github.com/customscriptz/freegifts
 */

/**
 * Check if a product is a free gift
 */
function freegifts_is_freegift($products_id = 0)
{
    global $db;

    $freegifts = $db->Execute("SELECT
									count(f.products_id) as gifts
								FROM " . TABLE_FREEGIFTS . " f
								JOIN " . TABLE_PRODUCTS . " p ON (f.products_id = p.products_id)
								JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON (f.products_id = pd.products_id)
								WHERE
									p.products_status = 1 AND
									f.freegifts_status = 1 AND
									pd.language_id = '" . (int)$_SESSION['languages_id'] . "' AND
									f.freegifts_start_date <= Now() AND
									f.freegifts_end_date >= Now() AND
									f.products_id = " . (int)$products_id);

    if ($freegifts->fields['gifts'] == 1) return true;

    return false;
}

function freegiftsCalculateShoppingCart(&$products_array)
{
    global $db;

    if (count($products_array)) {
        $sql = "SELECT
				*
			FROM " . TABLE_FREEGIFTS . " f
			JOIN " . TABLE_PRODUCTS . " p ON (f.products_id = p.products_id)
			JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON (f.products_id = pd.products_id)
			WHERE
				p.products_status = 1 AND
				f.freegifts_status = 1 AND
				pd.language_id = '" . (int)$_SESSION['languages_id'] . "' AND
				f.freegifts_start_date <= '" . date('Y-m-d', time()) . "' AND
				f.freegifts_end_date >= '" . date('Y-m-d', time()) . "'
			ORDER BY f.freegifts_threshold DESC
		";
        $freegifts = $db->Execute($sql);

        $gifts = array();
        while (!$freegifts->EOF) {
            $gifts[$freegifts->fields['products_id']] = $freegifts->fields;
            $freegifts->MoveNext();
        }

        $ids = array();

        $cart_total = 0;
        for ($c = 0; $c < count($products_array); $c++) {
            $product_id = zen_get_prid($products_array[$c]['id']);
            $final_price = $products_array[$c]['final_price'];
            $qty = $products_array[$c]['quantity'];
            $model = $products_array[$c]['model'];

            $ids[] = $product_id;

            if (preg_match('/^GIFT/', $model))
                continue;

            $cart_total += zen_round($final_price * $qty, 2);
        }

        $products_array_list = $products_array;
        $_SESSION['gift_exist'] = $_SESSION['gift_amount'] = 0;
        for ($i = 0; $i < count($products_array); $i++) {
            $model = $products_array[$i]['model'];
            if (preg_match('/^GIFT/', $model))
                continue;

            $product_id = (int)zen_get_prid($products_array[$i]['id']);
            if ($product_id == $gifts[$product_id]['products_id']) {
                $cart_total_categories = 0;
                if ($gifts[$product_id]['freegifts_threshold_categories']) {
                    reset($products_array_list);

                    $categories = explode(',', $gifts[$product_id]['freegifts_threshold_categories']);
                    foreach ($categories as $categories_id) {
                        $tree = get_category_tree($categories_id);
                        $categories = array_merge($categories, $tree);
                    }

                    $categories = array_unique($categories);

                    foreach ($products_array_list as $product) {
                        $products_id = (int)zen_get_prid($product['id']);

                        if ($products_id != $product_id) {
                            $model = $product['model'];
                            $final_price = $product['final_price'];
                            $qty = $product['quantity'];

                            if (preg_match('/^GIFT/', $model))
                                continue;

                            $result = $db->Execute("SELECT * FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " WHERE products_id = $products_id AND categories_id IN (" . implode(',', $categories) . ")");
                            if ($result->RecordCount()) {
                                $cart_total_categories += zen_round($final_price * $qty, 2);
                            }
                        }
                    }
                }

                $allow = false;
                if ($gifts[$product_id]['freegifts_type'] == 1 || $gifts[$product_id]['freegifts_type'] == 2) {
                    $required_products = explode(',', $gifts[$product_id]['freegifts_required_products']);
                    foreach ($required_products as $products_id) {
                        if (in_array($products_id, $ids)) {
                            $allow = true;
                        }
                    }
                }

                if (!(int)$gifts[$product_id]['freegifts_type'] || $gifts[$product_id]['freegifts_type'] == 2 && $allow == true) {
                    $product = array_values(array_filter($products_array, function($a) use ($product_id) {
                      return zen_get_prid($a['id']) == $product_id;
                    }));

                    $gift = array_values(array_values(array_filter($gifts, function($a) use ($product_id) {
                      return $a['products_id'] == $product_id;
                    })));

                    $gift = $gift[0];
                    $product = $product[0];

                    $threshold = (float)$gift['freegifts_threshold'] + ($product['final_price'] * $product['quantity']);
                    if ($gifts[$product_id]['freegifts_threshold_categories']) {
                      $threshold = (float)$gift['freegifts_threshold'];
                      if ($cart_total_categories >= $threshold && $cart_total_categories > 0 && $_SESSION['gift_exist'] == 0) {
                        $allow = true;
                      } else {
                        $allow = false;
                      }
                    } else {
                      if ($cart_total >= $threshold && $cart_total > 0 && $_SESSION['gift_exist'] == 0) {
                          $allow = true;
                      } else {
                          $allow = false;
                      }
                    }
                }

                if ($allow AND $_SESSION['gift_exist'] == 0) {
                    $_SESSION['gift_amount'] = $products_array[$i]['final_price'];

                    $products_array[$i]['name'] = $gifts[$product_id]['products_name'] . ' ' . TEXT_QUALIFIED_FOR_THIS_GIFT;
                    $products_array[$i]['product_is_free'] = 1;
                    $products_array[$i]['price'] = 0;
                    $products_array[$i]['final_price'] = 0;
                    $products_array[$i]['quantity'] = 1;

                    $_SESSION['gift_exist'] = $product_id;
                    break;
                }
            }
        }
    }
}

function freegiftsCalculateTotal()
{
    $_SESSION['cart']->total -= $_SESSION['gift_amount'];
}

function get_category_tree($parent_id, $category_tree_array = '') {
    global $db;

    if (!is_array($category_tree_array)) $category_tree_array = array();

    $categories = $db->Execute("select c.categories_id, cd.categories_name, c.parent_id
                                from " . TABLE_CATEGORIES . " c, " . TABLE_CATEGORIES_DESCRIPTION . " cd
                                where c.categories_id = cd.categories_id
                                and cd.language_id = '" . (int)$_SESSION['languages_id'] . "'
                                and c.parent_id = '" . (int)$parent_id . "'
                                order by c.sort_order, cd.categories_name");

    while (!$categories->EOF) {
        $category_tree_array[] = $categories->fields['categories_id'];
        $category_tree_array = get_category_tree($categories->fields['categories_id'], $category_tree_array);
        $categories->MoveNext();
    }

    return $category_tree_array;
}