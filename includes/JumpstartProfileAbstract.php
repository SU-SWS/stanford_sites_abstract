<?php
/**
 * @file
 * @author  Shea McKinney <sheamck@stanford.edu>
 */

class JumpstartProfileAbstract extends JumpstartProfile {

  /**
   * Returns a list of tasks to run during installation. These run
   * after standard and stanford but before the inheritable profiles.
   * @param  [type] $install_state [description]
   * @return [type]                [description]
   */
  public function get_install_tasks(&$install_state) {
    $tasks = array();

    $tasks['stanford_sites_abstract_stanford_install_tasks'] = array(
      'display_name' => st('Stanford Install Tasks.'),
      'display' => FALSE,
      'type' => 'normal',
      'function' => 'stanford_profile_install_tasks',
      'run' => INSTALL_TASK_RUN_IF_NOT_COMPLETED,
    );

    $tasks['stanford_sites_abstract_disable_modules'] = array(
      'display_name' => st('JumpstartAbstract - Disable Modules.'),
      'display' => FALSE,
      'type' => 'normal',
      'function' => 'disable_modules',
      'run' => INSTALL_TASK_RUN_IF_NOT_COMPLETED,
    );

    $tasks['stanford_sites_abstract_install_config'] = array(
      'display_name' => st('JumpstartAbstract - Install Config.'),
      'display' => FALSE,
      'type' => 'normal',
      'function' => 'install_config',
      'run' => INSTALL_TASK_RUN_IF_NOT_COMPLETED,
    );

    $tasks['stanford_sites_abstract_revert_all_features'] = array(
      'display_name' => st('JumpstartAbstract - Revert Features.'),
      'display' => FALSE,
      'type' => 'normal',
      'function' => 'revert_all_features',
      'run' => INSTALL_TASK_RUN_IF_NOT_COMPLETED,
    );

    // Prepare class callback.
    $this->prepare_tasks($tasks, get_class());
    return $tasks;
  }

  /**
   * [get_config_form description]
   * @param  [type] $form       [description]
   * @param  [type] $form_state [description]
   * @return [type]             [description]
   */
  public function get_config_form(&$form, &$form_state) {

    // Call Standard's & Stanford's config alter directly.
    standard_form_install_configure_form_alter($form, $form_state);
    stanford_form_install_configure_form_alter($form, $form_state);

    $form['jumpstart'] = array(
      '#type' => 'fieldset',
      '#title' => 'Jumpstart Configuration',
      '#description' => 'Jumpstart Sites Configuration Options',
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    );

    $form['jumpstart']['sunetid'] = array(
      '#type' => 'textfield',
      '#title' => 'Sunet ID',
      '#description' => 'Please enter your Sunet ID without the @stanford suffix. ie: johndoe',
      '#default_value' => isset($form_state['values']['sunetid']) ? $form_state['values']['sunetid'] : '',
    );

    $form['jumpstart']['full_name'] = array(
      '#type' => 'textfield',
      '#title' => 'Full Name',
      '#description' => 'Please enter your first and last name. ie: John Doe',
      '#default_value' => isset($form_state['values']['full_name']) ? $form_state['values']['full_name'] : '',
    );

    $form['#validate'][]  = 'stanford_sites_abstract_form_install_configure_form_alter_validate';
    $form['#submit'][]    = 'stanford_sites_abstract_form_install_configure_form_alter_submit';

    return $form;
  }


  /**
   * Runs install tasks as defined by the stanford installation profile
   * @param  [array] $install_state [the current installation state]
   * @return [type]                [description]
   */
  public function stanford_profile_install_tasks($install_state) {
    stanford_sites_tasks($install_state);
  }

  // ---------------------------------------------------------------------------
  // ---------------------------------------------------------------------------

  /**
   * Disable and uninstall some modules that we no longer need.
   *
   * Only specifically are we going to disable them. No dependants.
   * @param  [array] $install_state [the current installation state]
   */
  public function disable_modules(&$install_state) {
    drupal_flush_all_caches();

    // We need to disable anything installed by standard directly as it is a
    // special case and we cannot use prohibit for them.
    $disable_these_modules = array(
      'stanford_wysiwyg',
      'toolbar',
      'comment',
      'clone',
      'overlay',
      'dashboard',
      'shortcut',
    );

    // Disable and uninstall only the moduels we want. Not the dependants.
    module_disable($disable_these_modules, FALSE);
    drupal_uninstall_modules($disable_these_modules, FALSE);
  }

  // ---------------------------------------------------------------------------

  /**
   * Installation tasks to fix anything that standard and stanford did that
   * we do not want for any of our work.
   */
  public function install_config() {

    // This variable is set in the stanford installation profile and causes
    // havoc when installing through drush. Re-enable later.
    variable_del('file_private_path');

    // variable_set('file_private_path', 'sites/default/files/private');
  }

  // ---------------------------------------------------------------------------

  /**
   * If features exists revert all of them.
   * @param  [type] $install_state [description]
   */
  public function revert_all_features(&$install_state) {
    if (module_exists('features') || function_exists('features_revert')) {
      drush_log('Reverting all of the features.', 'status');
      features_revert();
    }
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



}
