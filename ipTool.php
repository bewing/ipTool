<?php
/**
 * This file contains the class that provides an IP Allocation API
 *
 * PHP version 5
 *
 * @category Net
 * @package  ipTool
 * @author   Brandon Ewing <nicotine@warningg.com>
 * @filesource
 */

/**
 * Class for handling IP allocations.
 *
 * @access public
 * @category Net
 * @package  ipTool
 */
class ipTool
{
    /** 
     * MDB2 Object used to store information regarding our current allocations
     * @var MDB2_Driver_Common
     * Expects a table named allocation, SQL is in ipTool.sql, and expects no 
     * fixcase
     */
    var $db;

    /**
     * getNetblock 
     * 
     * Get information about a netblock
     * 
     * @param int $netblockId   Id of netblock to retrieve 
     * @access public
     * @return Net_IPv4Alloc|MDB2_Error Net_IPv4Alloc on success, an MDB2 error on failure
     */
    public function getNetblock($netblockId)
    {
        $result = $this->db->query("SELECT vrf, netblock AS bin, INET6_ntoa(netblock) AS netblock, bitmask, parentId, objectId, isLeaf, description FROM allocation WHERE netblockId = $netblockId");
        if (PEAR::isError($result)) {
            return $result;
        }
        $row = $result->fetchRow(MDB2_FETCHMODE_OBJECT);
        /*
        return array("network" => long2ip($row->netblock),
                     "long" => $row->netblock,
                     "bitmask" => $row->bitmask,
                     "parentId" => $row->parentId,
                     "isLeaf" => $row->isLeaf,
                     "description" => $row->description);
        */

        if (Net_IPv4Alloc::validateIP($row->netblock)) {
            $netblock = Net_IPv4Alloc::parseAddress($row->netblock ."/". $row->bitmask);
        } elseif (Net_IPv6::checkIPv6($row->netblock)) {
            $netblock = Net_IPv6Alloc::parseAddress($row->netblock ."/". $row->bitmask);
        }
        $netblock->vrf = $row->vrf;
        $netblock->netblockId = $netblockId;
        $netblock->bin = $row->bin;
        $netblock->parentId = $row->parentId;
        $netblock->objectId = $row->objectId;
        $netblock->isLeaf = $row->isLeaf;
        $netblock->description = $row->description;
        return $netblock;
    }

    /**
     * getNetblockId 
     * 
     * Retrieve a netblockId for a given object
     * 
     * @param Net_IPv4Alloc $netblock 
     * @access public
     * @return int|MDB2_Error int netblockId on success, MDB2 error on failure 
     */
    public function getNetblockId($netblock)
    {
        if (!(is_a($netblock, "net_ipv4alloc") || is_a($netblock, "net_ipv6alloc"))) {
            die("Bad!");
        }
        $result = $this->db->query("SELECT vrf, netblock AS bin, netblockId, parentId, objectId, isLeaf, description FROM allocation WHERE inet6_ntoa(netblock) = '".
        $netblock->network ."' AND bitmask = ". $netblock->bitmask);
        if (PEAR::isError($result))
            return $result;
        $row = $result->fetchRow(MDB2_FETCHMODE_OBJECT);
        $netblock->vrf = $row->vrf;
        $netblock->netblockId = $row->netblockId;
        $netblock->bin = $row->bin;
        $netblock->parentId = $row->parentId;
        $netblock->objectId = $row->objectId;
        $netblock->isLeaf = $row->isLeaf;
        return true;
    }

    /**
     * getAssignedNetblocks 
     * 
     * Get a list of netblocks assigned to an object
     * 
     * @param string $objectId   Id of assigned object
     * @access public
     * @return array|MDB2_Error    array of netblockIds on success, an MDB2 error on failure
     */
    public function getAssignedNetblocks($objectId) 
    {
        $result = $this->db->query("SELECT netblockId FROM allocation WHERE objectId = '$objectId'");
        if (PEAR::isError($result)) {
            return $result;
        }
        return $result->fetchCol();
    }
    /**
     * getNonleafNetblocks 
     * 
     * Get a list of netblocks that you can allocate from
     * 
     * @access public
     * @return array|MDB2_Error    array of netblockIds on success, an MDB2 error on failure
     */
    public function getNonleafNetblocks()
    {
        $result = $this->db->query("SELECT netblockId FROM allocation WHERE isLeaf IS FALSE");
        if (PEAR::isError($result)) {
            return $result;
        }
        return $result->fetchCol();
    }

    /**
     * getChildren 
     * 
     * Get a list of children for a given netblockId
     * 
     * @param int $parentId     netblockId of parent
     * @access public
     * @return array|MDB2_Error array of netblockIds on success, an MDB2 error on failure
     */
    public function getChildren($parentId)
    {
        $result = $this->db->query("SELECT netblockId FROM allocation WHERE parentId = $parentId");
        if (PEAR::isError($result)) {
            return $result;
        }
        return $result->fetchCol();
    }

