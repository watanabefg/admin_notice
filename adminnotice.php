<?php
// $Id$

/*
 * @file
 * Drupal Module: Admin Notice
 * notify system administrater's message to your admin page
 *
 * @author: Hironori Watanabe
 */

/**
 * Implementation of hook_help().
 */
function adminnotice_help($path, $arg) {
  switch ($path) {
    case 'admin/settings/adminnotice':
      return t('システム管理者からのお知らせを表示します。');
  }
}

/**
 * Implementation of hook_perm().
 */
function adminnotice_perm() {
  return array('administrater notice');
}

/**
 * Implementation of hook_menu().
 */
function adminnotice_menu() {
  $items['admin/settings/adminnotice'] = array(
    'title' => 'Admin Notice',
    'description' => 'システム管理者からのお知らせを表示しています。',
    'page callback' => 'admin_notice',
    'access callback' => 'user_access',
    'access arguments' => array('administer notice'),
    //'file' => 'googleanalytics.admin.inc',
    //'type' => MENU_NORMAL_ITEM,
  );

  return $items;
}

/**
 * 管理者ページでの表示
 */
function admin_notice() {
  $path = 'sites/all/modules/admin_notice/admin.notice.php';

  if (is_file($path)) {
    // ファイルの存在確認OK
    $fp = fopen($path, "r") or die("エラー:ファイルが開けません。");
    // 1行ずつ処理
    while (!feof($fp)) {
      $data .= fgets($fp, 4096);
    }
    fclose($fp);

    return $data;
  }
}

