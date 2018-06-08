<?php
/**
 * Free Gifts for Zen-Cart
 * @link https://github.com/customscriptz/freegifts
 */

require 'includes/application_top.php';

/*************************************/
/***** Install Procedure - START *****/
/*************************************/

$db->Execute("
CREATE TABLE IF NOT EXISTS " . TABLE_FREEGIFTS . " (
  freegifts_id int(11) NOT NULL AUTO_INCREMENT,
  products_id int(11) NOT NULL,
  freegifts_threshold decimal(15,2) NOT NULL,
  freegifts_status int(1) NOT NULL DEFAULT '0',
  freegifts_start_date date NOT NULL DEFAULT '0001-01-01',
  freegifts_end_date date NOT NULL DEFAULT '0001-01-01',
  PRIMARY KEY (freegifts_id)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=0 ;
");

/* Run update routine */
$checkfields = $db->metaColumns(TABLE_FREEGIFTS);
if (!$checkfields['FREEGIFTS_ATTRIBUTES']->type 
    || !$checkfields['FREEGIFTS_REQUIRED_PRODUCTS']->type
    || !$checkfields['FREEGIFTS_THRESHOLD_CATEGORIES']->type
    || !$checkfields['FREEGIFTS_TYPE']->type) {
  
    if (!$checkfields['FREEGIFTS_ATTRIBUTES']->type) {
        $db->Execute("ALTER TABLE " . TABLE_FREEGIFTS . " ADD COLUMN freegifts_attributes varchar(255) NULL");
    }

    if (!$checkfields['FREEGIFTS_REQUIRED_PRODUCTS']->type) {
        $db->Execute("ALTER TABLE " . TABLE_FREEGIFTS . " ADD COLUMN freegifts_required_products varchar(255) NULL");
    }

    if (!$checkfields['FREEGIFTS_THRESHOLD_CATEGORIES']->type) {
        $db->Execute("ALTER TABLE " . TABLE_FREEGIFTS . " ADD COLUMN freegifts_threshold_categories varchar(255) NULL");
    }

    if (!$checkfields['FREEGIFTS_TYPE']->type) {
        $db->Execute("ALTER TABLE " . TABLE_FREEGIFTS . " ADD COLUMN freegifts_type tinyint(1) default 0");
    }

    //check if free gifts configuration group exist on CONFIGURATION GROUP table
    $group_id = $db->Execute("SELECT configuration_group_id AS group_id FROM " . TABLE_CONFIGURATION_GROUP . " WHERE configuration_group_title = 'Free Gifts'");
    if ($group_id->RecordCount() == 0) {
        $group_id_query = $db->Execute("SELECT configuration_group_id AS group_id FROM " . TABLE_CONFIGURATION_GROUP . " ORDER BY configuration_group_id DESC");
        $group_id       = $group_id_query->fields['group_id'] + 1;
        $setting        = $db->Execute("INSERT INTO " . TABLE_CONFIGURATION_GROUP . " (configuration_group_id, configuration_group_title, configuration_group_description, sort_order, visible) VALUES ('" . $group_id . "', 'Free Gifts', 'Configure Free Gifts Settings', '" . $group_id . "', '1')");
        $group_id       = $db->insert_ID();
    } else {
        $group_id = $group_id->fields['group_id'];
    }

    $freegifts_image_width                 = defined(GIFTS_IMAGE_WIDTH) ? GIFTS_IMAGE_WIDTH : '150';
    $freegifts_image_height                = defined(GIFTS_IMAGE_HEIGHT) ? GIFTS_IMAGE_HEIGHT : '150';
    $FREEGIFTS_SIDEBOX_COUNT               = defined(FREEGIFTS_SIDEBOX_COUNT) ? FREEGIFTS_SIDEBOX_COUNT : 3;
    $freegifts_remove_gifts_if_coupon_used = defined(FREEGIFTS_REMOVE_GIFTS_IF_COUPON_USED) ? FREEGIFTS_REMOVE_GIFTS_IF_COUPON_USED : 'True';

    $db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key IN ('FREEGIFTS_AUTO_ADD','FREEGIFTS_SHOW_MSG_IN_CART','FREEGIFTS_SHOW_PRODUCT_IMAGE')");

    $sort_order = 200;
    $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, set_function) values ('Sidebox Image Width',                 'GIFTS_IMAGE_WIDTH',            '" . $freegifts_image_width . "', 'The pixel width of heading images', '" . $group_id . "', " . $sort_order++ . ", now(), now(), NULL)");
    $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, set_function) values ('Sidebox Image Height',                'GIFTS_IMAGE_HEIGHT',           '" . $freegifts_image_height . "', 'The pixel height of heading images', '" . $group_id . "', " . $sort_order++ . ", now(), now(), NULL)");
    $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, set_function) VALUES ('Items to show on the sidebox',                    'FREEGIFTS_SIDEBOX_COUNT',           '" . $FREEGIFTS_SIDEBOX_COUNT . "', 'Set this to the number of items you want to show on the sidebox.', '" . $group_id . "', " . $sort_order++ . ", now(), now(), '')");
    $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, set_function) VALUES ('Remove Free Gift if Coupon is Used',      'FREEGIFTS_REMOVE_GIFTS_IF_COUPON_USED', '" . $freegifts_remove_gifts_if_coupon_used . "', 'If a coupon is used and the order total drop below the required to get the free gift, you can choose to remove the free gift or not.<br /><br />True: Remove product and redirect to Shopping Cart page.<br />False: Ignore the coupon and let the customer checkout.', '" . $group_id . "', " . $sort_order++ . ", now(), now(), 'zen_cfg_select_option(array(\'True\', \'False\'), ')");
}