    /**
     * assignNetblock 
     * 
     * Assign a netblock of a fixed size from a given allocation identified by parentId
     * Two allocation schemes are supported currently:
     *                                  + Request a specific netblock
     *                                  + First available
     * Additional schemes may be implemented in the future.
     * 
     * @param string $objectId          object to assign a netblock to
     * @param int $parentId             id of parent to allocate from
     * @param int $bitmask              desired bitmask of new allocation
     * @param bool $isLeaf              optional variable to allow sub-allocations of this netblock
     * @param string $description       variable to describe this netblock if sub-allocations are allowed
     * @param string $desiredNetwork    optional desired network address
     * @access public
     * @return Net_IPv4Alloc|PEAR_Error Net_IPv4Alloc object on success, PEAR::Error object on failure.
     */
    public function assignNetblock($objectId, $parentId, $bitmask, $isLeaf = true, $description = NULL, $desiredNetwork = NULL)
    {
        if (!$isLeaf && !$description) {
            // throw an error here
            die("Nonleaf with no description");
        }
        if (!is_null($desiredNetwork)) {
            if (Net_IPv4::validateIP($desiredNetwork)) {
                $test = Net_IPv4::parseAddress($desiredNetwork ."/". $bitmask);
            }
            elseif (Net_IPv6::checkIPv6($desiredNetwork)) {
                $test = Net_IPv6Alloc::parseAddress($desiredNetwork ."/". $bitmask);
            }
            if ($test->network != $test->ip)
                // throw an error here
            unset($test);
        }
        $lock = $this->db->query("LOCK TABLES allocation WRITE");
        if (PEAR::isError($lock))
            return $lock;
        $parent = $this->recurseIpTree($parentId);
        if (PEAR::isError($parent)) {
            $this->db->query("UNLOCK TABLES");
            return $parent;
        }
        if ($desiredNetwork) {
            foreach ($parent->children as $child) {
                if ($bitmask > $child->bitmask) {
                    if ($parent->type == "IPv4") {
                        if (Net_IPv4::ipInNetwork($desiredNetwork, $child)) {
                            $this->db->query("UNLOCK TABLES");
                            return false;
                        }
                    } elseif ($parent->type == "IPv6") {
                        if (Net_IPv6::ipInNetwork($desiredNetwork, $child)) {
                            $this->db->query("UNLOCK TABLES");
                            return false;
                        }
                    }
                } elseif ($bitmask < $child->bitmask) {
                    if ($parent->type == "IPv4") {
                        if (Net_IPv4::ipInNetwork($child->ip, $desiredNetwork ."/". $bitmask)) {
                            $this->db->query("UNLOCK TABLES");
                            return false;
                        }
                    } elseif ($parent->type == "IPv6") {
                        if (Net_IPv6::ipInNetwork($child->ip, $desiredNetwork ."/". $bitmask)) {
                            $this->db->query("UNLOCK TABLES");
                            return false;
                        }
                    }
                } else {
                    if ($child->ip == $desiredNetwork) {
                        $this->db->query("UNLOCK TABLES");
                        return false;
                    }
                }
            }
            if ($parent->type == "IPv4")
                $allocation = Net_IPv4Alloc::parseAddress($desiredNetwork ."/". $bitmask);
            elseif ($parent->type == "IPv6")
                $allocation = Net_IPv6Alloc::parseAddress($desiredNetwork ."/". $bitmask);
        } else {
            $allocation = $this->findFreeAllocation($parent, $bitmask);
        }
        if (!$allocation) {
            // throw error here
            die("No allocation");
        }
        $sth = $this->db->prepare('INSERT INTO allocation (parentId, vrf, netblock, bitmask, isLeaf, objectId, description) VALUES
            (:parentId, :vrf, inet6_aton(:network), :bitmask, :isLeaf, :objectId, :description)',
            array('integer', 'text', 'text', 'integer', 'boolean', 'integer', 'text'));
        $data = array(
            "parentId"      => $parentId,
            "vrf"           => $parent->vrf,
            "network"       => $allocation->network,
            "bitmask"       => $bitmask,
            "isLeaf"        => $isLeaf,
            "objectId"      => $objectId,
            "description"   => $description);
        $result = $sth->execute($data);
        if (PEAR::isError($result)) {
            $this->db->query("UNLOCK TABLES");
            return $result;
        }
        return $allocation;
    }

    /**
     * deallocateNetblock 
     * 
     * Recursively delete an allocation, and all its sub-allocations, yea, unto infinite generations
     * 
     * @param int $netblockId         id of netblock to delete
     * @access public
     * @return true|PEAR_Error        true on success, MDB2 error on failure
     */
    public function deallocateNetblock($netblockId)
    {
        $netblock = $this->recurseIpTree($netblockId);
        if (PEAR::isError($netblock))
            return $netblock;
        foreach ($netblock->children as $child) {
            $result = $this->deallocateNetblock($this->getNetblockId($child));
            if (PEAR::isError($return))
                return $result;
        }
        $result = $this->db->query("DELETE FROM allocation WHERE netblockId = $netblockId");
        if (PEAR::isError($result))
            return $result;
        return true;
    }

