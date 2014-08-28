<?


require_once("MDB2.php");
require_once("../IPv4Alloc.php");
require_once("../IPv6Alloc.php");
require_once("../ipTool.php");

$dsn = array("phptype"          => "mysql",
             "hostspec"         => "localhost",
             "database"         => "ipTool",
             "username"         => "ipTool",
             "password"         => "bTmnZalQrD");

$db = MDB2::singleton($dsn, array("portability" => MDB2_PORTABILITY_ALL ^ MDB2_PORTABILITY_FIX_CASE));
if (PEAR::isError($db)) {
    die($db->getMessage());
}


$test = new ipTool;
$test->db = $db;
$result = $test->getNetblock(2);
print_r($result);
unset($result->netblockId);
$result2 = $test->getNetblockId($result);
print_r($result);
$getAssigned = $test->getAssignedNetblocks($result->objectId);
print_r($getAssigned);
$getNonleaf = $test->getNonleafNetblocks();
print_r($getNonleaf);
$child = $test->getChildren($result->netblockId);
print_r($child);
$tree = $test->recurseIpTree($result->netblockId);
print_r($tree);
$free = $test->findFreeAllocation($tree, 29);
print_r($free);
//$new = $test->assignNetblock(3, 2, 29, TRUE, "Test allocation");
//print_r($new);

$v6 = $test->getNetblock(1);
print_r($v6);
unset($v6->netblockId);
$v62 = $test->getNetblockId($v6);
print_r($v6);
$child = $test->getChildren($v6->netblockId);
print_r($child);

$ipv6tree = $test->recurseIpTree($v6->netblockId);
print_r($ipv6tree);

$free = $test->findFreeAllocation($ipv6tree, 64);
print_r($free);

$new = $test->assignNetblock(3, 1, 64, TRUE, "Test IPv6 allocation");
print_r($new);
