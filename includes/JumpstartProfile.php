<?php
/**
 * @file
 * @author Shea McKinney <sheamck@stanford.edu>
 * Default Installation Class for Jumpstart. Provides the base functionality
 * and a large degree of alterations for the installation profile. See README.md
 * for more information.
 *
 */

/**
 * JumpStart Installation Profile Class
 */
abstract class JumpstartProfile {

  // Profile Installation tree in order from (first) top most parent to
  // (last) bottom most child.
  private $tree;
  // Variable to trigger wether we are on sites environment or not.
  private $is_sites_environment;
  // Loader call once.
  private static $called_once = FALSE;
  // Static var to hold active profile class name.
  static public $active_class = "JumpstartProfile";

  // ///////////////////////////////////////////////////////////////////////////
  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Construction Class.
   */
  public function __construct() {
    $class = get_called_class();
    JumpstartProfile::set_active_profile($class);
  }

  // ///////////////////////////////////////////////////////////////////////////
  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Returns all of the tree's install tasks in order from (first) top most
   * parent to (last) bottom most child.
   * @return array an array of install tasks.
   */
  abstract public function get_install_tasks(&$install_state);

  /**
   * Implements hook_form_alter()
   * Modifies and alters the configuration form.
   * @return array the form array
   */
  abstract public function get_config_form(&$form, &$form_state);


  /**
   * Implements hook_form_validate()
   * Only on the installation configuration form.
   */
  public function get_config_form_validate($form, &$form_state) {
    // Empty placeholder as not needed always.
  }

  /**
   * Implements hook_form_submit()
   * Only on the installation configuration form.
   */
  public function get_config_form_submit($form, &$form_state) {
    // Empty placeholder as not needed always.
  }

  // ///////////////////////////////////////////////////////////////////////////
  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Returns an array of parsed info files that contain the base key.
   * @return array [profile_name] => array [parsed info file]
   */
  public function get_jumpstart_profiles() {
    // Go find sub profiles!
    $profiles_dir = DRUPAL_ROOT . "/profiles";
    $info_files = file_scan_directory($profiles_dir, '/.*\.info$/', array('key' => 'name'));

    // Loop through each of the .info files and parse them.
    foreach ($info_files as $name => $obj) {
      $info = drupal_parse_info_file($obj->uri);

      // If the profile has a base configuration then keep it.
      if (isset($info['base']) && !empty($info['base'])) {
        $info_files[$name] = $info;
      }
      else {
        // If no base then we unset it.
        unset($info_files[$name]);
      }
    }

    return $info_files;
  }

  // ---------------------------------------------------------------------------

  /**
   * Implements hook_install_tasks_alter()
   * Heavily alters the installation process. Overrides core tasks, adds new
   * tasks and re-arrages the order of execution.
   */
  public function install_tasks_alter(&$tasks, &$install_state) {

    // Throw up if not everything is provided.
    if (empty($tasks) || empty($install_state)) {
      throw new Exception("Install Tasks Alter Requires both tasks and install_state variables");
    }

    // Alter the display name of profile task.
    $tasks['install_profile_modules']['display_name'] = "Install Common Assets";

    // Alter the display name of task.
    $tasks['install_configure_form']['display_name'] = "Configuration";

    // Take over the module installation task so we can run it on all profiles.
    $tasks['install_profile_modules']['function'] = "stanford_sites_abstract_install_profile_modules";

    // Override the requirements task so we may check all profiles.
    $tasks['install_verify_requirements']['function'] = "stanford_sites_abstract_verify_requirements";

    // The Stanford profile requires that the standard profile be installed so lets
    // do that prior to installing the other dependencies.

    // Add install standard profile modules as installation task before
    // other tasks.
    $tasks['install_standard_profile_modules'] = array(
      'display_name' => st('Install Standard Assets.'),
      'display' => TRUE,
      'type' => 'batch',
      'function' => 'stanford_sites_abstract_install_standard_profile_modules',
      'run' => INSTALL_TASK_RUN_IF_NOT_COMPLETED,
    );

    // Run the _install() function in standard before we start adding in new
    // modules and tasks.
    $tasks['install_standard_profile'] = array(
      'display_name' => st('Execute Install'),
      'display' => FALSE,
      'type' => 'normal',
      'function' => 'stanford_sites_abstract_install_standard_profile',
      'run' => INSTALL_TASK_RUN_IF_NOT_COMPLETED,
    );

    // Alter the order of running of all the things!
    $new_order = array();
    $new_order['install_select_profile'] = array();
    $new_order['install_select_locale'] = array();
    $new_order['install_verify_requirements'] = array();
    $new_order['install_settings_form'] = array();
    $new_order['install_system_module'] = array();
    $new_order['install_bootstrap_full'] = array();
    $new_order['install_standard_profile_modules'] = array();
    $new_order['install_standard_profile'] = array();
    $tasks = array_merge($new_order, $tasks);

  }