/*************************************/
/****** Install Procedure - END ******/
/*************************************/

require DIR_WS_CLASSES . 'currencies.php';
$currencies = new currencies();

$action = (isset($_GET['action']) ? $_GET['action'] : '');

$db->Execute("UPDATE " . TABLE_FREEGIFTS . " SET freegifts_status = 0 WHERE freegifts_end_date < '" . date('Y-m-d') . "'");

$freegifts_types = array(
    TEXT_INFO_THRESHOLD,
    TEXT_INFO_REQUIRED_PRODUCTS,
    TEXT_INFO_THRESHOLD_REQUIRED_PRODUCTS,
);

$ajax = isset($_GET['ajax']) ? $_GET['ajax'] : '';
switch ($ajax) {
    // Products
    case 'products':
        $sql = "select p.products_id, pd.products_name, p.products_price, p.products_model, p.products_image, p.master_categories_id
                                from " . TABLE_PRODUCTS . " p
                                join " . TABLE_PRODUCTS_DESCRIPTION . " pd ON p.products_id = pd.products_id
                                WHERE pd.language_id = '" . (int) $_SESSION['languages_id'] . "'";

        if (isset($_SESSION['provider_id'])) {
            $sql = str_replace('WHERE', "WHERE p.manufacturers_id = " . $_SESSION['provider_id'] . " AND ", $sql);
        }

        if (isset($_GET['products_id'])) {
            $sql .= "AND pd.products_id IN (" . $_GET['products_id'] . ")";
        } else {
            $sql .= "AND (pd.products_name LIKE '%" . zen_db_input($_GET['q']) . "%')";
        }

        $sql .= " order by products_name";

        $products = $db->Execute($sql);

        $products_array = array();
        while (!$products->EOF) {
            $display_price = zen_get_products_base_price($products->fields['products_id']);

            if (strtoupper(substr($products->fields['products_model'], 0, 4)) != 'GIFT') {
                $products_array[] = array(
                    'id'         => $products->fields['products_id'],
                    'name'       => $products->fields['products_name'],
                    'path'       => zen_output_generated_category_path($products->fields['master_categories_id']),
                    'price'      => $currencies->format($display_price),
                    'model'      => $products->fields['products_model'],
                    'image'      => zen_image(DIR_WS_CATALOG_IMAGES . $products->fields['products_image'], '', SMALL_IMAGE_WIDTH, SMALL_IMAGE_HEIGHT),
                    'attributes' => isset($_GET['loadAttributes']) ? loadAttributes($products->fields['products_id']) : '',
                );
            }

            $products->MoveNext();
        }

        header('content-type: application/json');
        die(json_encode($products_array));

        break;
    // Categories
    case 'categories':

        $sql = "select c.categories_id, cd.categories_name, c.categories_image
                                from " . TABLE_CATEGORIES . " c
                                join " . TABLE_CATEGORIES_DESCRIPTION . " cd USING(categories_id)
                                WHERE cd.language_id = '" . (int) $_SESSION['languages_id'] . "' ";

        if (isset($_SESSION['provider_id']) && function_exists('get_manufacturers_data')) {
            $category = get_manufacturers_data($_SESSION['provider_id'], 'category_id');
            if ((int) $category) {
                $sql = str_replace('WHERE', "WHERE (c.parent_id = " . $category . " OR c.categories_id = $category) AND ", $sql);
            }
        }

        if (isset($_GET['categories_id'])) {
            $sql .= "AND c.categories_id IN (" . $_GET['categories_id'] . ")";
        } else {
            $sql .= "AND (cd.categories_name LIKE '%" . zen_db_input($_GET['q']) . "%')";
        }

        $sql .= " order by cd.categories_name";

        $categories = $db->Execute($sql);

        $json = array();
        while (!$categories->EOF) {
            $json[] = array(
                'id'    => $categories->fields['categories_id'],
                'name'  => $categories->fields['categories_name'],
                'path'  => zen_output_generated_category_path($categories->fields['categories_id']),
                'image' => zen_image(DIR_WS_CATALOG_IMAGES . $categories->fields['categories_image'], '', SMALL_IMAGE_WIDTH, SMALL_IMAGE_HEIGHT),
            );
            $categories->MoveNext();
        }

        header('content-type: application/json');
        die(json_encode($json));

        break;
}

