<?php
/**
 * Plugin Name: Adopte un corail / certificate
 * Plugin URI:
 * Description: Gestion des certificats
 * Version: 0.1
 * Requires PHP: 8.1
 * Author: Benoit DELBOE & Grégory COLLIN
 * Author URI:
 * Licence: GPLv2
 */
add_action('plugins_loaded', [\D4rk0snet\Certificate\Plugin::class, 'launchActions']);
add_action('cli_init', [\D4rk0snet\Certificate\Plugin::class, 'addCLICommands']);
