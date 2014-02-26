<?php
/**
 * @file
 * Default Installation Class for Jumpstart
 *
 */

/**
 * JumpStart Installation Profile Class
 */
interface JumpstartProfileInterface {

  /**
   * A key function to returning all of the installation tasks
   * for all of the profiles being installed.
   * @param  [array] $install_state [the current installation state]
   * @return [array]                [an array of install tasks]
   */
  function get_install_tasks(&$install_state);

  /**
   * A key function for altering the configuration form. Allows all profiles
   * to get their grubby paws on it.
   * @param  [array] $form       [the current form]
   * @param  [array] $form_state [the form state]
   * @return [array]             [the altered form]
   */
  function get_config_form(&$form, &$form_state);



}
