<?php
midcom::get('auth')->require_admin_user();
midcom::get('cache')->content->enable_live_mode();
midcom::get()->header('Content-Type: text/plain');
$exporter = midcom_helper_filesync_exporter::create('structure');
$exporter->export();
echo "Export to {$exporter->root_dir} completed\n";
?>