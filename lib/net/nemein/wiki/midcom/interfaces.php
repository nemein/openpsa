<?php
/**
 * @package net.nemein.wiki
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Wiki MidCOM interface class.
 *
 * @package net.nemein.wiki
 */
class net_nemein_wiki_interface extends midcom_baseclasses_components_interface
{
    /**
     * Iterate over all wiki pages and create index record using the datamanager2 indexer
     * method.
     */
    public function _on_reindex($topic, $config, &$indexer)
    {
        $qb = net_nemein_wiki_wikipage::new_query_builder();
        $qb->add_constraint('topic', '=', $topic->id);
        $result = $qb->execute();

        if ($result)
        {
            $schemadb = midcom_helper_datamanager2_schema::load_database($config->get('schemadb'));
            $datamanager = new midcom_helper_datamanager2_datamanager($schemadb);
            if (! $datamanager)
            {
                debug_add('Warning, failed to create a datamanager instance with this schemapath:' . $config->get('schemadb'),
                    MIDCOM_LOG_WARN);
                continue;
            }

            foreach ($result as $wikipage)
            {
                if (! $datamanager->autoset_storage($wikipage))
                {
                    debug_add("Warning, failed to initialize datamanager for Wiki page {$wikipage->id}. Skipping it.", MIDCOM_LOG_WARN);
                    continue;
                }

                net_nemein_wiki_viewer::index($datamanager, $indexer, $topic);
            }
        }

        return true;
    }

    public function _on_resolve_permalink($topic, $config, $guid)
    {
        try
        {
            $article = new midcom_db_article($guid);
            if ($article->name == 'index')
            {
                return '';
            }
            return "{$article->name}/";
        }
        catch (midcom_error $e)
        {
            return null;
        }
    }

    /**
     * Check whether given wikiword is free in given node
     *
     * Returns true if word is free, false if reserved
     */
    public static function node_wikiword_is_free(&$node, $wikiword)
    {
        if (empty($node))
        {
            //Invalid node
            debug_add('given node is not valid', MIDCOM_LOG_ERROR);
            return false;
        }
        $wikiword_name = midcom_helper_misc::generate_urlname_from_string($wikiword);
        $qb = new midgard_query_builder('midgard_article');
        $qb->add_constraint('topic', '=', $node[MIDCOM_NAV_OBJECT]->id);
        $qb->add_constraint('name', '=', $wikiword_name);
        $ret = @$qb->execute();
        if (   is_array($ret)
            && count($ret) > 0)
        {
            //Match found, word is reserved
            debug_add("QB found matches for name '{$wikiword_name}' in topic #{$node[MIDCOM_NAV_OBJECT]->id}, given word '{$wikiword}' is reserved", MIDCOM_LOG_INFO);
            debug_print_r('QB results:', $ret);
            return false;
        }
        return true;
    }
}
?>