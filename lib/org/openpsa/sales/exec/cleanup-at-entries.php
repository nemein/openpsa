<?php
midcom::get('auth')->require_admin_user();

// Ensure this is not buffered
midcom::get('cache')->content->enable_live_mode();
while(@ob_end_flush())

// TODO: Could this be done more safely somehow
@ini_set('memory_limit', -1);
@ini_set('max_execution_time', 0);

echo "<h1>Cleanup deliverable AT entries:</h1>\n";

$qb = org_openpsa_sales_salesproject_deliverable_dba::new_query_builder();
$qb->add_constraint('orgOpenpsaObtype', '=', ORG_OPENPSA_PRODUCTS_DELIVERY_SUBSCRIPTION);
$qb->add_constraint('state', '>=', org_openpsa_sales_salesproject_deliverable_dba::STATUS_ORDERED);
$deliverables = $qb->execute();

echo "<pre>\n";
flush();
foreach ($deliverables as $deliverable)
{
    $mc = new org_openpsa_relatedto_collector($deliverable->guid, 'midcom_services_at_entry_dba');
    $mc->add_object_order('start', 'DESC');
    $at_entries = $mc->get_related_objects();

    if (sizeof($at_entries) <= 1)
    {
        continue;
    }

    echo "Removing duplicate AT entries for deliverable #{$deliverable->id} " . $deliverable->title . "\n";
    flush();

    $first = true;
    foreach ($at_entries as $entry)
    {
        if ($first)
        {
            $first = false;
            echo "Keeping entry for " . strftime('%x %X', $entry->start) . "\n";
            flush();
            continue;
        }

        echo "Deleting entry for " . strftime('%x %X', $entry->start) . "\n";
        $entry->delete();
        flush();
    }
}
?>
</pre>
<p>All done</p>