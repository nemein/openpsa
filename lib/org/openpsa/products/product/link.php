<?php
/**
 * @package org.openpsa.products
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * MidCOM wrapped class for access to stored queries
 *
 * @package org.openpsa.products
 */
class org_openpsa_products_product_link_dba extends midcom_core_dbaobject
{
    public $__midcom_class_name__ = __CLASS__;
    public $__mgdschema_class_name__ = 'org_openpsa_products_product_link';

    function get_parent_guid_uncached()
    {
        if ($this->productGroup != 0)
        {
            $parent = new org_openpsa_products_product_group_dba($this->productGroup);
            return $parent->guid;
        }
        else
        {
            debug_add("No parent defined for this product");
            return null;
        }
    }
}
?>