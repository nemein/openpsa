<?php
$prefix = $_MIDCOM->get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX);
$task =& $data['task'];
echo "<dt><a href=\"{$prefix}task/{$task->guid}/\">{$task->title}</a>";
if ($parent = $task->get_parent())
{
    if (is_a($parent, 'org_openpsa_projects_project'))
    {
        $parent_url = "{$prefix}project/{$parent->guid}/";
    }
    else
    {
        $parent_url = "{$prefix}task/{$parent->guid}/";
    }
    echo " <span class=\"metadata\">(<a href=\"{$parent_url}\">{$parent->title}</a>)</span>";
}
echo "</dt>\n";
echo "<dd>\n";

$task->get_members();
if ( count($task->resources) > 0)
{
    $resources_string = '';
    foreach ($task->resources as $id => $boolean)
    {
        $contact = org_openpsa_widgets_contact::get($id);
        $resources_string .= ' ' . $contact->show_inline();
    }
    echo sprintf($data['l10n']->get("declined by %s"), $resources_string);
}
?>
</dd>