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
      return t('Admin Notice はあなたの管理者画面にシステム管理者からのお知らせを表示します。');
  }
}

/**
 * Implementation of hook_theme().
 */
/*
 * テーマはいらない
function googleanalytics_theme() {
  return array(
    'googleanalytics_admin_custom_var_table' => array(
      'arguments' => array('form' => NULL),
    ),
  );
}
 */

/**
 * Implementation of hook_perm().
 */
function googleanalytics_perm() {
  return array('administer google analytics', 'opt-in or out of tracking', 'use PHP for tracking visibility');
}

/**
 * Implementation of hook_menu().
 */
function googleanalytics_menu() {
  $items['admin/settings/adminnotice'] = array(
    'title' => 'Admin Notice',
    'description' => 'システム管理者からのお知らせを表示しています。',
    'page callback' => 'admin_notice',
    'access callback' => 'user_access',
    'access arguments' => array('administer notice'),
    //'file' => 'googleanalytics.admin.inc',
    'type' => MENU_NORMAL_ITEM,
  );

  return $items;
}

/**
 * 管理者ページでの表示
 */
function admin_notice() {
  $path = 'sites/all/admin_notice/admin.notice.php';
  $filename = file_check_path($path); // $filename = sites/all/admin_notice, $path = admin.notice.php
  if (($real_path = file_check_location($path, $filename)) != 0) {
    // ファイルの存在確認OK
    $fp = fopen($filename.$path, "r") or die("エラー:ファイルが開けません。");
    // 1行ずつ処理
    while (!feof($fp)) {
      $data .= fgets($fp, 4096);
    }
    fclose($fp);

    return $data;
  }
}

/**
 * Implementation of hook_init().
 */
function googleanalytics_init() {
  global $user;

  $id = variable_get('googleanalytics_account', '');

  // 1. Check if the GA account number has a value.
  // 2. Track page views based on visibility value.
  // 3. Check if we should track the currently active user's role.
  if (!empty($id) && _googleanalytics_visibility_pages() && _googleanalytics_visibility_user($user)) {

    // Custom tracking.
    if (variable_get('googleanalytics_trackadsense', FALSE)) {
      drupal_add_js('window.google_analytics_uacct = '. drupal_to_js($id) .';', 'inline', 'header');
    }

    // Add link tracking.
    $link_settings = array();
    if ($track_outgoing = variable_get('googleanalytics_trackoutgoing', 1)) {
      $link_settings['trackOutgoing'] = $track_outgoing;
    }
    if ($track_mailto = variable_get('googleanalytics_trackmailto', 1)) {
      $link_settings['trackMailto'] = $track_mailto;
    }
    if (($track_download = variable_get('googleanalytics_trackfiles', 1)) && ($trackfiles_extensions = variable_get('googleanalytics_trackfiles_extensions', GOOGLEANALYTICS_TRACKFILES_EXTENSIONS))) {
      $link_settings['trackDownload'] = $track_download;
      $link_settings['trackDownloadExtensions'] = $trackfiles_extensions;
    }
    if ($track_outbound_as_pageview = variable_get('googleanalytics_trackoutboundaspageview', 0)) {
      $link_settings['trackOutboundAsPageview'] = $track_outbound_as_pageview;
    }
    if (!empty($link_settings)) {
      drupal_add_js(array('googleanalytics' => $link_settings), 'setting', 'header');
      drupal_add_js(drupal_get_path('module', 'googleanalytics') .'/googleanalytics.js', 'module', 'header');
    }
  }
}

/**
 * Implementation of hook_user().
 *
 * Allow users to decide if tracking code will be added to pages or not.
 */
function googleanalytics_user($type, $edit, &$account, $category = NULL) {
  switch ($type) {
    case 'form':
      if ($category == 'account' && user_access('opt-in or out of tracking') && ($custom = variable_get('googleanalytics_custom', 0)) != 0 && _googleanalytics_visibility_roles($account)) {
        $form['googleanalytics'] = array(
          '#type' => 'fieldset',
          '#title' => t('Google Analytics configuration'),
          '#weight' => 3,
          '#collapsible' => TRUE,
          '#tree' => TRUE,
        );

        switch ($custom) {
          case 1:
            $description = t('Users are tracked by default, but you are able to opt out.');
            break;

          case 2:
            $description = t('Users are <em>not</em> tracked by default, but you are able to opt in.');
            break;
        }

        // Disable tracking for visitors who have opted out from tracking via DNT (Do-Not-Track) header.
        $disabled = FALSE;
        if (variable_get('googleanalytics_privacy_donottrack', 1) && !empty($_SERVER['HTTP_DNT'])) {
          $disabled = TRUE;

          // Override settings value.
          $account->googleanalytics['custom'] = FALSE;

          $description .= '<span class="admin-disabled">';
          $description .= ' '. t('You have opted out from tracking via browser privacy settings.');
          $description .= '</span>';
        }

        $form['googleanalytics']['custom'] = array(
          '#type' => 'checkbox',
          '#title' => t('Enable user tracking'),
          '#description' => $description,
          '#default_value' => isset($account->googleanalytics['custom']) ? $account->googleanalytics['custom'] : ($custom == 1),
          '#disabled' => $disabled,
        );

        return $form;
      }
      break;
  }
}

