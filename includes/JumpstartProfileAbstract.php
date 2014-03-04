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


}
