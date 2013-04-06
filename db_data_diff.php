#!/usr/bin/php -q
<?php
/**
 * DbDataDiff実行ファイル
 * 引数を基にDBデータの差分を出力するするスクリプト
 *
 * @package		DbDataDiff
 * @author		setsuki (yukicon)
 * @copyright	Susugi Ningyoukan
 * @license		BSD
 **/

// ==================================================
// DB接続の準備
// ==================================================

$options = getopt('u:p:h:P:jm');

if (isset($options['m'])) {
	print "require option table names ex:./db_data_diff.php db1.table1 db2.table2\n";
	print "-u user      [optional]mysql user\n";
	print "-p pass      [optional]mysql pass\n";
	print "-h host      [optional]memcached protcol host\n";
	print "-P port      [optional]memcached protcol port\n";
	print "-j json      [optional]output json format string\n";
	exit;
}

$db_host = 'localhost';
$db_port = 3306;
$db_user = 'root';
$db_pass = 'root';
$output_json_flg = false;
if (isset($options['h'])) {
	$db_host = $options['h'];
}
if (isset($options['P'])) {
	$db_port = $options['P'];
}
if (isset($options['u'])) {
	$db_user = $options['u'];
}
if (isset($options['p'])) {
	$db_pass = $options['p'];
}
if (isset($options['j'])) {
	$output_json_flg = true;
}

// 引数からテーブル名を取得
$next_ignore_flg = true;
$table_list = array();
foreach ($argv as $key => $val) {
	if ($next_ignore_flg) {
		// フラグが有効ならフラグを落として次へ
		$next_ignore_flg = false;
		continue;
	}
	if (0 === strpos($val, '-')) {
		// -から始まるものはオプションとして無視する
		if (0 !== strpos($val, '-j')) {
			// -jでないなら次のものを含めて無視するようにする
			$next_ignore_flg = true;
		}
		continue;
	}
	$table_list[] = $val;
}

if (2 > count($table_list)) {
	print "[ERROR] empty set table names\n";
	print "Manual for (-m)\n";
	exit;
}

$table1 = $table_list[0];
$table2 = $table_list[1];

$ignore_cols = array('update_dt', 'create_dt');

$attribute = array(
	PDO::ATTR_PERSISTENT => false,
	PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . 'utf8',
	PDO::ATTR_EMULATE_PREPARES => true,
	PDO::ATTR_TIMEOUT => 30
);

// DBに接続する
$db_dsn = sprintf('%s:host=%s;port=%s', 'mysql', $db_host, $db_port);
$db = new PDO($db_dsn, $db_user, $db_pass, $attribute);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);	// エラー時はExceptionを投げる


// ==================================================
// メイン処理
// ==================================================

// カラム情報を取得
$col_info_arr = fetchAll($db, 'SHOW FULL COLUMNS FROM ' . $table1);

// 比較対象のカラムと主キーをまとめる
$all_cols = array();
$primary_cols = array();
foreach ($col_info_arr as $col_info) {
	if (!in_array($col_info['Field'], $ignore_cols)) {
		// 除外カラムでない場合
		$all_cols[] = $col_info['Field'];
	}
	if ('PRI' == $col_info['Key']) {
		// 主キーの場合
		$primary_cols[] = $col_info['Field'];
	}
}

$all_diff1 = diffRows($db, $table1, $table2, $all_cols, $all_cols);
$exist_diff1 = diffRows($db, $table1, $table2, $all_cols, $primary_cols);
$all_diff2 = diffRows($db, $table2, $table1, $all_cols, $all_cols);
$exist_diff2 = diffRows($db, $table2, $table1, $all_cols, $primary_cols);

if (empty($all_diff1) and empty($all_diff2)) {
	// 差分が無い場合は何も出力せずに終了
	exit;
}

// table1 にだけ存在するものを出力
if (0 < count($exist_diff1)) {
	echo "%%eo1%% exists only {$table1} ----------------\n";
	echoRows($exist_diff1, $output_json_flg);
}
// table2 にだけ存在するものを出力
if (0 < count($exist_diff2)) {
	echo "%%eo2%% exists only {$table2} ----------------\n";
	echoRows($exist_diff2, $output_json_flg);
}

