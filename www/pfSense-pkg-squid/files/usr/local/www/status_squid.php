<?php
/*
 * status_squid.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2026 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 */

include("guiconfig.inc");

$pgtitle = array(gettext("Package"), gettext("Squid"), gettext("Status"));
$shortcut_section = "squid";
include("head.inc");

// Tabs
$tab_array = array();
if ($_REQUEST["menu"] == "reverse") {
	$tab_array[] = array(gettext("General"), false, "/pkg_edit.php?xml=squid_reverse_general.xml&amp;id=0");
	$tab_array[] = array(gettext("Web Servers"), false, "/pkg.php?xml=squid_reverse_peer.xml");
	$tab_array[] = array(gettext("Mappings"), false, "/pkg.php?xml=squid_reverse_uri.xml");
	$tab_array[] = array(gettext("Redirects"), false, "/pkg.php?xml=squid_reverse_redir.xml");
	$tab_array[] = array(gettext("Real Time"), true, "/squid_monitor.php?menu=reverse");
	$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=squid_reverse_sync.xml");
} else {
	$tab_array[] = array(gettext("General"), false, "/pkg_edit.php?xml=squid.xml&amp;id=0");
	$tab_array[] = array(gettext("Remote Cache"), false, "/pkg.php?xml=squid_upstream.xml");
	$tab_array[] = array(gettext("Local Cache"), false, "/pkg_edit.php?xml=squid_cache.xml&amp;id=0");
	$tab_array[] = array(gettext("Antivirus"), false, "/pkg_edit.php?xml=squid_antivirus.xml&amp;id=0");
	$tab_array[] = array(gettext("ACLs"), false, "/pkg_edit.php?xml=squid_nac.xml&amp;id=0");
	$tab_array[] = array(gettext("Traffic Mgmt"), false, "/pkg_edit.php?xml=squid_traffic.xml&amp;id=0");
	$tab_array[] = array(gettext("Authentication"), false, "/pkg_edit.php?xml=squid_auth.xml&amp;id=0");
	$tab_array[] = array(gettext("Users"), false, "/pkg.php?xml=squid_users.xml");
	$tab_array[] = array(gettext("Real Time"), false, "/squid_monitor.php");
	$tab_array[] = array(gettext("Status"), true, "/status_squid.php");
	$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=squid_sync.xml");
}
display_top_tabs($tab_array);

// Squid Status Function
function squid_status() {
	if (!is_service_running('squid')) {
		return gettext('Squid Proxy is not running.');
	}

	$proxy_ifaces = explode(",", config_get_path('installedpackages/squid/config/0/active_interface', ''));
	$result = [];

	// Squid 6: curl instead of squidclient
	$squid_conf = '/usr/local/etc/squid/squid.conf';
	$user = $pass = null;
	if (file_exists($squid_conf)) {
		$conf = file_get_contents($squid_conf);
		if (preg_match('/^cachemgr_passwd\s+(\S+)\s+(\S+)/m', $conf, $m)) {
			$user = $m[1];
			$pass = $m[2];
		}
	}

	foreach ($proxy_ifaces as $iface) {
		$ip = get_interface_ip($iface) ?: get_interface_ipv6($iface);
		$port = 3128;

		$cmd = "/usr/local/bin/curl -s --max-time 10 ";
		if ($user && $pass) {
			$cmd .= "-u '{$user}:{$pass}' ";
		}
		$cmd .= "http://{$ip}:{$port}/squid-internal-mgr/info";

		exec($cmd, $output_iface);
		$result = array_merge($result, $output_iface);
	}

	// Parse output for "Squid Object Cache"
	$begin = 0;
	foreach ($result as $i => $line) {
		if (preg_match("/Squid Object Cache/", $line)) {
			$begin = $i;
			break;
		}
	}

	$output = "";
	for ($i = $begin; $i < count($result); $i++) {
		$output .= $result[$i] . "\n";
	}

	return $output;
}
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title">Connection list</h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed">
			<tbody>
				<?php
				print "<pre>";
				print htmlentities(squid_status());
				print "</pre>";
				?>
			</tbody>
		</table>
	</div>
</div>

<?php include("foot.inc"); ?>
