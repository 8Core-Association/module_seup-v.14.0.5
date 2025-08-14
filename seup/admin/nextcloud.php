<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2025		SuperAdmin
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    seup/admin/nextcloud.php
 * \ingroup seup
 * \brief   Nextcloud configuration page for SEUP module.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

// Libraries
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once '../lib/seup.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

// Translations
$langs->loadLangs(array("errors", "admin", "seup@seup"));

// Access control
if (!$user->admin) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

$error = 0;
$setupnotempty = 0;

/*
 * Actions
 */

if ($action == 'update') {
	$nextcloud_url = GETPOST('NEXTCLOUD_URL', 'alpha');
	$nextcloud_username = GETPOST('NEXTCLOUD_USERNAME', 'alpha');
	$nextcloud_password = GETPOST('NEXTCLOUD_PASSWORD', 'alpha');
	$nextcloud_enabled = GETPOST('NEXTCLOUD_ENABLED', 'alpha');

	// Validate URL format
	if (!empty($nextcloud_url) && !filter_var($nextcloud_url, FILTER_VALIDATE_URL)) {
		setEventMessages($langs->trans("ErrorInvalidURL"), null, 'errors');
		$error++;
	}

	if (!$error) {
		$res = 0;
		$res += dolibarr_set_const($db, "NEXTCLOUD_URL", $nextcloud_url, 'chaine', 0, '', $conf->entity);
		$res += dolibarr_set_const($db, "NEXTCLOUD_USERNAME", $nextcloud_username, 'chaine', 0, '', $conf->entity);
		$res += dolibarr_set_const($db, "NEXTCLOUD_PASSWORD", $nextcloud_password, 'chaine', 0, '', $conf->entity);
		$res += dolibarr_set_const($db, "NEXTCLOUD_ENABLED", $nextcloud_enabled, 'chaine', 0, '', $conf->entity);

		if ($res >= 4) {
			setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
		} else {
			setEventMessages($langs->trans("Error"), null, 'errors');
		}
	}
}

if ($action == 'test_connection') {
	// Test Nextcloud connection
	require_once '../class/nextcloud_api.class.php';
	
	try {
		$nextcloudApi = new NextcloudAPI($db, $conf);
		$testFiles = $nextcloudApi->getFilesFromFolder('/');
		
		if (is_array($testFiles)) {
			setEventMessages($langs->trans("NextcloudConnectionSuccess") . " (" . count($testFiles) . " " . $langs->trans("FilesFound") . ")", null, 'mesgs');
		} else {
			setEventMessages($langs->trans("NextcloudConnectionFailed"), null, 'errors');
		}
	} catch (Exception $e) {
		setEventMessages($langs->trans("NextcloudConnectionError") . ": " . $e->getMessage(), null, 'errors');
	}
}

/*
 * View
 */

$form = new Form($db);

$help_url = '';
$title = "SEUPSetup";

llxHeader('', $langs->trans($title), $help_url, '', 0, 0, '', '', '', 'mod-seup page-admin_nextcloud');

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

// Configuration header
$head = seupAdminPrepareHead();
print dol_get_fiche_head($head, 'nextcloud', $langs->trans($title), 0, 'seup@seup');

// Page content
print '<span class="opacitymedium">'.$langs->trans("NextcloudConfigurationPage").'</span><br><br>';

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '<td>'.$langs->trans("Description").'</td>';
print '</tr>';

// Enable/Disable Nextcloud
print '<tr class="oddeven">';
print '<td>'.$langs->trans("EnableNextcloudIntegration").'</td>';
print '<td>';
print $form->selectyesno("NEXTCLOUD_ENABLED", getDolGlobalString('NEXTCLOUD_ENABLED'), 1);
print '</td>';
print '<td>'.$langs->trans("EnableNextcloudIntegrationTooltip").'</td>';
print '</tr>';

// Nextcloud URL
print '<tr class="oddeven">';
print '<td>'.$langs->trans("NextcloudURL").'</td>';
print '<td>';
print '<input type="url" name="NEXTCLOUD_URL" value="'.getDolGlobalString('NEXTCLOUD_URL').'" size="40" placeholder="https://cloud.example.com">';
print '</td>';
print '<td>'.$langs->trans("NextcloudURLTooltip").'</td>';
print '</tr>';

// Nextcloud Username
print '<tr class="oddeven">';
print '<td>'.$langs->trans("NextcloudUsername").'</td>';
print '<td>';
print '<input type="text" name="NEXTCLOUD_USERNAME" value="'.getDolGlobalString('NEXTCLOUD_USERNAME').'" size="30" placeholder="username">';
print '</td>';
print '<td>'.$langs->trans("NextcloudUsernameTooltip").'</td>';
print '</tr>';

// Nextcloud Password/Token
print '<tr class="oddeven">';
print '<td>'.$langs->trans("NextcloudPassword").'</td>';
print '<td>';
print '<input type="password" name="NEXTCLOUD_PASSWORD" value="'.getDolGlobalString('NEXTCLOUD_PASSWORD').'" size="30" placeholder="app-password-or-token">';
print '</td>';
print '<td>'.$langs->trans("NextcloudPasswordTooltip").'</td>';
print '</tr>';

print '</table>';

print '<div class="tabsAction">';
print '<input type="submit" class="button buttongen" value="'.$langs->trans("Save").'">';
print '</div>';

print '</form>';

// Test connection section
if (getDolGlobalString('NEXTCLOUD_URL') && getDolGlobalString('NEXTCLOUD_USERNAME')) {
	print '<br><br>';
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="test_connection">';
	
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td colspan="2">'.$langs->trans("TestConnection").'</td>';
	print '</tr>';
	
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("TestNextcloudConnection").'</td>';
	print '<td>';
	print '<input type="submit" class="button buttongen" value="'.$langs->trans("TestConnection").'">';
	print '</td>';
	print '</tr>';
	
	print '</table>';
	print '</form>';
}

// Connection status
if (getDolGlobalString('NEXTCLOUD_ENABLED')) {
	print '<br>';
	print '<div class="info">';
	print '<strong>'.$langs->trans("NextcloudStatus").':</strong> ';
	if (getDolGlobalString('NEXTCLOUD_URL') && getDolGlobalString('NEXTCLOUD_USERNAME') && getDolGlobalString('NEXTCLOUD_PASSWORD')) {
		print '<span style="color: green;">'.$langs->trans("Configured").'</span>';
	} else {
		print '<span style="color: orange;">'.$langs->trans("PartiallyConfigured").'</span>';
	}
	print '</div>';
}

// Page end
print dol_get_fiche_end();
llxFooter();
$db->close();