if (zen_not_null($action)) {
    switch ($action) {
        case 'setflag':
            if (isset($_GET['gID'])) {
                $freegifts_id   = zen_db_prepare_input((int) $_GET['gID']);
                $sql_data_array = array('freegifts_status' => (int) $_GET['flag']);
                zen_db_perform(TABLE_FREEGIFTS, $sql_data_array, 'update', "freegifts_id = '" . (int) $freegifts_id . "'");
            }

            if (isset($_GET['pID'])) {
                $products_id    = zen_db_prepare_input((int) $_GET['pID']);
                $sql_data_array = array('products_status' => (int) $_GET['flag']);
                zen_db_perform(TABLE_PRODUCTS, $sql_data_array, 'update', "products_id = '" . (int) $products_id . "'");
            }

            zen_redirect(zen_href_link(FILENAME_FREEGIFTS, (isset($_GET['page']) ? 'page=' . $_GET['page'] . '&amp;' : '') . 'gID=' . $_GET['gID'], 'NONSSL'));
            break;
        case 'deleteconfirm':
            $freegifts_id = zen_db_prepare_input((int) $_GET['gID']);

            $db->Execute("DELETE FROM " . TABLE_FREEGIFTS . " WHERE freegifts_id = " . $freegifts_id);

            zen_redirect(zen_href_link(FILENAME_FREEGIFTS, (isset($_GET['page']) ? 'page=' . $_GET['page'] : ''), 'NONSSL'));
            break;
        case 'insert':
        case 'save':
            if (isset($_POST['freegifts_id'])) {
                $freegifts_id = (int) $_POST['freegifts_id'];
            }

            $products_id                 = (int) $_POST['products_id'];
            $freegifts_type              = (int) $_POST['freegifts_type'];
            $freegifts_required_products = '';
            if (!empty($_POST['freegifts_required_products'])) {
                $freegifts_required_products = zen_db_prepare_input(implode(',', $_POST['freegifts_required_products']));
            }

            $freegifts_threshold_categories = '';
            if (!empty($_POST['freegifts_threshold_categories'])) {
                $freegifts_threshold_categories = zen_db_prepare_input(implode(',', $_POST['freegifts_threshold_categories']));
            }

            $freegifts_threshold  = zen_db_prepare_input($_POST['freegifts_threshold']);
            $freegifts_start_date = ((zen_db_prepare_input($_POST['freegifts_start_date']) == '') ? date('Y-m-d') : zen_date_raw($_POST['freegifts_start_date']));
            $freegifts_end_date   = ((zen_db_prepare_input($_POST['freegifts_end_date']) == '') ? date('Y-m-d', strtotime("+1 day", time())) : zen_date_raw($_POST['freegifts_end_date']));

            $freegifts_attributes = zen_db_prepare_input($_POST['freegifts_attributes']);

            $sql_data_array = array(
                'products_id'                    => $products_id,
                'freegifts_type'                 => $freegifts_type,
                'freegifts_required_products'    => $freegifts_required_products,
                'freegifts_threshold'            => $freegifts_threshold,
                'freegifts_start_date'           => $freegifts_start_date,
                'freegifts_end_date'             => $freegifts_end_date,
                'freegifts_attributes'           => $freegifts_attributes,
                'freegifts_threshold_categories' => $freegifts_threshold_categories,
            );

            if ($action == 'insert') {
                zen_db_perform(TABLE_FREEGIFTS, $sql_data_array);
                $freegifts_id = zen_db_insert_id();
            } elseif ($action == 'save') {
                zen_db_perform(TABLE_FREEGIFTS, $sql_data_array, 'update', "freegifts_id = '" . (int) $freegifts_id . "'");
            }

            zen_redirect(zen_href_link(FILENAME_FREEGIFTS, (isset($_GET['page']) ? 'page=' . $_GET['page'] . '&amp;' : '') . 'gID=' . $freegifts_id));
            break;
        case 'loadAttributes':
            echo loadAttributes($_GET['pID']);
            die();
            break;
    }
}
?>
<!DOCTYPE html>
<html <?php echo HTML_PARAMS; ?>>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
    <title><?php echo TITLE; ?></title>
    <link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
    <link rel="stylesheet" type="text/css" href="includes/cssjsmenuhover.css" media="all" id="hoverJS">
    <link rel="stylesheet" type="text/css" href="includes/javascript/spiffyCal/spiffyCal_v2_1.css">
    <link rel="stylesheet" type="text/css" href="//cdnjs.cloudflare.com/ajax/libs/select2/3.5.2/select2.min.css">
    <script language="javascript" src="includes/menu.js"></script>
    <script language="javascript" src="includes/general.js"></script>
    <script language="JavaScript" src="includes/javascript/spiffyCal/spiffyCal_v2_1.js"></script>

    <script type="text/javascript">
        function init() {
            cssjsmenu('navbar');
            if (document.getElementById) {
                var kill = document.getElementById('hoverJS');
                kill.disabled = true;
            }
        }
    </script>
    <style>
        .bigdrop .select2-results {
            max-height: 400px;
        }
        .required-product-list li {
            margin-bottom: 4px;
        }

        label[for=required-products]:hover {
            cursor: pointer;
        }

        .hide {
            display: none;
        }

        fieldset legend {
            font-weight: bold;
        }
    </style>