    /**
     * findFreeAllocation 
     * 
     * Find a free allocation in a netblock
     * 
     * @param Net_IPv4Alloc $parent A parent allocation built from recurseIpTree
     * @param mixed $bitmask    Desired bitmask
     * @access protected
     * @return Net_IPv4Alloc|false Net_IPv4Alloc on success, false on failure
     */
    public function findFreeAllocation($parent, $bitmask)
    {
        if (!(is_a($parent, "net_ipv4alloc") || is_a($parent, "net_ipv6alloc"))) {
            die("Bad!");
        }
        for ($x = 30; $x >= 16; $x = $x - 1)
            $magic[$x] = pow(2, 32 - $x);
        for ($x = 64; $x >= 31; $x = $x - 4) 
            $magic[$x] = pow(2, 64 - $x);
        
        $test = $parent->long;
        if (sizeof($parent->children) == 0) {
            if ($parent->type == "IPv4")
                return Net_IPv4Alloc::parseAddress($parent->ip ."/". $bitmask);
            elseif ($parent->type == "IPv6")
                return Net_IPv6Alloc::parseAddress($parent->ip ."/". $bitmask);
        }
        usort($parent->children, array($this, "sortChildren"));
        // Only test as long as our possible netblock is inside the parent
        while ($test + $magic[$bitmask] <= $parent->long + $magic[$parent->bitmask]) {
            // Check against all current allocations
            foreach ($parent->children as $child) {
                if ($bitmask > $child->bitmask) {
                    // See if test is in the child
                    if ($parent->type == "IPv4") {
                        if (Net_IPv4Alloc::ipInNetwork(long2ip($test), $child)) {
                            $test = $child->long + $magic[$child->bitmask];
                            continue 2;
                        }
                    } elseif ($parent->type == "IPv6") {
                        if (Net_IPv6Alloc::ipInNetwork(Net_IPv6Alloc::long2string($test, 0), $child)) {
                            $test = $child->long + $magic[$child->bitmask];
                            continue 2;
                        }
                    }
                } elseif ($bitmask < $child->bitmask) {
                    // see if test has the child inside of it
                    if ($parent->type == "IPv4") {
                        if (Net_IPv4Alloc::ipInNetwork($child->ip, long2ip($test) ."/". $bitmask)) {
                            $test = $test + $magic[$bitmask];
                            continue 2;
                        }
                    } elseif ($parent->type == "IPv6") {
                        if (Net_IPv6Alloc::ipInNetwork($child->network, Net_IPv6Alloc::long2string($test, 0) ."/". $bitmask)) {
                            $test = $test + $magic[$bitmask];
                            continue 2;
                        }
                    }
                } elseif ($bitmask == $child->bitmask) {
                    // Check to see if they're they same
                    if ($test == $child->long) {
                        $test = $test + $magic[$bitmask];
                        continue 2;
                    }
                }
            }
            if ($parent->type == "IPv4") 
                return Net_IPv4Alloc::parseAddress(long2ip($test) ."/". $bitmask);
            elseif ($parent->type == "IPv6")
                return Net_IPv6Alloc::parseAddress(Net_IPv6Alloc::long2string($test, 0) ."/". $bitmask);
        }
        return false;
    }

    /**
     * recurseIpTree 
     * 
     * Builds a full Net_IPv4Alloc object from a netblockId
     * 
     * @param int $netblockId   Netblock Id to serve as the root of the tree 
     * @access public
     * @return Net_IPv4Alloc|MDB2_Error Net_IPv4Alloc on success, an MDB2 error on failure
     */
    public function recurseIpTree($netblockId) {
        $tree = $this->getNetblock($netblockId);
        if ($tree->isLeaf)
            return $tree;
        $children = $this->getChildren($netblockId);
        if (sizeof($children) == 0)
            return $tree;
        foreach ($children as $child) {
            $childBlock = $this->getNetblock($child);
            if ($childBlock->isLeaf) 
                $tree->children[] = $childBlock;
            else
                $tree->children[] = $this->recurseIpTree($child);
        }
        usort($tree->children, array($this, "sortChildren"));
        return $tree;
    }
    /**
     * reassignNetblock 
     *
     * Updates the objectId for a specific netblock
     * 
     * @param int $netblockId 
     * @param int $objectId 
     * @access public
     * @return mixed            Number of updated rows on success, MDB2 error on DB faiure
     *                          or false if there is no netblock by that ID
     */
    public function reassignNetblock($netblockId, $objectId) {
        // Check to make sure the netblock exists
        if ($db->query("SELECT * FROM allocation WHERE netblockId = $netblockId")->numRows() > 0) {
            $update = $db->prepare("UPDATE allocation SET objectId = ? WHERE netblockId = ?", array("integer", "integer"));
            $result = $update->execute(array($objectId, $netblockId));
            return $result;
        } else {
            return false;
        }
    }
    /**
     * sortChildren 
     * 
     * Callback function to sort children of a parent netblock
     * 
     * @param mixed $a 
     * @param mixed $b 
     * @access public
     * @return void
     */
    function sortChildren($a, $b) {
        if ($a->bin > $b->bin)
            return 1;
        elseif ($a->bin < $b->bin)
            return -1;
        else
            return 0;
    }
}