  // ---------------------------------------------------------------------------

  /**
   * Overrides core install_verify_requirements so that we can ensure all
   * profiles meet their requirements.
   * @param  array $install_state the current installation state.
   * @return string [html]  a HTML string with an error report.
   */
  public function verify_requirements($install_state) {

    // Gather all profiles that need to be installed in order from top most parent
    // to bottom most child.
    $tree = $this->get_profile_tree($install_state['parameters']['profile']);
    $report = '';

    // Loop through all the profiles and check requirements.
    foreach ($tree as $weight => $profile) {
      $install_state['parameters']['profile'] = $profile['machine_name'];
      $install_state['profile'] = $profile;
      $info_file = drupal_get_path('profile', $profile['machine_name']) . "/" . $profile['machine_name'] . ".info";
      $install_state['profile_info'] = drupal_parse_info_file($info_file);

      // When we prohibit a dependency we should remove it from the requirements
      // check so that sites can install without the prohibited module existing.

      $this->remove_prohibits_from_requirements($install_state, $tree);

      $errors = install_verify_requirements($install_state);

      if ($errors) {
        $report .= "<h1>" . $profile['name'] . "</h1>";
        $report .= $errors;
        $report .= "<p>&nbsp;</p><hr /><p>&nbsp;</p>";
      }

    }

    // If there are any errors show them.
    if (!empty($report)) {
      return $report;
    }

  }

  // ---------------------------------------------------------------------------

  /**
   * When we prohibit a dependency we should remove it from the requirements
   * check so that sites can install without the prohibited module existing.
   * @todo: Make prohibit values statically available.
   *
   * @param  [type] $install_state [description]
   * @param   $tree the tree of installation profiles.
   */
  protected function remove_prohibits_from_requirements(&$install_state, $tree) {

    $prohibits = array();
    foreach ($tree as $id => $profile) {
      if (isset($profile['prohibit'])) {
        $prohibits = $prohibits + $profile['prohibit'];
      }
     }

    // Remove all dependencies that are prohibited.
    $dependencies = $install_state['profile_info']['dependencies'];
    $cleaned = array_diff($dependencies, $prohibits);

    // Set dependencies to cleaned.
    $install_state['profile_info']['dependencies'] = $cleaned;

  }

  // ---------------------------------------------------------------------------

  /**
   * Returns an array of profiles in order from the top most parent to the
   * bottom most child and the profile info options.
   *
   * @param  string $profile_name the name of the child most profile
   * @return mixed - Array or false
   */
  protected function get_profile_tree($profile_name = '') {

    // Storage so we dont execute extra.
    if (isset($this->tree[$profile_name])) {
      return $this->tree[$profile_name];
    }

    $tree = array();

    $profile = profiler_v2_load_config($profile_name);
    $profile['machine_name'] = $profile_name;
    $tree[] = $profile;

    while (isset($profile['base'])) {
      $base = $profile['base'];
      $profile = profiler_v2_load_config($base);
      $profile['machine_name'] = $base;
      array_unshift($tree, $profile);
    }

    // Set the tree variables.
    $this->tree[$profile_name] = $tree;
    return $tree;

  }

