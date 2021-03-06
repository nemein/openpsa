<?php
$prefix = midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX);
$reporters = $data['reporters'];
$tasks = $data['tasks'];
$reports = $data['reports'];

$entries = array();

$grid_id = $data['status'] . '_hours_grid';

foreach ($reports['reports'] as $report)
{
    $entry = array();

    $description = "<em>" . $data['l10n']->get('no description given') . "</em>";
    if (! preg_match("/^[\W]*?$/", $report->description))
    {
        $description = $report->description;
    }

    $entry['id'] = $report->id;
    $entry['date'] = strftime('%Y-%m-%d', $report->date);

    if ($data['mode'] != 'simple')
    {
        $entry['task'] = $tasks[$report->task];
    }

    $entry['index_description'] = $description;
    $entry['description'] = '<a href="' . $prefix . 'hours/edit/' . $report->guid . '">' . $description . '</a>';

    $entry['reporter'] = $reporters[$report->person];

    $entry['index_hours'] = $report->hours;
    $entry['hours'] = $report->hours . ' ' . $data['l10n']->get('hours unit');

    $entries[] = $entry;
}
echo '<script type="text/javascript">//<![CDATA[';
echo "\nvar " . $grid_id . '_entries = ' . json_encode($entries);
echo "\n//]]></script>";

$footer_data = array
(
    'date' => $data['l10n']->get('total'),
    'hours' => $reports['hours']
);
?>
<div class="org_openpsa_expenses <?php echo $data['status']; ?> batch-processing full-width fill-height" style="margin-bottom: 1em">

<table id="&(grid_id);"></table>
<div id="p_&(grid_id);"></div>

<form id="form_&(grid_id);" method="post" action="<?php echo $data['action_target_url']; ?>">
<input type="hidden" name="relocate_url" value="<?php echo $_SERVER['REQUEST_URI']; ?>" />
</form>

</div>

<script type="text/javascript">
org_openpsa_grid_helper.setup_grid("&(grid_id);", {
      datatype: "local",
      data: &(grid_id);_entries,
      colNames: ['id', <?php
                 echo '"' . $data['l10n']->get('date') . '",';
                 echo '"' . $data['l10n']->get('person') . '",';
                 if ($data['mode'] != 'simple')
                 {
                     echo '"' . $data['l10n']->get('task') . '",';
                 }
                 echo '"index_hours", "' . $data['l10n']->get('hours') . '",';
                 echo '"index_description", "' . $data['l10n']->get('description') . '",';
      ?>],
      colModel:[
          {name:'id', index:'id', hidden: true, key: true},
          {name:'date', index: 'date', width: 80, align: 'center', formatter: 'date', fixed: true},
          {name:'reporter', index: 'reporter', width: 80, classes: 'ui-ellipsis'},
          <?php if ($data['mode'] != 'simple')
          { ?>
              {name:'task', index: 'task', classes: 'ui-ellipsis'},
          <?php } ?>
          {name:'index_hours', index: 'index_hours', sorttype: "integer", hidden: true },
          {name:'hours', index: 'index_hours', width: 50, align: 'right'},
          {name:'index_description', index: 'index_description', hidden: true},
          {name:'description', index: 'index_description', width: 250, classes: 'ui-ellipsis'}
       ],
       sortname: 'date',
       sortorder: 'desc',
       pager: "#p_&(grid_id);",
       loadonce: true,
       caption: "&(data['subheading']:h);",
       footerrow: true,
       multiselect: true
    });

jQuery("#&(grid_id);").jqGrid('footerData', 'set', <?php echo json_encode($footer_data); ?>);

org_openpsa_batch_processing.initialize(
{
    id: '&(grid_id);',
    options: <?php echo json_encode($data['action_options']); ?>
});
</script>

