<?
/** 
 * This file contains code to add recursion (and consistancy checking
 * on that recursion) to Net_IPv4.
 * 
 * PHP version 5
 * @category    Net
 * @package     ipTool
 * @author      Brandon Ewing <nicotine@warningg.com>
 * @filesource
 */

require_once("Net/IPv4.php");

/**
 * IPv4 with recursion class
 * 
 * @package     ipTool
 * @category    Net
 * @author      Brandon Ewing <nicotine@warningg.com>
 */
class Net_IPv4Alloc extends Net_IPv4
{

    public $type = "IPv4";
    public $netblockId;
    /** 
     * Array of suballocations
     *
     * @var array
     */
    public $children = array();

    /**
     * parentId 
     * 
     * @var integer
     * @access public
     */
    public $parentId;

    /**
     * isLeaf 
     * 
     * @var boolean
     * @access public
     */
    public $isLeaf;

    public $objectId;

    /**
     * vrf 
     * 
     * @var string
     * @access public
     */
    public $vrf;

    /**
     * checkConsistency 
     * 
     * @access public
     * @return mixed
     */

    function checkConsistency()
    {
        $inconsistant = array();
        // No children?  We're good.
        if (sizeof($this->children < 1)) {
            return true;
        }
        foreach ($this->children as $child) {
            if (!parentId::ipInNetwork($child->ip, $this)) {
                $inconsistant[] = $child;
            }
            if (sizeof($child->children > 0)) {
                $subCheck = $child->checkConsistency();
                if (is_array($subCheck))
                    $insconsistant = array_merge($inconsistant, $subCheck);
                unset($subCheck);
            }
        }
        if (sizeof($inconsistant) > 0) {
            return $inconsistant;
        } else {
            return true;
        }

    }
    function parseAddress($address)
    {
        $myself = new Net_IPv4Alloc;
        if (strchr($address, "/")) {
            $parts = explode("/", $address);
            if (! $myself->validateIP($parts[0])) {
                return PEAR::raiseError("invalid IP address");
            }
            $myself->ip = $parts[0];

            // Check the style of netmask that was entered
            /*
             *  a hexadecimal string was entered
             */
            if (preg_match("/^([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i", $parts[1], $regs)) {
                // hexadecimal string
                $myself->netmask = hexdec($regs[1]) . "." .  hexdec($regs[2]) . "." .
                    hexdec($regs[3]) . "." .  hexdec($regs[4]);

            /*
             *  a standard dot quad netmask was entered.
             */
            } else if (strchr($parts[1], ".")) {
                if (! $myself->validateNetmask($parts[1])) {
                    return PEAR::raiseError("invalid netmask value");
                }
                $myself->netmask = $parts[1];

            /*
             *  a CIDR bitmask type was entered
             */
            } else if ($parts[1] >= 0 && $parts[1] <= 32) {
                // bitmask was entered
                $myself->bitmask = $parts[1];

            /*
             *  Some unknown format of netmask was entered
             */
            } else {
                return PEAR::raiseError("invalid netmask value");
            }
            $myself->calculate();
            return $myself;
        } else if ($myself->validateIP($address)) {
            $myself->ip = $address;
            return $myself;
        } else {
            return PEAR::raiseError("invalid IP address");
        }
    }
    function ipInNetwork($ip, $network)
    {
        if (! is_object($network) || strcasecmp(get_class($network), 'net_ipv4alloc') <> 0) {
            $network = Net_IPv4Alloc::parseAddress($network);
        }

        $net = Net_IPv4::ip2double($network->network);
        $bcast = Net_IPv4::ip2double($network->broadcast);
        $ip = Net_IPv4::ip2double($ip);
        unset($network);
        if ($ip >= $net && $ip <= $bcast) {
            return true;
        }
        return false;
    }
}
