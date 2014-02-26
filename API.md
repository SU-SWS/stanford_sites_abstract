# Jumpstart API
* **Author:** Shea McKinney <sheamck@stanford.edu>
* **Changed:** December 19, 2013

## Drush

You can pass along extra options into the drush si command for added configuration.

    --full_name | The full name of the site owner user.
    --sunetid | The sunet id of the site owner user.

    eg: drush si stanford_sites_jumpstart --full_name="John Wayne" --sunetid="johnway" --strict=0

## .info

Two additional options have been added and are used in creating sub profiles.

    base
    Used to declare a parent profile.
    When a base is declared all of it's functionality will automatically be included.
    eg: base = stanford

    prohibit
    Used to restrict a previously declared dependency from installing.
    Use with care as dependants and install tasks may cause install to break.
    eg: prohibit[] = overlay

It is also very important to include all of the dependant files in the info file using the files[] = myfile.php notation. This includes the .install, .profile, ProfileClass.php, and any other resources.

## .profile

In order for your subprofile to work correctly you must include the following items.

1. Require includes/autoloader.php
2. hook_install_tasks()
3. hook_install_tasks_alter()
4. hook_form_install_configure_form_alter()

**autoloader.php**

See below for more

**hook_install_tasks()**

You are required to return the installation tasks. As the parent tasks are declared in their class you will need to initiate your installation class and return the tasks. eg:

    $profile = new MyFunProfileName();
    return $profile->get_install_tasks();

**hook_install_tasks_alter()**

This is where the meat of the system happens. This alter function heavily modifies the core installation process to include special directives. This needs to be called in every child profile as it acts as the entry point for the majority of the functionality and allows us to use multiple profiles.

    $profile = new MyFunProfileName();
    $profile->prepare_task_handlers($install_state);
    JumpstartSites::install_tasks_alter($tasks, $install_state);

**hook_form_install_configure_form_alter()**

This function allows all profiles in the installation tree to alter the installation form. You will need call the following at the very least:

    $profile = new MyFunProfileName();
    return $profile->get_config_form($form, $form_state);

## .install

This file is open to all functions. Nothing is required in this file. You may install items through the hook_install() function but because of the way Drupal install handles dependencies this functionality would probably be best included as an installation task. Using installation tasks ensures that all dependencies are available and all core functionality is available. Much more stable and feature rich.

## includes/autoloader.php

This file exists only for the early parts of the installation process. Before Drupal is able to parse .info files and keep the files[] declarations in the database we have to include those files ourselves. This file is used to 'chain-load' all of the profiles' files in the install tree. While the .info file is being parsed an additional look for a 'base' declaration. If a 'base' is declared then the autoloader looks for the base profile's autoloader.php file and executes the same process for that profile.

## includes/ExampleProfile.php

The minimum viable profile class must include the following:

1. Extend the parent profile or JumpstartProfile if the top most parent.
2. Implement public function get_install_tasks()
3. Implement public function get_config_form()

4. (optional) Validation functions

Example:

    <?php
    class myFunProfileName extends JumpstartSites {

    public function get_install_tasks(&$install_state) {
        $parent_tasks = parent::get_install_tasks($install_state);

	    $tasks['my_task'] = array(
		    'display_name' => st('My Task'),
		    'display' => TRUE,
		    'type' => 'normal',
		    'function' => 'my_task',
		    'run' => INSTALL_TASK_RUN_IF_NOT_COMPLETED,
	    );

	    $this->prepare_tasks($tasks, get_class());
	    return array_merge($parent_tasks, $tasks);
    }

    public function get_config_form(&$form, &$form_state) {
        return parent::get_config_form($form, $form_state);
    }


    public function my_task(&$install_state) {
    	variable_set('site_name', "My New Site Install Name");
    }

    }


**function get_install_tasks(&$install_state)**

When declaring your own installation tasks you will need to do 2 things. The first thing is to get all of the parent tasks by calling parent::get_install_tasks(). This is not mandatory but will remove all of the parent installation tasks if you ignore it. The second thing is to declare your custom tasks and run it through the prepare_tasks() method. This method adjusts the task so that the Drupal installation process can call it while running the tasks. Return both the parent and your tasks merged into one array of tasks. Do not run the parent tasks through prepare_tasks as they have already been done.

**function get_config_form(&$form, &$form_state)**

This function allows you to alter the configuration form. At the very least get and return the parent form. See code example above.

**function get_config_form_validate(&$form, &$form_state)**

This function is optional to declare. This function performs hook_form_validate for the configuration form. Use as you normally would a validate function.

**function get_config_form_submit(&$form, &$form_state)**

This function is optional to declare. This function performs hook_form_submit for the configuration form. Use as you normally would a submit function.