</head>
<body onLoad="init()">
    <div id="spiffycalendar" class="text"></div>
    <!-- header //-->
    <?php require DIR_WS_INCLUDES . 'header.php';?>
    <!-- header_eof //-->

    <!-- body //-->
    <table border="0" width="100%" cellspacing="2" cellpadding="2">
        <tr>
            <!-- body_text //-->
            <td width="100%" valign="top">
                <table border="0" width="100%" cellspacing="0" cellpadding="2">

                    <tr>
                        <td>
                            <table border="0" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td class="pageHeading"><?php echo HEADING_TITLE; ?></td>
                                    <td class="pageHeading" align="right"><?php echo zen_draw_separator('pixel_trans.gif', 1, HEADING_IMAGE_HEIGHT); ?></td>
                                    <td class="smallText" align="right"></td>
                                    </form></tr>
                            </table>
                        </td>
                    </tr>
                    <?php
if ($action != 'new' && $action != 'edit') {
    ?>
                        <td align="center"><?php echo '<a href="' . zen_href_link(FILENAME_FREEGIFTS, ((isset($_GET['page']) && $_GET['page'] > 0) ? 'page=' . $_GET['page'] . '&' : '') . 'action=new') . '">' . zen_image_button('button_new_product.gif', IMAGE_NEW_PRODUCT) . '</a>'; ?></td>
                    <?php
}
?>
                    <?php