  // ---------------------------------------------------------------------------

  /**
   * This function overrides the core install_profile_modules task so that it
   * installs all of the dependencies declared in each of the profiles in the
   * installation tree.
   * @param  array $install_state the current installation state.
   * @return array a batch array of modules to install.
   */
  public function install_profile_modules($install_state) {

    $tree = $this->get_profile_tree($install_state['parameters']['profile']);
    $batch = array('operations' => array());
    // Sets up the database variable.
    $this->prime_module_install($tree);

    foreach ($tree as $weight => $profile) {

      $install_state['parameters']['profile'] = $profile['machine_name'];
      install_load_profile($install_state);

      // Clear the module list to get the new profile data and modules.
      drupal_static_reset('system_rebuild_module_data');

      // Build dependant operations.
      $batch_info = install_profile_modules($install_state);

      // Patch information through. Ugly but worky.
      $batch['operations'] += $batch_info['operations'];
      $batch['title'] = $batch_info['title'];
      $batch['error_message'] = $batch_info['error_message'];
      $batch['finished'] = $batch_info['finished'];
    }

    return $batch;
  }

  // ---------------------------------------------------------------------------

  /**
   * This function is a little hack to ensure all the correct modules are ready.
   *
   * For the install profile module install task as the dependants are not
   * in the list by default.
   *
   * @param  array  $tree The installation tree
   */
  public function prime_module_install($tree = array()) {
    $modules = variable_get('install_profile_modules', array());

    // Prepend the tree with standard as it is required through the stanford
    // module but does not use the base system like this module.
    // array_unshift($tree, array('machine_name' => 'standard'));

    foreach ($tree as $weight => $profile) {

      // Add profile to dependencies so that hook_install gets run on all
      // parent profiles.
      $modules[] = $profile['machine_name'];
      $install_state = array('parameters' => array('profile' => $profile['machine_name']));
      $profile_info = install_load_profile($install_state);
      $parsed_file = $install_state['profile_info'];

      if (!isset($parsed_file['dependencies'])) {
        continue;
      }

      $modules = array_merge($modules, $parsed_file['dependencies']);

      // Allow the unsetting of modules.
      if (isset($parsed_file['prohibit'])) {
        $modules = array_diff($modules, $parsed_file['prohibit']);
      }

    }

    // Update the install variable.
    variable_set('install_profile_modules', array_unique($modules));
  }

  // ---------------------------------------------------------------------------

  /**
   * This function scans all of the profiles .info files for file declarations
   * and loads them as sometimes they are not always available.
   * @return [type] [description]
   */
  public function load_all_includes($install_state) {
    if (self::$called_once == TRUE) {
      return;
    }

    foreach ($install_state['profiles'] as $profile_machine_name => $v) {
      $info_path = DRUPAL_ROOT . "/profiles/" . $profile_machine_name . "/" . $profile_machine_name . ".info";
      $parsed_file = drupal_parse_info_file($info_path);

      // If there is nothing to include then don't include anything.
      if (!isset($parsed_file['files'])) {
        continue;
      }

      foreach ($parsed_file['files'] as $file) {
        require_once $info_path = DRUPAL_ROOT . "/profiles/" . $profile_machine_name . "/" . $file;
      }
    }

    self::$called_once = TRUE;
  }

  // ---------------------------------------------------------------------------

  /**
   * Set the active class that is to be installed.
   *
   * @param string $class_name The class name to be installed.
   */
  static public function set_active_profile($class_name = "") {
    // Once set do not set again!
    $current = self::get_active_profile();
    if ($current !== "JumpstartProfile") {
      return;
    }
    self::$active_class = $class_name;
  }

