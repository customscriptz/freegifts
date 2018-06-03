<?php
/**
 * Free Gifts for Zen-Cart
 * @link https://github.com/customscriptz/freegifts
 */

if (!isset($_SESSION['gift_exist'])) {
    $_SESSION['gift_exist'] = 0;
}

$freegifts_query = $db->Execute("SELECT
            *
        FROM " . TABLE_FREEGIFTS . " f
        JOIN " . TABLE_PRODUCTS . " p ON (f.products_id = p.products_id)
        JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON (f.products_id = pd.products_id)
        WHERE
            p.products_status = 1 AND
            f.freegifts_status = 1 AND
            pd.language_id = '" . (int) $_SESSION['languages_id'] . "' AND
            f.freegifts_start_date <= '" . date('Y-m-d') . "' AND
            f.freegifts_end_date >= '" . date('Y-m-d') . "' AND p.products_id NOT IN (" . $_SESSION['gift_exist'] . ")
        ORDER BY pd.products_name");

$freegifts = array();
while (!$freegifts_query->EOF) {
    $conditions = array();

    if ((!(int) $freegifts_query->fields['freegifts_type'] || (int) $freegifts_query->fields['freegifts_type'] == 2) && !$freegifts_query->fields['freegifts_threshold_categories']) {
        $conditions[] = array('text' => sprintf(TEXT_FREEGIFTS_SPEND, $currencies->format($freegifts_query->fields['freegifts_threshold'])));
    } else if ((!(int) $freegifts_query->fields['freegifts_type'] || (int) $freegifts_query->fields['freegifts_type'] == 2) && $freegifts_query->fields['freegifts_threshold_categories']) {
        $categories       = array();
        $categories_query = $db->Execute("SELECT categories_id, categories_name FROM " . TABLE_CATEGORIES_DESCRIPTION . " WHERE categories_id IN (" . $freegifts_query->fields['freegifts_threshold_categories'] . ") AND language_id = " . (int) $_SESSION['languages_id']);
        while (!$categories_query->EOF) {
            $categories[] = $categories_query->fields;
            $categories_query->MoveNext();
        }

        switch (count($categories)) {
            case 1:
                $conditions[] = array('text' => sprintf(TEXT_FREEGIFTS_SPEND_CATEGORY, $currencies->format($freegifts_query->fields['freegifts_threshold'])), 'categories' => $categories);
                break;
            default:
                $conditions[] = array('text' => sprintf(TEXT_FREEGIFTS_SPEND_CATEGORIES, $currencies->format($freegifts_query->fields['freegifts_threshold'])), 'categories' => $categories);
                break;
        }
    }

    if ((int) $freegifts_query->fields['freegifts_type'] == 1 || (int) $freegifts_query->fields['freegifts_type'] == 2) {
        $products = array();
        if (empty($freegifts_query->fields['freegifts_required_products'])) {
            $freegifts_query->fields['freegifts_required_products'] = 0;
        }

        $products_query = $db->Execute("SELECT products_id, products_name FROM " . TABLE_PRODUCTS_DESCRIPTION . " WHERE products_id IN (" . $freegifts_query->fields['freegifts_required_products'] . ") AND language_id = " . (int) $_SESSION['languages_id']);
        while (!$products_query->EOF) {
            $products[] = $products_query->fields;
            $products_query->MoveNext();
        }

        switch (count($categories)) {
            case 1:
                $conditions[] = array('text' => TEXT_FREEGIFTS_REQUIRED_PRODUCT, 'products' => $products);
                break;
            default:
                $conditions[] = array('text' => TEXT_FREEGIFTS_REQUIRED_PRODUCTS, 'products' => $products);
                break;
        }
    }

    $freegifts_query->fields['conditions'] = $conditions;
    $freegifts[]                           = $freegifts_query->fields;
    $freegifts_query->MoveNext();
}