if (($action == 'new') || ($action == 'edit')) {
    $form_action = 'insert';
    if (($action == 'edit') && isset($_GET['gID'])) {
        $form_action = 'save';

        $product = $db->Execute("select *
                               from " . TABLE_FREEGIFTS . " f
                               left join " . TABLE_PRODUCTS_DESCRIPTION . " pd on (f.products_id = pd.products_id)
                               where pd.language_id = '" . (int) $_SESSION['languages_id'] . "'
                               and f.freegifts_id = '" . (int) $_GET['gID'] . "'");

        $gInfo = new objectInfo($product->fields);

    } else {
        $gInfo = new objectInfo(array());

        $freegifts_array = array();
        $sql             = "select products_id from " . TABLE_FREEGIFTS . " where 1=1 ";
        $gifts           = $db->Execute($sql);

        if ($gifts->RecordCount()) {
            while (!$gifts->EOF) {
                $freegifts_array[] = $gifts->fields['products_id'];
                $gifts->MoveNext();
            }
        }

        if (isset($_SESSION['provider_id'])) {
            $exclude = $db->Execute("select products_id from " . TABLE_PRODUCTS . " where manufacturers_id <> " . (int) $_SESSION['provider_id']);
            while (!$exclude->EOF) {
                $freegifts_array[] = $exclude->fields['products_id'];
                $exclude->MoveNext();
            }
        }

        $gift_vouchers = $db->Execute("select distinct p.products_id, p.products_model from " . TABLE_PRODUCTS . " p where p.products_model rlike '" . "GIFT" . "'");
        while (!$gift_vouchers->EOF) {
            if (substr($gift_vouchers->fields['products_model'], 0, 4) == 'GIFT') {
                $freegifts_array[] = $gift_vouchers->fields['products_id'];
            }
            $gift_vouchers->MoveNext();
        }

        // do not include things that cannot go in the cart
        $not_for_cart = $db->Execute("select p.products_id from " . TABLE_PRODUCTS . " p left join " . TABLE_PRODUCT_TYPES . " pt on p.products_type= pt.type_id where pt.allow_add_to_cart = 'N'");
        while (!$not_for_cart->EOF) {
            $freegifts_array[] = $not_for_cart->fields['products_id'];
            $not_for_cart->MoveNext();
        }
    }

    $sql = "select p.products_id, pd.products_name, p.products_price, p.products_model
                                from " . TABLE_PRODUCTS . " p
                                join " . TABLE_PRODUCTS_DESCRIPTION . " pd ON p.products_id = pd.products_id
                                WHERE pd.language_id = '" . (int) $_SESSION['languages_id'] . "'
                                order by products_name";

    if (isset($_SESSION['provider_id'])) {
        $sql = str_replace('WHERE', "WHERE p.manufacturers_id = " . $_SESSION['provider_id'] . " AND ", $sql);
    }

    $products = $db->Execute($sql);

    $products_array = array();
    while (!$products->EOF) {
        $display_price    = zen_get_products_base_price($products->fields['products_id']);
        $products_array[] = array('id' => $products->fields['products_id'], 'text' => $products->fields['products_name'] . ' (' . $currencies->format($display_price) . ') [' . $products->fields['products_model'] . '] - ID# ' . $products->fields['products_id']);
        $products->MoveNext();
    }

    $sql = "select c.categories_id, cd.categories_name
                                from " . TABLE_CATEGORIES . " c
                                join " . TABLE_CATEGORIES_DESCRIPTION . " cd USING(categories_id)
                                WHERE cd.language_id = '" . (int) $_SESSION['languages_id'] . "'
                                order by cd.categories_name";

    if (isset($_SESSION['provider_id']) && function_exists('get_manufacturers_data')) {
        $category = get_manufacturers_data($_SESSION['provider_id'], 'category_id');
        if ((int) $category) {
            $sql = str_replace('WHERE', "WHERE (c.parent_id = " . $category . " OR c.categories_id = $category) AND ", $sql);
        }
    }

    $categories = $db->Execute($sql);

    $categories_array = array();
    while (!$categories->EOF) {
        $categories_array[] = array('id' => $categories->fields['categories_id'], 'text' => $categories->fields['categories_name'] . ' - ID# ' . $categories->fields['categories_id']);
        $categories->MoveNext();
    }
    ?>
                        <script language="javascript">
                            var StartDate = new ctlSpiffyCalendarBox("StartDate", "freegifts", "freegifts_start_date", "btnDate1", "<?php echo (($gInfo->freegifts_start_date == '0001-01-01') ? '' : zen_date_short($gInfo->freegifts_start_date)); ?>", scBTNMODE_CUSTOMBLUE);
                            var EndDate = new ctlSpiffyCalendarBox("EndDate", "freegifts", "freegifts_end_date", "btnDate2", "<?php echo (($gInfo->freegifts_end_date == '0001-01-01') ? '' : zen_date_short($gInfo->freegifts_end_date)); ?>", scBTNMODE_CUSTOMBLUE);
                        </script>

                        <tr><?php echo zen_draw_form("freegifts", FILENAME_FREEGIFTS, (isset($_GET['page']) ? 'page=' . $_GET['page'] . '&amp;' : '') . 'action=' . $form_action);
    if ($form_action == 'save') {
        echo zen_draw_hidden_field('freegifts_id', $_GET['gID']);
    }
    ?>
                            <td><br>
                                <table border="0" cellspacing="0" cellpadding="2">
                                    <tr>
                                        <td class="main" valign="top">
                                            <strong><?php echo TABLE_HEADING_FREEGIFT_NAME; ?></strong>&nbsp;</td>
                                        <td class="main">
                                            <input type="hidden" name="products_id" id="select2-gift" class="bigdrop" style="width:600px" value="<?php echo $gInfo->products_id; ?>"/>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">
                                            <hr/>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="main">&nbsp;</td>
                                        <td class="main"><strong><?php echo TABLE_HEADING_ATTRIBUTES; ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td class="main">&nbsp;</td>
                                        <td class="main">
                                            <div id="attributes">
                                                <input type="hidden" name="freegifts_attributes" value="<?php echo $gInfo->freegifts_attributes; ?>"/>
                                                <table></table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">
                                            <hr/>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="main" style="width: 300px;">
                                            <strong><?php echo TEXT_INFO_TYPE; ?></strong>&nbsp;</td>
                                        <td class="main">
                                            <?php
foreach ($freegifts_types as $k => $v) {
        echo '<label>' . zen_draw_radio_field('freegifts_type', (string) $k, ((int) $gInfo->freegifts_type == $k ? true : false)) . '&nbsp;' . $v . '</label><br>';
    }
    ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">
                                            <br/>
                                        </td>
                                    </tr>

                                    <tr class="type-threshold hide">
                                        <td colspan="2">
                                            <fieldset>
                                                <legend><?php echo TABLE_HEADING_FREEGIFT_THRESHOLD; ?></legend>
                                                <table>
                                                    <tr>
                                                        <td class="main" valign="top" align="right">
                                                            <strong><?php echo TABLE_HEADING_FREEGIFT_AMOUNT; ?></strong>&nbsp;
                                                        </td>
                                                        <td class="main" valign="top"><?php echo zen_draw_input_field('freegifts_threshold', (isset($gInfo->freegifts_threshold) ? $gInfo->freegifts_threshold : ''), 'placeholder="0.00"'); ?></td>
                                                    </tr>

                                                    <tr>
                                                        <td class="main" valign="top" align="right">
                                                            <strong><?php echo TABLE_FORM_THRESHOLD_CATEGORY; ?></strong>&nbsp;
                                                        </td>
                                                        <td class="main" valign="top">
                                                            <input type="hidden" name="freegifts_threshold_categories[]" id="select2-categories" class="bigdrop" style="width:600px" value="<?php echo $gInfo->freegifts_threshold_categories; ?>"/>
                                                            <br/>
                                                            <em><?php echo TABLE_FORM_THRESHOLD_CATEGORY_TIP1; ?></em><br/>
                                                            <em><?php echo TABLE_FORM_THRESHOLD_CATEGORY_TIP2; ?></em>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </fieldset>
                                        </td>
                                    </tr>

                                    <tr class="type-products-required hide">
                                        <td colspan="2">
                                            <fieldset>
                                                <legend><?php echo TEXT_INFO_REQUIRED_PRODUCTS; ?></legend>
                                                <table>
                                                    <tr>
                                                        <td class="main" valign="top">
                                                            <strong><?php echo TABLE_FORM_PRODUCTS; ?></strong>&nbsp;
                                                        </td>
                                                        <td class="main" valign="top">
                                                            <input type="hidden" name="freegifts_required_products[]" id="select2-products-required" class="bigdrop" style="width:600px" value="<?php echo $gInfo->freegifts_required_products; ?>"/>
                                                            <br/>
                                                            <em><?php echo TABLE_FORM_PRODUCTS_TIP; ?></em>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </fieldset>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td colspan="2">
                                            <hr/>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="main">
                                            <strong><?php echo TABLE_HEADING_FREEGIFT_START_DATE; ?></strong>&nbsp;</td>
                                        <td class="main">
                                            <script language="javascript">StartDate.writeControl();
                                                StartDate.dateFormat = "<?php echo DATE_FORMAT_SPIFFYCAL; ?>";</script> <?php echo TABLE_HEADING_FREEGIFT_START_DATE_TIP; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">
                                            <hr/>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="main"><strong><?php echo TABLE_HEADING_FREEGIFT_END_DATE; ?></strong>&nbsp;
                                        </td>
                                        <td class="main">
                                            <script language="javascript">EndDate.writeControl();
                                                EndDate.dateFormat = "<?php echo DATE_FORMAT_SPIFFYCAL; ?>";</script> <?php echo TABLE_HEADING_FREEGIFT_END_DATE_TIP; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="main">&nbsp;</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <table border="0" width="100%" cellspacing="0" cellpadding="2">
                                    <tr>
                                        <td class="main" align="right" valign="top">
                                            <br><?php echo (($form_action == 'insert') ? zen_image_submit('button_insert.gif', IMAGE_INSERT) : zen_image_submit('button_update.gif', IMAGE_UPDATE)) . '&nbsp;&nbsp;&nbsp;<a href="' . zen_href_link(FILENAME_FREEGIFTS, 'page=' . $_GET['page']) . '">' . zen_image_button('button_cancel.gif', IMAGE_CANCEL) . '</a>'; ?>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                            </form></tr>
                    <?php
} else {
    ?>
                    <tr>
                        <td valign="top">
                            <table border="0" width="100%" cellspacing="0" cellpadding="2">
                                <tr class="dataTableHeadingRow">
                                    <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_PRODUCT_ID; ?></td>
                                    <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_PRODUCT_NAME; ?></td>
                                    <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_FREEGIFT_THRESHOLD; ?></td>
                                    <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_FREEGIFT_TYPE; ?></td>
                                    <td class="dataTableHeadingContent" align="center"><?php echo TABLE_HEADING_FREEGIFT_STATUS; ?></td>
                                    <td class="dataTableHeadingContent" align="center"><?php echo TABLE_HEADING_PRODUCT_STATUS; ?></td>
                                    <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_FREEGIFT_START_DATE; ?></td>
                                    <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_FREEGIFT_END_DATE; ?></td>
                                    <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_ACTION; ?>&nbsp;</td>
                                </tr>
                                <?php
$freegifts_query_raw = "SELECT * FROM " . TABLE_FREEGIFTS . " f JOIN " . TABLE_PRODUCTS . " p ON (f.products_id = p.products_id) JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON (f.products_id = pd.products_id) WHERE pd.language_id = '" . (int) $_SESSION['languages_id'] . "' order by f.freegifts_end_date DESC";

    if (isset($_SESSION['provider_id'])) {
        $freegifts_query_raw = str_replace('WHERE', "WHERE p.manufacturers_id = " . $_SESSION['provider_id'] . " AND ", $freegifts_query_raw);
    }

    $freegifts_split = new splitPageResults($_GET['page'], MAX_DISPLAY_SEARCH_RESULTS, $freegifts_query_raw, $freegifts_query_numrows);
    $gifts           = $db->Execute($freegifts_query_raw);
    while (!$gifts->EOF) {
        if ((!isset($_GET['gID']) || (isset($_GET['gID']) && ($_GET['gID'] == $gifts->fields['freegifts_id']))) && !isset($gInfo) && (substr($action, 0, 3) != 'new')) {
            $gInfo = new objectInfo($gifts->fields);
        }

        if (isset($gInfo) && is_object($gInfo) && ($gifts->fields['freegifts_id'] == $gInfo->freegifts_id)) {
            echo '              <tr id="defaultSelected" class="dataTableRowSelected">' . "\n";
        } else {
            echo '              <tr class="dataTableRow">' . "\n";
        }
        ?>
                <td class="dataTableContent"><?php echo $gifts->fields['products_id']; ?></td>
                <td class="dataTableContent"><?php echo $gifts->fields['products_name']; ?></td>
                <td class="dataTableContent"><?php echo ($gifts->fields['freegifts_type'] == 1 ? TEXT_NONE : $currencies->format($gifts->fields['freegifts_threshold'])); ?></td>
                <td class="dataTableContent"><?php echo $freegifts_types[(int) $gifts->fields['freegifts_type']]; ?></td>
                <td class="dataTableContent" align="center">
                    <?php
if ($gifts->fields['freegifts_status'] == '1') {
            echo '<a href="' . zen_href_link(FILENAME_FREEGIFTS, 'action=setflag&amp;flag=0&amp;gID=' . $gifts->fields['freegifts_id'] . '&page=' . $_GET['page'], 'NONSSL') . '">' . zen_image(DIR_WS_IMAGES . 'icon_green_on.gif', IMAGE_ICON_STATUS_ON) . '</a>';
        } else {
            echo '<a href="' . zen_href_link(FILENAME_FREEGIFTS, 'action=setflag&amp;flag=1&amp;gID=' . $gifts->fields['freegifts_id'] . '&page=' . $_GET['page'], 'NONSSL') . '">' . zen_image(DIR_WS_IMAGES . 'icon_red_on.gif', IMAGE_ICON_STATUS_OFF) . '</a>';
        }
        ?></td>
                <td class="dataTableContent" align="center">
                    <?php
if ($gifts->fields['products_status'] == '1') {
            echo '<a href="' . zen_href_link(FILENAME_FREEGIFTS, 'action=setflag&amp;flag=0&amp;pID=' . $gifts->fields['products_id'] . '&page=' . $_GET['page'], 'NONSSL') . '">' . zen_image(DIR_WS_IMAGES . 'icon_green_on.gif', IMAGE_ICON_STATUS_ON) . '</a>';
        } else {
            echo '<a href="' . zen_href_link(FILENAME_FREEGIFTS, 'action=setflag&amp;flag=1&amp;pID=' . $gifts->fields['products_id'] . '&page=' . $_GET['page'], 'NONSSL') . '">' . zen_image(DIR_WS_IMAGES . 'icon_red_on.gif', IMAGE_ICON_STATUS_OFF) . '</a>';
        }
        ?></td>
                <td class="dataTableContent" style="<?php echo ($gifts->fields['freegifts_start_date'] > date('Y-m-d') ? 'color:red' : ''); ?>"><?php echo date(DATE_FORMAT, strtotime($gifts->fields['freegifts_start_date'])); ?></td>
                <td class="dataTableContent" style="<?php echo ($gifts->fields['freegifts_end_date'] < date('Y-m-d') ? 'color:red' : ''); ?>"><?php echo date(DATE_FORMAT, strtotime($gifts->fields['freegifts_end_date'])); ?></td>
                <td class="dataTableContent" align="right">
                    <?php echo '<a href="' . zen_href_link(FILENAME_FREEGIFTS, 'page=' . $_GET['page'] . '&gID=' . $gifts->fields['freegifts_id'] . '&pID=' . $gifts->fields['products_id'] . '&action=edit') . '">' . zen_image(DIR_WS_IMAGES . 'icon_edit.gif', ICON_EDIT) . '</a>'; ?>
                                    <?php echo '<a href="' . zen_href_link(FILENAME_FREEGIFTS, 'page=' . $_GET['page'] . '&gID=' . $gifts->fields['freegifts_id'] . '&action=delete') . '">' . zen_image(DIR_WS_IMAGES . 'icon_delete.gif', ICON_DELETE) . '</a>'; ?>
                    &nbsp;</td>
                </tr>
            <?php
$gifts->MoveNext();
    }
    ?>
                                <tr>
                                    <td colspan="7">
                                        <table border="0" width="100%" cellspacing="0" cellpadding="2">
                                            <tr>
                                                <td class="smallText" valign="top"><?php echo $freegifts_split->display_count($freegifts_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, $_GET['page'], TEXT_DISPLAY_NUMBER_OF_FREEGIFTS); ?></td>
                                                <td class="smallText" align="right"><?php echo $freegifts_split->display_links($freegifts_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, MAX_DISPLAY_PAGE_LINKS, $_GET['page']); ?></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                        <?php
$heading  = array();
    $contents = array();

    switch ($action) {
        case 'delete':
            $heading[] = array('text' => '<b>' . TEXT_INFO_HEADING_DELETE_FREEGIFT . '</b>');

            $contents   = array('form' => zen_draw_form('freegifts', FILENAME_FREEGIFTS, 'page=' . $_GET['page'] . '&gID=' . $gInfo->freegifts_id . '&action=deleteconfirm'));
            $contents[] = array('text' => TEXT_INFO_DELETE_INTRO);
            $contents[] = array('text' => '<br><b>' . $gInfo->title . '</b>');
            $contents[] = array('align' => 'center', 'text' => '<br>' . zen_image_submit('button_delete.gif', IMAGE_DELETE) . '&nbsp;<a href="' . zen_href_link(FILENAME_FREEGIFTS, 'page=' . $_GET['page'] . '&gID=' . $gInfo->freegifts_id) . '">' . zen_image_button('button_cancel.gif', IMAGE_CANCEL) . '</a>');
            break;
    }

    if ((zen_not_null($heading)) && (zen_not_null($contents))) {
        echo '            <td width="25%" valign="top">' . "\n";

        $box = new box;
        echo $box->infoBox($heading, $contents);

        echo '            </td>' . "\n";
    }
}
?>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    </td>
    <!-- body_text_eof //-->
    </tr>
    </table>
    <!-- body_eof //-->

    <?php echo '<br /><div style="text-align: center">' . $module['name'] . ' v' . $module['version'] . ' developed by <a href="https://customscriptz.com" target="_blank">Custom Scriptz</a>';
echo !isset($_SESSION['provider_id']) ? ' | <a href="' . $module['main_page'] . '?action=licenseManager">License Manager</a></div>' : ''; ?>
    <!-- footer //-->
    <?php require DIR_WS_INCLUDES . 'footer.php';?>
    <!-- footer_eof //-->
    <br>

    <script language="JavaScript" src="//cdnjs.cloudflare.com/ajax/libs/jquery/2.0.3/jquery.min.js"></script>
    <script language="JavaScript" src="//cdnjs.cloudflare.com/ajax/libs/select2/3.5.2/select2.min.js"></script>
    <script type="text/javascript">
        function check_type() {
            var type = $('input[name=freegifts_type]:checked').val();
            console.log(type);
            $('.type-threshold, .type-products-required').addClass('hide');
            if (parseInt(type,0) === 1) {
              $('.type-products-required').removeClass('hide');
            } else if (parseInt(type, 0) == 2) {
              $('.type-threshold, .type-products-required').removeClass('hide');
            } else {
              $('.type-threshold').removeClass('hide');
            }
        }


        function formatResult(item) {
            return '<div>' +
                '<div style="width: 20%; float: left;">' + item.image + '</div>' +
                '<div style="width: ' + (item.price ? '4' : '8') + '0%; float: left;">' + item.name + (item.path ? '<br /><em style="font-style: italic">' + item.path + '</em>' : '') + '</div>' +
                (item.price ? '<div style="width: 20%; float: left;"><strong>' + item.price + '</strong></div>' : '') +
                (item.model ? '<div style="width: 20%; float: left;"><strong>' + item.model + '</strong></div>' : '') +
                '</div><div style="clear: both">&nbsp;</div>';
        }

        function formatSelection(item) {
            if (item[0]) {
                item = item[0];
            }

            if (item.attributes) {
                $('#attributes table').html(item.attributes);
            }
            return item.name;
        }

        $(function () {
            $("#select2-gift").select2({
                placeholder: "<?php echo addslashes(TEXT_INFO_CHOSEN_CLICK_TO_ADD); ?>",
                minimumInputLength: 1,
                ajax: {
                    url: "freegifts.php?ajax=products&loadAttributes=true",
                    dataType: 'json',
                    quietMillis: 250,
                    data: function (term, page) {
                        return {
                            q: term
                        };
                    },
                    results: function (data, page) { // parse the results into the format expected by Select2.
                        return {results: data};
                    },
                    cache: true
                },
                initSelection: function (element, callback) {
                    var id = $(element).val();
                    if (id !== "") {
                        $.ajax("freegifts.php?ajax=products&loadAttributes=true&products_id=" + id, {
                            dataType: "json"
                        }).done(function (data) {
                            callback(data);
                        });
                    }
                },
                formatResult: formatResult, // omitted for brevity, see the source of this page
                formatSelection: formatSelection,  // omitted for brevity, see the source of this page
                dropdownCssClass: "bigdrop",
                escapeMarkup: function (m) {
                    return m;
                }
            });

            $("#select2-categories").select2({
                multiple: true,
                placeholder: "<?php echo addslashes(TEXT_INFO_CHOSEN_CLICK_TO_ADD); ?>",
                minimumInputLength: 1,
                ajax: {
                    url: "freegifts.php?ajax=categories",
                    dataType: 'json',
                    quietMillis: 250,
                    data: function (term, page) {
                        return {
                            q: term
                        };
                    },
                    results: function (data, page) { // parse the results into the format expected by Select2.
                        return {results: data};
                    },
                    cache: true
                },
                initSelection: function (element, callback) {
                    var id = $(element).val();
                    if (id !== "") {
                        $.ajax("freegifts.php?ajax=categories&categories_id=" + id, {
                            dataType: "json"
                        }).done(function (data) {
                            callback(data);
                        });
                    }
                },
                formatResult: formatResult, // omitted for brevity, see the source of this page
                formatSelection: formatSelection,  // omitted for brevity, see the source of this page
                dropdownCssClass: "bigdrop",
                escapeMarkup: function (m) {
                    return m;
                }
            });


            $("#select2-products-required").select2({
                multiple: true,
                placeholder: "<?php echo addslashes(TEXT_INFO_CHOSEN_CLICK_TO_ADD); ?>",
                minimumInputLength: 1,
                ajax: {
                    url: "freegifts.php?ajax=products",
                    dataType: 'json',
                    quietMillis: 250,
                    data: function (term, page) {
                        return {
                            q: term
                        };
                    },
                    results: function (data, page) { // parse the results into the format expected by Select2.
                        return {results: data};
                    },
                    cache: true
                },
                initSelection: function (element, callback) {
                    // the input tag has a value attribute preloaded that points to a preselected repository's id
                    // this function resolves that id attribute to an object that select2 can render
                    // using its formatResult renderer - that way the repository name is shown preselected
                    var id = $(element).val();
                    if (id !== "") {
                        $.ajax("freegifts.php?ajax=products&products_id=" + id, {
                            dataType: "json"
                        }).done(function (data) {
                            callback(data);
                        });
                    }
                },
                formatResult: formatResult, // omitted for brevity, see the source of this page
                formatSelection: formatSelection,  // omitted for brevity, see the source of this page
                dropdownCssClass: "bigdrop",
                escapeMarkup: function (m) {
                    return m;
                }
            });

            $(document).on('click', 'input[name*=freegifts_attributes_check]', function () {
                var list = $(document).find('input[name*=freegifts_attributes_check]:checked');
                var attrs = [];
                $.each(list, function (i, elm) {
                    attrs.push($(elm).val());
                });

                $('input[name=freegifts_attributes]').val(attrs.join(','));
            });

            $('input[name=freegifts_type]').change(function () {
                check_type();
            });

            check_type();
        });
    </script>
</body>
</html>
<?php require DIR_WS_INCLUDES . 'application_bottom.php';?>