/**
 * Implementation of hook_cron().
 */
function googleanalytics_cron() {
  // Regenerate the tracking code file every day.
  if (time() - variable_get('googleanalytics_last_cache', 0) >= 86400 && variable_get('googleanalytics_cache', 0)) {
    _googleanalytics_cache('http://www.google-analytics.com/ga.js', TRUE);
    variable_set('googleanalytics_last_cache', time());
  }
}

/**
 * Implementation of hook_preprocess_search_results().
 *
 * Collects the number of search results. It need to be noted, that this
 * function is not executed if the search result is empty.
 */
function googleanalytics_preprocess_search_results(&$variables) {
  // There is no search result $variable available that hold the number of items
  // found. But the pager item mumber can tell the number of search results.
  global $pager_total_items;

  drupal_add_js('window.googleanalytics_search_results = '. intval($pager_total_items[0]) .';', 'inline', 'header');
}

/**
 * Download/Synchronize/Cache tracking code file locally.
 *
 * @param $location
 *   The full URL to the external javascript file.
 * @param $sync_cached_file
 *   Synchronize tracking code and update if remote file have changed.
 *
 * @return mixed
 *   The path to the local javascript file on success, boolean FALSE on failure.
 */
function _googleanalytics_cache($location, $sync_cached_file = FALSE) {

  $path = file_create_path('googleanalytics');
  $file_destination = $path .'/'. basename($location);

  if (!file_exists($file_destination) || $sync_cached_file) {
    // Download the latest tracking code.
    $result = drupal_http_request($location);

    if ($result->code == 200) {
      if (file_exists($file_destination)) {
        // Synchronize tracking code and and replace local file if outdated.
        $data_hash_local = md5(file_get_contents($file_destination));
        $data_hash_remote = md5($result->data);
        // Check that the files directory is writable.
        if ($data_hash_local != $data_hash_remote && file_check_directory($path)) {
          // Save updated tracking code file to disk.
          file_save_data($result->data, $file_destination, FILE_EXISTS_REPLACE);
          watchdog('googleanalytics', 'Locally cached tracking code file has been updated.', array(), WATCHDOG_INFO);

          // Change query-strings on css/js files to enforce reload for all users.
          _drupal_flush_css_js();
        }
      }
      else {
        // Check that the files directory is writable.
        if (file_check_directory($path, FILE_CREATE_DIRECTORY)) {
          // There is no need to flush JS here as core refreshes JS caches
          // automatically, if new files are added.
          file_save_data($result->data, $file_destination, FILE_EXISTS_REPLACE);
          watchdog('googleanalytics', 'Locally cached tracking code file has been saved.', array(), WATCHDOG_INFO);

          // Return the local JS file path.
          return base_path() . $file_destination;
        }
      }
    }
  }
  else {
    // Return the local JS file path.
    return base_path() . $file_destination;
  }
}

/**
 * Delete cached files and directory.
 */
function googleanalytics_clear_js_cache() {
  $path = file_create_path('googleanalytics');
  if (file_check_directory($path)) {
    file_scan_directory($path, '.*', array('.', '..', 'CVS'), 'file_delete', TRUE);
    rmdir($path);

    // Change query-strings on css/js files to enforce reload for all users.
    _drupal_flush_css_js();

    watchdog('googleanalytics', 'Local cache has been purged.', array(), WATCHDOG_INFO);
  }
}

/**
 * Tracking visibility check for an user object.
 *
 * @param $account
 *   A user object containing an array of roles to check.
 *
 * @return boolean
 *   A decision on if the current user is being tracked by Google Analytics.
 */
function _googleanalytics_visibility_user($account) {

  $enabled = FALSE;

  // Is current user a member of a role that should be tracked?
  if (_googleanalytics_visibility_header($account) && _googleanalytics_visibility_roles($account)) {

    // Use the user's block visibility setting, if necessary.
    if (($custom = variable_get('googleanalytics_custom', 0)) != 0) {
      if ($account->uid && isset($account->googleanalytics['custom'])) {
        $enabled = $account->googleanalytics['custom'];
      }
      else {
        $enabled = ($custom == 1);
      }
    }
    else {
      $enabled = TRUE;
    }
  }

  return $enabled;
}

/**
 * Based on visibility setting this function returns TRUE if GA code should
 * be added for the current role and otherwise FALSE.
 */
function _googleanalytics_visibility_roles($account) {

  $visibility = variable_get('googleanalytics_visibility_roles', 0);
  $enabled    = $visibility;
  $roles      = variable_get('googleanalytics_roles', array());

  if (array_sum($roles) > 0) {
    // One or more roles are selected.
    foreach (array_keys($account->roles) as $rid) {
      // Is the current user a member of one of these roles?
      if (isset($roles[$rid]) && $rid == $roles[$rid]) {
        // Current user is a member of a role that should be tracked/excluded from tracking.
        $enabled = !$visibility;
        break;
      }
    }
  }
  else {
    // No role is selected for tracking, therefore all roles should be tracked.
    $enabled = TRUE;
  }

  return $enabled;
}

