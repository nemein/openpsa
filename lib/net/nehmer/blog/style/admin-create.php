<?php
// Available request keys: controller, indexmode, schema, schemadb
?>

<h2><?php echo $data['l10n']->get('create article'); ?>: <?php echo $data['topic']->extra; ?></h2>

<?php if ($data['indexmode']) { midcom_show_style('admin-create-indexnote'); } ?>

<?php $data['controller']->display_form (); ?>