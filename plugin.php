<?php
/**
 ***********************************************************************************************
 * Organization switcher - entry file.
 *
 * An entry in the profile menu with which a member of several organizations changes to another one
 * without logging out.
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use AdmidioPlugin\OrganizationSwitcher\OrganizationSwitcher;

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit('This page may not be called directly!');
}

OrganizationSwitcher::register();
