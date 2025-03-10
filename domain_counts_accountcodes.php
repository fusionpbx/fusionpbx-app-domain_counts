<?php
/*
	FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Portions created by the Initial Developer are Copyright (C) 2008-2025
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	KonradSC <konrd@yahoo.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//include the class
	require_once "resources/check_auth.php";

//check permissions
	require_once "resources/check_auth.php";
	if (permission_exists('domain_counts_view')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//get the settings
	$settings = new settings(['database' => $database, 'domain_uuid' => $_SESSION['domain_uuid'] ?? '', 'user_uuid' => $_SESSION['user_uuid'] ?? '']);

//get the http values and set them as variables
	$domain_uuid = check_str($_GET["id"]);
	$order_by = check_str($_GET["order_by"]);
	$order = check_str($_GET["order"]);

//handle search term
	$search = check_str($_GET["search"]);
	if (strlen($search) > 0) {
		$sql_mod = "and accountcode ILIKE '%".$search."%' ";
	}
	if (strlen($order_by) < 1) {
		$order_by = "accountcode";
		$order = "ASC";
	}

//get total accountcode count from the database
	$sql = "select count(DISTINCT accountcode) as num_rows from v_extensions where domain_uuid = '".$domain_uuid."' ".$sql_mod." ";
	$database = new database;
	$numeric_accountcodes = $database->select($sql, null, 'column');
	$total_accountcodes = $numeric_accountcodes;
	unset($sql);

//prepare to page the results
	$rows_per_page = ($_SESSION['domain']['paging']['numeric'] != '') ? $_SESSION['domain']['paging']['numeric'] : 50;
	$param = "&search=".$search."&id=".$domain_uuid."&order_by=".$order_by."&order=".$order;
	if (!isset($_GET['page'])) { $_GET['page'] = 0; }
	$_GET['page'] = check_str($_GET['page']);
	list($paging_controls_mini, $rows_per_page, $var_3) = paging($total_accountcodes, $param, $rows_per_page, true); //top
	list($paging_controls, $rows_per_page, $var_3) = paging($total_accountcodes, $param, $rows_per_page); //bottom
	$offset = $rows_per_page * $_GET['page'];

//get the domain name from the database
	$sql = "SELECT domain_name  \n";
	$sql .= "FROM v_domains \n";
	$sql .= "WHERE domain_uuid = '$domain_uuid' \n";
	$database = new database;
	$result = $database->select($sql, null);
	$domain_result = $database->result;
	unset($database,$result);

//get all the accountcodes from the database
	$sql = "SELECT accountcode, count(*) AS count  \n";
	$sql .= "FROM v_extensions \n";
	$sql .= "WHERE domain_uuid = '$domain_uuid' and accountcode is not null \n";
	$sql .= $sql_mod; //add search mod from above
	$sql .= "GROUP BY accountcode ";
	$sql .= "ORDER BY ".$order_by." ".$order." \n";
	$sql .= "limit $rows_per_page offset $offset ";
	$database = new database;
	$directory = $database->select($sql, null);
	unset($database);

//set the http header
	if ($_REQUEST['type'] == "csv") {

		//set the headers
			header('Content-type: application/octet-binary');
			header("Content-Disposition: attachment; filename='".$domain_result[0]['domain_name']."_accountcodes_" . date("Y-m-d") . ".csv'");

		//show the column names on the first line
			$z = 0;
			foreach($directory[1] as $key => $val) {
				if ($z == 0) {
					echo '"'.$key.'"';
				}
				else {
					echo ',"'.$key.'"';
				}
				$z++;
			}
			echo "\n";

		//add the values to the csv
			$x = 0;
			foreach($directory as $codes) {
				$z = 0;
				foreach($codes as $key => $val) {
					if ($z == 0) {
						echo '"'.$directory[$x][$key].'"';
					}
					else {
						echo ',"'.$directory[$x][$key].'"';
					}
					$z++;
				}
				echo "\n";
				$x++;
			}
			exit;
	}

//additional includes
	require_once "resources/header.php";
	$document['title'] = $text['title-domain_counts_accountcodes'];

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['header-domain_counts_accountcodes'].":</b> ".$domain_result[0]['domain_name']." <div class='count'>".number_format($numeric_accountcodes)."</div></div>\n";
	echo "	<div class='actions'>\n";
	echo "		<form method='get' action=''>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$_SESSION['theme']['button_icon_back'],'id'=>'btn_back','style'=>'margin-right: 15px;','onclick'=>"window.location='domain_counts.php'"]);
	echo button::create(['type'=>'button','label'=>$text['button-export'],'icon'=>$settings->get('theme', 'button_icon_export'),'style'=>'margin-right: 15px;','onclick'=>"window.location='domain_counts_accountcodes.php?".(!empty($_SERVER["QUERY_STRING"]) ? $_SERVER["QUERY_STRING"]."&type=csv';" : "type=csv';")]);
	echo "			<input type='text' class='txt' style='width: 150px' name='search' id='search' value=\"".escape($search)."\" placeholder=\"".$text['label-search']."\" onkeydown=''>";
	echo button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search']);
	echo "			<input type='hidden' name='id' value='".escape($domain_uuid)."' />";

#	if ($paging_controls_mini != '') {
#		echo 			"<span style='margin-left: 15px;'>".$paging_controls_mini."</span>\n";
#	}
	echo "		</form>\n";
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo $text['description-domain_counts_accountcodes']."\n";
	echo "<br /><br />\n";

	echo "<form name='frm' method='post' action='domain_counts_delete.php'>\n";

	echo "<div class='card'>\n";
	echo "<table class='tr_hover' width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
	echo "<tr>\n";
	echo th_order_by('acountcode', $text['label-accountcode'], $order_by, $order);
	echo th_order_by('count', $text['label-count'], $order_by,$order);
	echo "</tr>\n";

	if (isset($directory)) foreach ($directory as $key => $row) {
		echo "	<td valign='top' class='".$row_style[$c]."'>".escape($row['accountcode'])."</td>\n";
		echo "	<td valign='top' class='row_stylebg' width='75%'>".escape($row['count'])."&nbsp;</td>\n";
		echo "	</tr>\n";
		$c = ($c==0) ? 1 : 0;
	}

	echo "</table>";
	echo "</div>\n";

	echo "</form>";

//show the footer
	require_once "resources/footer.php";
?>
