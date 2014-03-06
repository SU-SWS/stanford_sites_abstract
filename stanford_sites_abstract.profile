<?php
/**
 * @file
 * @author Shea McKinney <sheamck@stanford.edu>
 *
 * This base installation profile alters the heck out of the installation
 * process. It should be used as it's own profile or as a parent "base" install
 * profile. This profile depends on both the stanford and standard installation
 * profiles and should be placed in /profiles/[profile_name]. If you plan on
 * using this profile as a "base" Do not alter anything in here.
 *
 * see: [create url] for more information about this.
 */

// Drupal does not load files[] configuration from profiles.info during
// installation. In order to ensure all of our resources are available we need
// to require them ourselves. This autoloader file looks into the .info file for
// a files[] declaration and includes those files.
require_once "includes/autoloader.php";

// We take advantage of some drush scripts but we need to support UI as well.
// Ensure no undeclared function calls by cheating the function declaration.
if (!function_exists('drush_log')) {
  function drush_log() {}
}

/**
 * Implements hook_install_tasks_alter().
 */
function stanford_sites_abstract_install_tasks_alter(&$tasks, &$install_state) {
  $profile = new JumpstartProfileAbstract();
  $profile->install_tasks_alter($tasks, $install_state);
  $profile->prepare_task_handlers($install_state);
}

/**
 * Get the class that is being installed and execute install modules on behalf
 * of the system so that we may include all profiles and not just one.
 * @param  [type] $install_state [description]
 * @return array a list of modules to install.
 */
function stanford_sites_abstract_install_profile_modules(&$install_state) {
  $profile_name = JumpstartProfileAbstract::get_active_profile();
  $profile = new $profile_name();
  return $profile->install_profile_modules($install_state);
}

/**
 * Call the class function that loops through all the profiles to ensure that
 * all the dependencies are met.
 * @param  [type] $install_state [description]
 * @return mixed html output if there are any errors or nothing if gravy.
 */
function stanford_sites_abstract_verify_requirements(&$install_state) {
  $profile_name = JumpstartProfileAbstract::get_active_profile();
  $profile = new $profile_name();
  return $profile->verify_requirements($install_state);
}

/**
 * Dependencies and requirements require that standard be installed before
 * everything else!
 * @param  [type] $install_state [description]
 * @return array a batch array of modules to install
 */
function stanford_sites_abstract_install_standard_profile_modules($install_state) {
  $install_state['parameters']['profile'] = 'standard';
  install_load_profile($install_state);

  // Clear the module list to get the new profile data and modules.
  drupal_static_reset('system_rebuild_module_data');
  variable_del('install_profile_modules');
  variable_set('install_profile_modules', array_unique($install_state['profile_info']['dependencies']));

  // Build dependant operations.
  $batch_info = install_profile_modules($install_state);
  unset($batch_info['finished']);
  return $batch_info;
}

/**
 * Run the standard installation profile.
 * @param  [type] $install_state [description]
 */
function stanford_sites_abstract_install_standard_profile(&$install_state) {

  // Before we continue with the install there seems to be blocks in the blocks
  // table. WTH? I know, like, really? Who is putting them there.

  // Turns out drupal_flush_all_caches calls block_clear_cache which builds out entries based on hook_block_info.
  // Whodathunkit?

  // Die blocks die!
  db_truncate('block')->execute();

}

/**
 * Implements form_install_configure_form_alter.
 * Calls all dependant alter including standard and stanford.
 * @param  [array] $form       [the form array]
 * @param  [array] $form_state [the form state array]
 */
function stanford_sites_abstract_form_install_configure_form_alter(&$form, &$form_state) {
  $profile_name = JumpstartProfileAbstract::get_active_profile();
  $profile = new $profile_name();
  $form = $profile->get_config_form($form, $form_state);
  return $form;
}

/**
 * Implements form_install_configure_form_alter_validate.
 * Calls all dependant validate functions on the installation config form.
 * @param  [array] $form       [the form array]
 * @param  [array] $form_state [the form state array]
 */
function stanford_sites_abstract_form_install_configure_form_alter_validate($form, &$form_state) {
  $profile_name = JumpstartProfileAbstract::get_active_profile();
  $profile = new $profile_name();
  $profile->get_config_form_validate($form, $form_state);
}

/**
 * Implements form_install_configure_form_alter_validate
 * Calls all dependant submit functions on the installation form.
 * @param  [array] $form       [the form array]
 * @param  [array] $form_state [the form state array]
 */
function stanford_sites_abstract_form_install_configure_form_alter_submit($form, &$form_state) {
  $profile_name = JumpstartProfileAbstract::get_active_profile();
  $profile = new $profile_name();
  $profile->get_config_form_submit($form, $form_state);
}





