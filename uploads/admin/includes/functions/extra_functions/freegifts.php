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
									pd.language_id = '" . (int) $_SESSION['languages_id'] . "' AND
									f.freegifts_start_date <= Now() AND
									f.freegifts_end_date >= Now() AND
									f.products_id = " . (int) $products_id);

    if ($freegifts->fields['gifts'] == 1) {
        return true;
    }

    return false;
}

function loadAttributes($products_id)
{
    global $db;

    $sql = "SELECT pa.products_attributes_id, po.products_options_name, pov.products_options_values_name, f.freegifts_attributes
			FROM " . TABLE_PRODUCTS_ATTRIBUTES . " pa
			JOIN " . TABLE_PRODUCTS_OPTIONS . " po ON pa.options_id = po.products_options_id
			JOIN " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov ON pa.options_values_id = pov.products_options_values_id
			LEFT JOIN " . TABLE_FREEGIFTS . " f ON f.products_id = pa.products_id
			WHERE pa.products_id = '" . $products_id . "'
			ORDER BY LPAD(po.products_options_sort_order,11,'0'), LPAD(pa.options_id,11,'0'), LPAD(pa.products_options_sort_order,11,'0')";
    $attributes = $db->Execute($sql);

    $html = '';
    while (!$attributes->EOF) {
        $selected = explode(',', $attributes->fields['freegifts_attributes']);

        $html .= '<tr>';
        $html .= '<td>' . zen_draw_checkbox_field('freegifts_attributes_check[]', $attributes->fields['products_attributes_id'], (in_array($attributes->fields['products_attributes_id'], $selected) ? true : false), '', 'id="' . $attributes->fields['products_attributes_id'] . '"') . '</td>';
        $html .= '<td><label for="' . $attributes->fields['products_attributes_id'] . '">' . $attributes->fields['products_options_name'] . ': ' . $attributes->fields['products_options_values_name'] . '</label></td>';
        $html .= '</tr>' . "\n";

        $attributes->MoveNext();
    }

    if (!$html) {
        $html = '<tr><td colspan="2">' . TEXT_INFO_NO_ATTRIBUTES . '</td></tr>';
    }

    return $html;
}
