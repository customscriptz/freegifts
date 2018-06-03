<?php
/**
 * Free Gifts for Zen-Cart
 * @link https://github.com/customscriptz/freegifts
 */

$date = date('Ymd', time());

$freegifts_sidebox_product_query = "
		SELECT *
		FROM " . TABLE_FREEGIFTS . " f
		LEFT JOIN " . TABLE_PRODUCTS . " p ON (f.products_id = p.products_id)
		LEFT JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON (f.products_id = pd.products_id)
		WHERE p.products_status = 1 AND
			f.freegifts_status = 1 AND
			pd.language_id = '" . (int) $_SESSION['languages_id'] . "' AND
			(f.freegifts_start_date <= '" . $date . "' AND f.freegifts_end_date >= '" . $date . "')";

$freegifts_sidebox_product = $db->Execute($freegifts_sidebox_product_query);
$freegifts_count           = $freegifts_sidebox_product->RecordCount();

if (!defined('FREEGIFTS_SIDEBOX_COUNT')) {
    define('FREEGIFTS_SIDEBOX_COUNT', 3);
}

$freegifts_sidebox_product_query = "
		SELECT *
		FROM " . TABLE_FREEGIFTS . " f
		LEFT JOIN " . TABLE_PRODUCTS . " p ON (f.products_id = p.products_id)
		LEFT JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON (f.products_id = pd.products_id)
		WHERE p.products_status = 1 AND
			f.freegifts_status = 1 AND
			pd.language_id = '" . (int) $_SESSION['languages_id'] . "' AND
			(f.freegifts_start_date <= '" . $date . "' AND f.freegifts_end_date >= '" . $date . "')
		ORDER BY RAND()
		LIMIT " . FREEGIFTS_SIDEBOX_COUNT;

$freegifts_sidebox_product = $db->Execute($freegifts_sidebox_product_query);

if ($freegifts_sidebox_product->RecordCount() > 0) {
    require $template->get_template_dir('tpl_freegifts.php', DIR_WS_TEMPLATE, $current_page_base, 'sideboxes') . '/tpl_freegifts.php';
    $title = '<label>' . BOX_HEADING_FREE_GIFTS . '</label>';
    require $template->get_template_dir($column_box_default, DIR_WS_TEMPLATE, $current_page_base, 'common') . '/' . $column_box_default;
}