  // ---------------------------------------------------------------------------

  /**
   * Returns the active profile that is being installed.
   * @return string. The name of the class that is to be installed.
   */
  static public function get_active_profile() {
    return self::$active_class;
  }

  // ---------------------------------------------------------------------------

  /**
   * This fun function evaluates some generated code. YAK!.
   * @todo KILL THIS WITH FIRE
   *
   * Drupal installation tasks require a valid global callback. This creates
   * those and calls into the class in which they were defined.
   */
  public function prepare_task_handlers(&$install_state) {
    $tasks = $this->get_install_tasks($install_state);
    $code = "";

    /*
     * This super nasty code block essentially writes this but specifically for
     * each class name:
     *
     * function JumpstartSites_taskfunction_name(&$install_state) {
     * $profile = new JumpstartSites();
     * $profile->task_callback_function($install_state);
     * }
     */
    foreach ($tasks as $key => $task) {

      // Do not redeclare anything!
      if (function_exists($task['function'])) {
        continue;
      }

      if ($task['type'] == "form") {
        $code .= "function " . $task['function'] . "(&\$form, &\$form_state, &\$install_state) {\n";
      }
      else {
        $code .= "function " . $task['function'] . "(&\$install_state) {\n";
      }
      $code .= "\$profile = new " . $task['class_name'] . "();\n";
      if ($task['type'] == "form") {
        $code .= "\$profile->" . $task['class_function'] . "(\$form, \$form_state, \$install_state);\n";
      }
      else {
        $code .= "\$profile->" . $task['class_function'] . "(\$install_state);\n";
      }
      $code .= "}\n\n";
    }

    // *GAK* necessary evil right now.
    eval($code);
  }

  // ---------------------------------------------------------------------------

  /**
   * Prepares the installation tasks for running. In this case it ensures
   * uniqueness through class name prefixing of the task key as well as altering
   * the function callback to be named the same way.
   * @param  array  $tasks an array of installation tasks
   * @param  string  the name of the class that defined the tasks.
   */
  protected function prepare_tasks(&$tasks, $class_name = '') {
    $parsed_tasks = array();

    // Use defining class if that is the case.
    if (empty($class_name)) {
      $class_name = get_called_class();
    }

    // Loop through each of the tasks and alter the keys/names so that they
    // have uniqueness.
    foreach ($tasks as $key => $task) {

      $task['class_name'] = $class_name;

      if ($task['function']) {
        $task['class_function'] = $task['function'];
        $task['function'] = $class_name . "_" . $task['function'];
      }
      else {
        $task['class_function'] = $key;
        $task['function'] = $class_name . "_" . $key;
      }

      $parsed_tasks[$class_name . "_" . $key] = $task;
    }

    $tasks = $parsed_tasks;
  }

  // ---------------------------------------------------------------------------

  /**
   * Returns a boolean value to wether the current installation is on the
   * Stanford Sites environment or not.
   * @return boolean TRUE == On Sites Environment.
   */
  protected function is_on_stanford_sites() {

    if (!empty($this->is_sites_environment)) {
      return $this->is_sites_environment;
    }

    // This directory only should exist on the sites-* servers.
    $dir = "/etc/drupal-service";
    // Check if it exists and is a directory.
    if (file_exists($dir) && is_dir($dir)) {
      return TRUE;
    }

    return FALSE;

  }

  // ---------------------------------------------------------------------------

  /**
   * Specifically Set wether this installation is on the Stanford Sites
   * environment or not.
   * @param [Boolean] True is set was successful.
   */
  public function set_is_on_sites_environment($value) {

    if (!is_bool($value)) {
      return FALSE;
    }

    $this->is_sites_environment = $value;
    return TRUE;
  }

   // ---------------------------------------------------------------------------

