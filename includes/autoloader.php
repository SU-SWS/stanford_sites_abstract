<?php
/**
 * @file
 * This file is used prior to drupal loading all of the required resources and
 * prior to them being registered in the database. It is used to get and parse
 * the .info file of an installation profile and include the files[]
 * that are set in each one. This also looks for a "base" entry in that info
 * file and trys to load [base]/includes/autoloader.php for more files starting
 * a chain of includes allowing for all parent and child profiles to have all of
 * their includes loaded and ready for use when installing early on.
 */

// Call these directly.
require_once DRUPAL_ROOT . "/profiles/standard/standard.profile";
require_once DRUPAL_ROOT . "/profiles/stanford/stanford.install";

// The profiler library includes a call to _system_rebuild_module_alter() which calls an alter
// which in turn calls user_system_info_alter which has db_table_exists() in it. Drupal has not
// fully bootstrapped at this point. Lets just call the file we need and be done with it.

if (!function_exists('db_table_exists')) {
  require_once DRUPAL_ROOT . "/includes/database/database.inc";
}


/**
 * Parses a profile info file for files[] and includes them.
 *
 * Looks for a base and includes that profiles /includes/autoloader.php file
 * as well.
 *
 * @param string $profile_name
 *   the profile name
 */
function stanford_sites_abstract_autoloader($profile_name) {

  // Get parent resources.
  $parsed_file = drupal_parse_info_file(DRUPAL_ROOT . "/profiles/" . $profile_name . "/$profile_name.info");

  // If there is a base profile declared try to get it's autoloader.
  if (isset($parsed_file['base'])) {
    $file_path = DRUPAL_ROOT . "/profiles/" . $parsed_file['base'] . "/includes/autoloader.php";
    if (is_file($file_path)) {
      include_once $file_path;
    }
    else {
      // No autoloader. Grab .info file and load files anyhow.
      $base_profile_info = drupal_parse_info_file(DRUPAL_ROOT . "/profiles/" . $parsed_file['base'] . "/" . $parsed_file['base'] . ".info");
      foreach ($base_profile_info['files'] as $file) {
        include_once DRUPAL_ROOT . "/profiles/" . $parsed_file['base'] . "/" . $file;
      }
    }
  }

  // Loop through all the file and include them.
  if (isset($parsed_file['files'])) {
    foreach ($parsed_file['files'] as $file) {
      include_once DRUPAL_ROOT . "/profiles/" . $profile_name . "/" . $file;
    }
  }

}

// Call self.
stanford_sites_abstract_autoloader("stanford_sites_abstract");
