<?php
/**
 * Free Gifts for Zen-Cart
 * @link https://github.com/customscriptz/freegifts
 */

ob_start();

class freegifts_Observer extends base
{
    function freegifts_Observer()
    {
        global $zco_notifier;
        $zco_notifier->attach($this, array('NOTIFY_MAIN_TEMPLATE_VARS_START_PRODUCT_INFO', 'NOTIFY_HEADER_END_SHOPPING_CART', 'NOTIFY_ORDER_TOTAL_CAPTURE', 'NOTIFY_HEADER_END_CHECKOUT_CONFIRMATION', 'NOTIFY_ORDER_DURING_CREATE_ADDED_PRODUCT_LINE_ITEM', 'NOTIFY_HEADER_START_SHOPPING_CART'));
    }

    function update(&$class, $eventID)
    {
        global $db, $messageStack, $currencies, $template;
        if ($eventID == 'NOTIFY_HEADER_START_SHOPPING_CART') {
            $freegift = $db->Execute("SELECT * FROM " . TABLE_FREEGIFTS . " f JOIN " . TABLE_PRODUCTS . " p ON p.products_id = f.products_id WHERE freegifts_id = " . (int)$_GET['id'] . " AND
						p.products_status = 1 AND
						f.freegifts_status = 1 AND
						f.freegifts_type = 1 AND
						f.freegifts_start_date <= '" . date('Y-m-d') . "' AND
						f.freegifts_end_date >= '" . date('Y-m-d') . "'");

            if ($freegift->RecordCount()) {
                $products = explode(',', $freegift->fields['freegifts_required_products']);
                $this->add_to_cart($freegift->fields['products_id'], false);
                foreach ($products as $product_id)
                    $this->add_to_cart($product_id, false);

                zen_redirect(zen_href_link(FILENAME_SHOPPING_CART));
            }
        } else if ($eventID != 'NOTIFY_ORDER_DURING_CREATE_ADDED_PRODUCT_LINE_ITEM') {
            $cart_content_with_id = array();
            $cart_content = array();
            foreach ($_SESSION['cart']->contents AS $product_id => $product_attr) {
                $cart_content_with_id[zen_get_prid($product_id)] = array('pid' => zen_get_prid($product_id), 'prid' => $product_id);
                $cart_content[] = zen_get_prid($product_id);
            }

            $cart_products = $_SESSION['cart']->get_products(false);
            $cart_products_clean = array();
            $order_total = 0;
            for ($i = 0; $i < count($cart_products); $i++) {
                $model = $cart_products[$i]['model'];
                if (strpos($model, 'GIFT') !== false)
                    continue;

                $price = zen_round($cart_products[$i]['final_price'], 2);
                $order_total += $price;
                $cart_products_clean[zen_get_prid($cart_products[$i]['id'])] = array('price' => $price);
            }

            //this is to prevent when someone use a coupon discount and the order total
            //drop the minimum value for the gift to be in the cart
            if ($eventID == 'NOTIFY_ORDER_TOTAL_CAPTURE') $order_total = $_SESSION['fg_order_total'];

            if ($_GET['act'] == 'add_free_gift' AND (int)$_GET['product_id'] > 0 AND $_SESSION['gift_exist'] == 0) {
                $this->add_to_cart($_GET['product_id']);
            } else if ($_GET['act'] == 'add_free_gift' AND (int)$_GET['product_id'] > 0 AND $_SESSION['gift_exist'] == 1) {
                zen_redirect(zen_href_link(zen_get_info_page($_GET['product_id']), 'products_id=' . $_GET['product_id']));
            } else if ($_GET['act'] == 'add_free_gift') {
                zen_redirect(zen_href_link(FILENAME_SHOPPING_CART));
            }

            $sql = "SELECT
						*
					FROM " . TABLE_FREEGIFTS . " f
					JOIN " . TABLE_PRODUCTS . " p ON (f.products_id = p.products_id)
					JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON (f.products_id = pd.products_id)
					WHERE
						p.products_status = 1 AND
						f.freegifts_status = 1 AND
						pd.language_id = '" . (int)$_SESSION['languages_id'] . "' AND
						f.freegifts_start_date <= '" . date('Y-m-d') . "' AND
						f.freegifts_end_date >= '" . date('Y-m-d') . "'
					ORDER BY f.freegifts_threshold DESC";

            $freegifts = $db->Execute($sql);

            $products_id = isset($_GET['products_id']) ? (int)zen_get_prid($_GET['products_id']) : '';
            while (!$freegifts->EOF) {
                $freegifts_products_id = $freegifts->fields['products_id'];
                $freegifts_threshold = $freegifts->fields['freegifts_threshold'];

                if ($freegifts_products_id == $products_id) {
                    if (!in_array($freegifts_products_id, $cart_content) AND $_SESSION['gift_exist'] == 0) {
                        $messageStack->add('product_info', sprintf(TEXT_FREEGIFTS_CAN_BE_GIFT, $currencies->format($freegifts_threshold)), 'success');
                    }
                }

                if (in_array($freegifts_products_id, $cart_content) AND $_SESSION['cc_id'] > 0 AND $cart_products_clean[$freegifts_products_id]['price'] == 0 AND FREEGIFTS_REMOVE_GIFTS_IF_COUPON_USED == 'True') {
                    unset($_SESSION['cc_id']);
                    $messageStack->add_session('checkout_confirmation', sprintf(TEXT_PRODUCT_REMOVED_FROM_CART, $freegifts->fields['products_name']), 'warning');
                    $messageStack->add_session('shopping_cart', sprintf(TEXT_PRODUCT_REMOVED_FROM_CART, $freegifts->fields['products_name']), 'warning');
                    $_SESSION['cart']->remove($cart_content_with_id[$freegifts_products_id]['prid']);
                    $_SESSION['product_removed'] = $cart_content_with_id[$freegifts_products_id]['pid'];
                    $_SESSION['used_coupon'] = true;
                    zen_redirect(zen_href_link(FILENAME_SHOPPING_CART));
                }

                $freegifts->MoveNext();
            }
        }
    }

    function add_to_cart($products_id, $redirect = true)
    {
        global $db, $messageStack;


        if ((int)$products_id > 0) {
            // get product attributes
            // check if any attribute was selected
            $sql = "SELECT freegifts_attributes
											FROM " . TABLE_FREEGIFTS . "
											WHERE products_id = '" . (int)$products_id . "'";
            $checkAttributes = $db->Execute($sql);

            if ($checkAttributes->fields['freegifts_attributes']) {
                $sql = "SELECT *
						FROM " . TABLE_PRODUCTS_ATTRIBUTES . "
						WHERE products_attributes_id IN (" . $checkAttributes->fields['freegifts_attributes'] . ")
						ORDER BY LPAD(products_options_sort_order,11,'0'), options_values_price";
                $attributes = $db->Execute($sql);

                $pattributes = array();
                while (!$attributes->EOF) {
                    $pattributes[$attributes->fields['options_id']][$attributes->fields['options_values_id']] = $attributes->fields['options_values_id'];

                    $attributes->MoveNext();
                }
            } else {
                $pr_attr = $db->Execute("SELECT count(*) as total
										FROM " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_ATTRIBUTES . " patrib
										WHERE patrib.products_id='" . (int)$products_id . "' AND patrib.options_id = popt.products_options_id
												and      popt.language_id = '" . (int)$_SESSION['languages_id'] . "'" .
                    " limit 1");

                if ($pr_attr->fields['total'] > 0) {
                    $products_options_names = $db->Execute("select distinct popt.products_options_id, popt.products_options_name, popt.products_options_sort_order,
										  popt.products_options_type, popt.products_options_length, popt.products_options_comment,
										  popt.products_options_size,
										  popt.products_options_images_per_row,
										  popt.products_options_images_style,
										  popt.products_options_rows
						  from        " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_ATTRIBUTES . " patrib
						  where           patrib.products_id='" . (int)$products_id . "'
						  and             patrib.options_id = popt.products_options_id
						  and             popt.language_id = '" . (int)$_SESSION['languages_id'] . "'
						  order by popt.products_options_name");

                    while (!$products_options_names->EOF) {

                        $products_options = $db->Execute("
									SELECT pov.products_options_values_id, pov.products_options_values_name, pa.*
									FROM      " . TABLE_PRODUCTS_ATTRIBUTES . " pa, " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov
									where     pa.products_id = '" . (int)$products_id . "'
									  and       pa.options_id = '" . (int)$products_options_names->fields['products_options_id'] . "'
									  and       pa.options_values_id = pov.products_options_values_id
									  and       pov.language_id = '" . (int)$_SESSION['languages_id'] . "'
									  order by LPAD(pa.products_options_sort_order,11,'0'), pa.options_values_price");

                        while (!$products_options->EOF) {
                            $products_options_value_id = $products_options->fields['products_options_values_id'];
                            $pattributes[$products_options_names->fields['products_options_id']][$products_options_value_id] = $products_options_value_id;
                            $products_options->MoveNext();
                        }
                        $products_options_names->MoveNext();
                    }
                }
            }

            $messageStack->add_session('shopping_cart', sprintf(TEXT_PRODUCT_ADDED_TO_CART, zen_get_products_name($products_id)), 'success');
            $_SESSION['cart']->add_cart($products_id, 1, $pattributes);
            $_SESSION['gift_exist'] = 1;

            if ($redirect)
                zen_redirect(zen_href_link(FILENAME_SHOPPING_CART));
        }
    }
}