  /**
   * Menu imports process does not find any paths from views defined in
   * features at this point. We will need to make it aware of them before
   * trying to import the menus links. Menu import uses drupal_valid_path() in
   * order to determine if a path is valid. drupal_valid_path() looks into the
   * menu router table for paths. At this point the menu_router table does not
   * have any views urls.
   *
   * menu_rebuild(); Doesnt work. Nice try!
   * @param  [type] $install_state [description]
   */
  public function save_all_default_views_to_db(&$install_state) {
    // Load up and save all views to the db.
    $implements = module_implements('views_default_views');
    $views = array();
    foreach ($implements as $module_name) {
      $views += call_user_func($module_name . "_views_default_views");
    }

    // Initialize! Alive!
    foreach ($views as $view) {
      $view->save(); // this unfortunately enables (turns on) the view as well.
    }

    drush_log('Saved Views For Menu System.', 'ok');
  }

  // ---------------------------------------------------------------------------

  /**
   * Removes all the default views from the DB after the site has been built.
   * Menu needs them to exist in the DB during the installation process as it
   * is unable to read the views and page paths from the features when saving
   * menu items this early in the sites life. A side effect is that all the
   * default views are enabled when saved to the db and they should not be.
   * @param  [type] $install_state [description]
   * @return [type]                [description]
   */
  public function remove_all_default_views_from_db(&$install_state) {

    $implements = module_implements('views_default_views');
    foreach ($implements as $module_name) {
      $views = call_user_func($module_name . '_views_default_views');

      foreach ($views as $view_name => $view) {
          $results = db_select('views_view', 'vv')
              ->fields('vv', array('vid'))
              ->condition('name', $view_name)
              ->range(0,1)
              ->execute()
              ->fetchAssoc();

          $view->vid = $results['vid'];
          $view->delete();
      }
    }

  }

  // ---------------------------------------------------------------------------

  /**
  * Create new Menu Position Rule.
  * @param array $mp_rules A multidimensional array with the following keys:
  * 'link_title' : Link title in the Main Menu. Assuming depth of 1
  * 'admin_title' : Administrative title of the Menu Position rule. Human-readable.
  * 'conditions' : multidimensional array of Menu Position conditions
  */
  protected function insert_menu_rule($mp_rule) {
    module_load_include('inc', 'menu_position', 'menu_position.admin');

    // Get the mlid of the parent link.
    $result = db_select('menu_links', 'm')
    ->fields('m', array('mlid'))
    ->condition('menu_name', 'main-menu')
    ->condition('depth', 1)
    ->condition('link_title', $mp_rule['link_title'])
    ->execute()
    ->fetchAssoc();

    $plid = $result['mlid'];


    // Create the array to populate the rule.
    $rule = array(
      'admin_title' => $mp_rule['admin_title'],
      'conditions' => $mp_rule['conditions'],
      'menu_name' => 'main-menu',
      'plid' => $plid,  // "News" item in main menu. Need to look this up programatically
    );

    // Calling menu_position_add_rule here because we can assume that no rules have been added.
    menu_position_add_rule($rule);
  }

  // ---------------------------------------------------------------------------

  /**
   * Loads the content importer library.
   */
  protected function load_sites_content_importer_files(&$install_state) {
    // Try to use libraries module if available to find the path.
    if (function_exists('libraries_get_path')) {
      $library_path = DRUPAL_ROOT . '/' . libraries_get_path('stanford_sites_content_importer');
    }
    if (!file_exists($library_path . '/SitesContentImporter.php')) {
      $library_path = DRUPAL_ROOT . '/sites/all/libraries/stanford_sites_content_importer';
    }
    if (!file_exists($library_path . '/SitesContentImporter.php')) {
      $library_path = DRUPAL_ROOT . '/sites/default/libraries/stanford_sites_content_importer';
    }
    $library_path .= "/SitesContentImporter.php";
    include_once $library_path;
  }


}