// 更新のあったものを出力
if (count($exist_diff1) < count($all_diff1)) {
	echo "%%upd%% updated {$table1} -> {$table2} ----------------\n";
	$update_diff_rows = array();
	
	foreach ($all_diff1 as $diff_info) {
		if (in_array($diff_info, $exist_diff1)) {
			// 更新でなく追加されたものなら出力済みなので次へ
			continue;
		}
		
		// table2から同じキーのデータを探す
		$diff_info2 = array();
		foreach ($all_diff2 as $target_diff_info) {
			$same_key_flg = true;
			foreach ($primary_cols as $primary_col_name) {
				// 同じ主キーのものを捜す
				if ($diff_info[$primary_col_name] != $target_diff_info[$primary_col_name]) {
					// 違う値ならフラグを落として抜ける
					$same_key_flg = false;
					break;
				}
			}
			if ($same_key_flg) {
				// キーが一致したものが見つかった場合
				$diff_info2 = $target_diff_info;
				break;
			}
		}
		
		// 違いのあるカラムをまとめる
		$primary_data = array();
		$update_diff_data = array();

		if ($output_json_flg) {
			// jsonで出力する場合
			foreach ($diff_info as $key => $val) {
				if (in_array($key, $primary_cols)) {
					$primary_data[$key] = $val;
				} 
				if ($val != $diff_info2[$key]) {
					$update_diff_data[$key]['old'] = $val;
					$update_diff_data[$key]['new'] = $diff_info2[$key];
				}
			}
			
			$update_diff_rows[] = array('key' => $primary_data, 'update' => $update_diff_data);
		} else {
			// 人が見やすい形でそのまま出力する場合
			foreach ($diff_info as $key => $val) {
				if (in_array($key, $primary_cols)) {
					$primary_data[] = "{$key}:{$val}";
				} 
				if ($val != $diff_info2[$key]) {
					$update_diff_data[] = "{$key}:{$val}->{$diff_info2[$key]}";
				}
			}
			
			$update_diff_rows[] = array('key' => sprintf('[%s]', implode(' ', $primary_data)), 'update' => sprintf('[%s]', implode(' ', $update_diff_data)));
		}
	}
	
	echoRows($update_diff_rows, $output_json_flg);
}


// ==================================================
// ローカル関数
// ==================================================
function fetchAll($db, $sql_str, $sql_param = array())
{
	// SQLを実行
	$stmt = $db->prepare($sql_str);
	$stmt->execute($sql_param);
	
	return $stmt->fetchAll($fetch_type = PDO::FETCH_ASSOC);
}



function diffRows($db, $base_table, $target_table, $select_cols, $check_cols)
{
	$sql_str = 'SELECT ' . implode(',', $select_cols) . ' FROM ' . $base_table;
	
	$where_arr = array();
	foreach ($check_cols as $col_name) {
		// NULL 同士で引っかからないようにNULL - 安全等価の演算をする
		$where_arr[] = "{$base_table}.{$col_name} <=> {$target_table}.{$col_name}";
	}
	$where_str = ' WHERE ' . implode(' AND ', $where_arr);
	
	$sql_str .= " WHERE NOT EXISTS ( SELECT * FROM {$target_table} {$where_str} )";
	
	return fetchAll($db, $sql_str);
}



function echoRows($row_info_arr, $json_flg)
{
	if ($json_flg) {
		// jsonで出力する場合
		foreach ($row_info_arr as $row_info) {
			echo json_encode($row_info) . "\n";
		}
	} else {
		// 人が見やすい形でそのまま出力する場合
		foreach ($row_info_arr as $row_info) {
			$echo_arr = array();
			foreach ($row_info as $key => $val) {
				$echo_arr[] =  "{$key}:{$val}";
			}
			echo implode(' ', $echo_arr) . "\n";
		}
	}
}
