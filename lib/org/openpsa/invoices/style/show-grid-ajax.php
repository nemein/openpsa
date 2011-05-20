<?php
$prefix = $_MIDCOM->get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX);

$grid = $data['grid'];
$classes = $data['list_type'];

if ($data['list_type'] == 'overdue')
{
    $classes .= ' bad';
}
else if ($data['list_type'] == 'paid')
{
    $classes .= ' good';
}
?>
<script type="text/javascript">
function calculate_total()
{
    var grid = $('#<?php echo $grid->get_identifier(); ?>'),
    total = grid.jqGrid('getCol', 'index_sum', false, 'sum'),
    separator_expression = /([0-9]+)([0-9]{3})/;

    total = total.toFixed(2).replace(/\./, $.jgrid.formatter.number.decimalSeparator);

    while (separator_expression.test(total))
    {
        total = total.replace(separator_expression, '$1' + $.jgrid.formatter.number.thousandsSeparator + '$2');
    }

    grid.jqGrid("footerData", "set", {"sum": total});
}
</script>
<?php
$grid->set_option('scroll', 1);
$grid->set_option('rowNum', 12);
$grid->set_option('height', 120);
$grid->set_option('viewrecords', true);
$grid->set_option('url', $prefix . 'list/json/' . $data['list_type'] . '/');
$grid->set_option('caption', $data['list_label']);
$grid->set_option('footerrow', true);
$grid->set_option('loadComplete', 'calculate_total', false);

$grid->set_column('number', $data['l10n']->get('invoice'), 'width: 80, align: "center", fixed: true, classes: "title"', 'string')
    ->set_column('contact', $data['l10n']->get('customer contact'), 'sortable: false');

if ($data['show_customer'])
{
    $grid->set_column('customer', $data['l10n']->get('customer'), 'sortable: false');
}

$grid->set_column('sum', $data['l10n']->get('amount'), 'width: 80, fixed: true, align: "right"', 'number')
    ->set_column('due', $data['l10n']->get('due'), 'width: 80, align: "center", formatter: "date"');

if ($data['list_type'] != 'paid')
{
    $grid->set_column('action', $data['l10n']->get('next action'), 'width: 80, align: "center"');
}
else
{
    $grid->set_column('action', $data['l10n']->get('paid date'), 'width: 80, align: "center"');
}

$footer_data = array
(
    'contact' => $data['l10n']->get('totals')
);

$grid->set_footer_data($footer_data);
?>

<div class="org_openpsa_invoices <?php echo $classes ?> full-width">
<?php $grid->render(); ?>
</div>