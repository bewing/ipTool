<?
/**
 * This class implements a similar interface for IPv6
 * as IPv6 for ipTool to use in a dual-stack environment
 * Leans heavily on statically called Net_IPv6 functions
 *
 * PHP version 5
 * @category    Net
 * @package     ipTool
 * @author      Brandon Ewing <nicotine@warningg.com>
 * @filesource
 */

require_once("Net/IPv6.php");

/**
 * IPv6 with recursion class
 *
 * @package     ipTool
 * @category    Net
 * @author      Brandon Ewing <nicotine@warningg.com>
 */

class Net_IPv6Alloc
{

    public $type = "IPv6";
    public $ip = "";
    /**
     *
     * Compressed IPv6 address
     *
     * @var string
     * @access public
     */

    public $bitmask = false;
    /**
     *
     * Bitmask in integer format
     *
     * @var int
     * @access public
     */

    public $network = "";
    /**
     *
     * Compressed IPv6 address
     *
     * @var string
     * @access public
     */

    public $long = false;
    /**
     *
     * Upper 64 bits of address
     *
     * @var int
     * @access public
     */

    public $long2 = false;
    /**
     *
     * Lower 64 bits of address
     *
     * @var int
     * @access public
     */

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

    function checkConsistency()
    {
        $inconsistent = array ();
        // No children?  We're good.
        if (sizeof($this->children < 1))
            return true;
        foreach ($this->children as $child) {
            if (!Net_IPv6Alloc::ipInNetwork($child->ip, $this)) {
                $inconsistent[] = $child;
            }
            if ($sizeof($child->children > 0)) {
                $subCheck = $child->checkConsistency();
                if (is_array($subCheck))
                    $inconsistent = array_merge($inconsistent, $subCheck);
                unset($subCheck);
            }
        }
        if (sizeof($inconsistent) > 0) 
            return $inconsistent;
        else
            return true;
    }

    function calculate()
    {
        if (! is_a($this, "net_ipv6alloc")) {
            $myself = new Net_IPv4Alloc;
            return PEAR::raiseError("cannot calculate on uninstantiated Net_IPv6Alloc Class");
        }
        $uncompressed = Net_IPv6::uncompress($this->ip, TRUE);
        $this->long = hexdec(substr($uncompressed, 0, 19));
        $this->long2 = hexdec(substr($uncompressed, 19));
        $minmax = Net_IPv6::parseAddress($this->ip ."/". $this->bitmask);
        $this->network = Net_IPv6::compress($minmax['start']);
        $this->broadcast = Net_IPv6::compress($minmax['end']);
        return true;
    }


    function parseAddress($address)
    {
        $myself = new Net_IPv6Alloc;
        if (strchr($address, "/")) {
            $parts = explode("/", $address);
            if (! Net_IPv6::checkIPv6($parts[0])) {
                return PEAR::raiseError("invalid IP address");
            }
            $myself->ip = Net_IPv6::compress($parts[0]);

            // Check the style of netmask that was entered
            /*
             *  a CIDR bitmask type was entered
             */
            if ($parts[1] >= 0 && $parts[1] <= 128) {
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
        if (! is_a($network, "net_ipv6alloc")) {
            $network = Net_IPv6Alloc::parseAddress($network);
        }

        if (Net_IPv6::isInNetmask($network->ip ."/". $network->bitmask, $ip))
            return true;
        else 
            return false;
    }
    function long2string($long1, $long2)
    {
        $string = '';
        $hex1 = dechex($long1);
        $hex1 = str_pad($hex1, 16, "0", STR_PAD_LEFT);
        $hex2 = dechex($long2);
        $hex2 = str_pad($hex2, 16, "0", STR_PAD_LEFT);
        $parts = str_split($hex1, 4);
        foreach ($parts as $y) 
            $string .= $y .":";
        $parts = str_split($hex2, 4);
        foreach ($parts as $y) 
            $string .= $y .":";
        return substr($string, 0, -1);
    }
}

