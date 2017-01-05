<?php

if (file_exists(__DIR__ . "/vendor/autoload.php")) {
    /* Testing for packege */
    require_once(__DIR__ . "/vendor/autoload.php");
} elseif (file_exists(__DIR__ . "/../Require.php")) {
    /* Testing for local source tree */
    require_once(__DIR__ . '/../Require.php');
}

use BizStation\Transactd\Transactd;
use BizStation\Transactd\Database;
use BizStation\Transactd\Tabledef;
use BizStation\Transactd\GroupQuery;
use BizStation\Transactd\RecordsetQuery;
use BizStation\Transactd\Count;
use BizStation\Transactd\Query;
use BizStation\Transactd\BtrVersions;
use Transactd\Model;
use Transactd\IOException;
use Transactd\Collection;


class Address
{

}

class Cust extends Model
{
    protected static $table = 'customers';
    protected static $aliases = ['名前' => 'name'];
    protected static $guarded = ['note'];
    protected $phone;
    public function setPhone($v)
    {
        $this->phone = $v;
    }
    public function getPhone()
    {
        return $this->phone;
    }
    public static function creating($user)
    {
        //echo 'Cust creating called user->id = '.$user->id.PHP_EOL;
        return false;
    }
    
    public function newCollection(array $models, $a, $b)
    {
        return new Collection($models, $a, $b);
    }
}

class Customer extends Model
{
    /** @var Addresss */
    private $hoge = '123';
    protected $address = null;
    protected $address3 = null;
    protected static $aliases = ['名前' => 'name'];
    protected static $fillable = ['id','name','option','phone'];
    //public static $serialize = ['name'];
    /**
     *
     * @var array field name => property name of object. Do not use __get __set magic method
     * Map the variable is required. If the variable is null then field values are not read or write.
     */
    static protected $transferMap = ['zip' => 'address', 'address1' => 'address', 'address2' => 'address3'];
    protected $id;
    protected $name;
    
    public function __construct()
    {
        parent::__construct();
        $this->address = new Address();
    }
    public static function created($user)
    {
        //echo 'Customer created called user->id = '.$user->id.PHP_EOL;
    }
    
    public function scopeParent($q, $parent)
    {
        $q->where('parent', $parent);
    }
    
    public function extension()
    {
        return $this->hasOne('\Extension', 0, null, false);
    }
    
    public function ext()
    {
        return $this->hasOne('\Extension', 'id');
    }
    
    public function followings()
    {
        return $this->hasMany('Follower', 'following_id');
    }
    
    public function followings2()
    {
        return $this->hasMany('Follower', ['following_id', 'followed_id']);
    }
    
    public function grp()
    {
        return $this->belongsTo('Group', 'group', 'id');
    }

    public function followings3()
    {
        return $this->belongsToMany('Customer', 'Follower', 'following_id', 'followed_id');
    }
    
    public function followings4()
    {
        return $this->hasMany('Follower', 'following_id')->addSubRelation('Customer', 0, 'followed_id');
    }
    
    public function comments()
    {
        return $this->morphMany('Comment', 'parent');
    }
    
    public function comments2()
    {
        return $this->morphMany('Comment', 'parent', null, null, ['[1]', 'id']);
    }
    
    public function tags()
    {
        return $this->morphToMany('Tag', 'taggable');
    }

    public function tags2()
    {
        return $this->relation('Taggable', 1, ['[Customer]', 'id'])->addSubRelation('Tag', 0, 'tag_id');
    }
    
    public function __get($var)
    {
        if ($var === 'id') {
            return $this->id;
        }
        if ($var === 'name') {
            return $this->name;
        }
        return parent::__get($var);
    }
    
    public function __set($name, $value)
    {
        $this->{$name} = $value;
    }

    public function address()
    {
        return $this->address;
    }
}

class Follower extends Model
{
    //protected $primaryKey = ['following_id', 'followed_id'];
    public $timestamps = false;
    public static function creating($folower)
    {
        //echo 'Follower creating called  folower->following_id = '.$folower->following_id.PHP_EOL;
        return true;
    }
    
    public function followers()
    {
        return $this->relation('Customer', 0, 'followed_id'); // Easy understand
    }
}

class Extension extends Model
{
    protected static $guarded = [];
    
    public function customer()
    {
        return $this->belongsTo('Customer', 'id', 0, false);
    }
    
    public function cust()
    {
        return $this->belongsTo('Customer', 'id');
    }
}

class Group extends Model
{
    protected static $guarded = [];
    public function customers()
    {
        return $this->relation('Customer', 1, 'id'); // Easy understand
        //return $this->hasMany('Customer', 'group');
    }
    
    public function cust()
    {
        return $this->relation('Cust', 1, 'id'); // Easy understand
        //return $this->hasMany('Cust', 'group');
    }

    public function newCollection(array $models, $a, $b)
    {
        return new Collection($models, $a, $b);
    }
    
    public function comments()
    {
        return $this->morphMany('Comment', 'parent');
    }
    
    public function comments2()
    {
        return $this->morphMany('Comment', 'parent', null, null, ['[2]', 'id']);
    }
    
    public function tags()
    {
        return $this->morphToMany('Tag', 'taggable');
    }
    
    public function tags2()
    {
        return $this->relation('Taggable', 1, ['[Group]', 'id'])->addSubRelation('Tag', 0, 'tag_id');
    }
}

class User extends Model
{
    protected static $connection = 'q2';
    protected static $table = 'user';
    protected static $aliases = ['名前' => 'name'];
    
    public function newCollection(array $models, $a, $b)
    {
        return new Collection($models, $a, $b);
    }
}

class Comment extends Model
{
    public function followers()
    {
        return $this->relation('Customer', 0, 'followed_id'); // Easy understand
    }

    public function commentable()
    {
        return $this->morphTo('parent');
    }
    
    public function commentable2()
    {
        return $this->morphTo('parent', null, null, [1 => 'Customer', 2 => 'Group']);
    }
}

class Tag extends Model
{
    public function customers()
    {
        return $this->morphedByMany('Customer', 'taggable');
    }

    public function groups()
    {
        return $this->morphedByMany('Group', 'taggable');
    }
}

class Taggable extends Model
{
}


function getHost()
{
    $host = getenv('TRANSACTD_PHPUNIT_HOST');
    if (strlen($host) == 0) {
        $host = 'localhost/';
    }
    if ($host[strlen($host) - 1] != '/') {
        $host = $host . '/';
    }
    return $host;
}


define("HOSTNAME", getHost());
define("USERNAME", getenv('TRANSACTD_PHPUNIT_USER'));
define("USERPART", strlen(USERNAME) == 0 ? '' : USERNAME . '@');
define("PASSWORD", getenv('TRANSACTD_PHPUNIT_PASS'));
define("PASSPART", strlen(PASSWORD) == 0 ? '' : '&pwd=' . PASSWORD);
define("DBNAME", "ormtest");
define("PROTOCOL", "tdap://");
define("BDFNAME", "?dbfile=test.bdf");
define("URI", PROTOCOL . USERPART . HOSTNAME . DBNAME . BDFNAME . PASSPART);
define("URIQ", PROTOCOL . USERPART . HOSTNAME . "querytest" . BDFNAME . PASSPART);

function init()
{
    class_alias('Transactd\DatabaseManager', 'DB');
    try {
        DB::connect(URI, URI, 'default', true);
        DB::connect(URIQ, null, 'q2');
        $users = User::keyValue(20001)->where('id', '>', 20000);
        $users->delete();
    } catch (\Exception $e) {
        echo PHP_EOL.$e.PHP_EOL;
    }
}

function checkStat($obj)
{
    if ($obj->stat()) {
        throw new IOException($obj->statMsg(), $obj->stat());
    }
}

function insertTable($def, $tableid, $name)
{
    $td = new Tabledef();
    $td->schemaCodePage = Transactd::CP_UTF8;
    $td->setTableName($name);
    $td->setFileName($name);
    $td->charsetIndex = Transactd::CHARSET_UTF8;
    $td->id = $tableid;
    $def->insertTable($td);
    checkStat($def);
}

function addField($def, $tableid, $name, $type, $len, $nullable=false)
{
    $fd = $def->insertField($tableid, $def->tableDefs($tableid)->fieldCount);
    $fd->setName($name);
    $fd->type = $type;
    if ($type === Transactd::ft_myvarchar) {
        $fd->setLenByCharnum($len);
    } else {
        $fd->len = $len;
    }
    if ($name === 'updated_at') {
        //$fd->setTimeStampOnUpdate(false);
        $fd->setDefaultValue(Transactd::DFV_TIMESTAMP_DEFAULT);
    } elseif ($name === 'created_at') {
        //$fd->setTimeStampOnUpdate(true);
        $fd->setDefaultValue(Transactd::DFV_TIMESTAMP_DEFAULT);
    } elseif ($nullable === true) {
        $fd->setNullAble(true);
    }
    checkStat($def);
    return $fd;
}

function makeCustData($db)
{
    $tb = $db->openTable('customers', Transactd::TD_OPEN_NORMAL);
    checkStat($db);
    $tb->clearBuffer();
    for ($i = 1; $i <= 10000; ++$i) {
        $fdi = 0;
        $tb->setFV($fdi++, 0);                  //id
        $tb->setFV($fdi++, 'User'.$i);          //name
        $tb->setFV($fdi++, (($i-1) % 5) + 1);   //group
        $tb->setFV($fdi++, $i);                 //option
        $tb->setFV($fdi++, '123-'.($i-1));       //zip
        $tb->setFV($fdi++, $i);                 //addrss1
        $tb->setFV($fdi++, $i);                 //address2
        if (($i % 100) === 0) {
            $tb->setFVNull($fdi++, true);       //phone
        } else {
            $tb->setFV($fdi++, $i);
        }
        $tb->setFV($fdi++, '');                 //note
        if ($i === 1) {
            $tb->setFVNull($fdi, true);         //special_following
        } 
        else {
            $tb->setFV($fdi, $i - 1);           //special_following
        } 
        $tb->insert();
        checkStat($tb);
    }
}

function makeExtData($db)
{
    $tb = $db->openTable('extensions', Transactd::TD_OPEN_NORMAL);
    checkStat($db);
    $tb->clearBuffer();
    for ($i = 1; $i <= 10000; ++$i) {
        $fdi = 0;
        $tb->setFV($fdi++, 0);
        $tb->setFV($fdi++, (($i -1) % 10) + 1);
        $tb->setFV($fdi++, '');
        $tb->insert();
        checkStat($tb);
    }
    $tb->release();
}

function makeFollowersData($db)
{
    $tb = $db->openTable('followers', Transactd::TD_OPEN_NORMAL);
    checkStat($db);
    $tb->clearBuffer();
    for ($i = 1; $i <= 10000; ++$i) {
        $fdi = 0;
        $id = ($i % 200) + 1;
        $tb->setFV($fdi++, $id);
        $tb->setFV($fdi++, $i);
        if ($id !== $i) {
            $tb->insert();
        }
        checkStat($tb);
    }
}

function makeGrpData($db)
{
    $tb = $db->openTable('groups', Transactd::TD_OPEN_NORMAL);
    checkStat($db);
    $tb->clearBuffer();
    for ($i = 1; $i <= 5; ++$i) {
        $fdi = 0;
        $tb->setFV($fdi++, $i);
        $tb->setFV($fdi++, 'Group '.$i);
        $tb->insert();
        checkStat($tb);
    }
}
$mysql55 = false;

function createTestData()
{
    try {

        echo PHP_EOL.'URI='.URI.PHP_EOL;
        $db = new Database();
        $db->create(URI);
        if ($db->stat() === Transactd::STATUS_TABLE_EXISTS_ERROR) {
            //return true;
            $db->drop(URI);
            $db->create(URI);
            checkStat($db);
        }
        
        $db->open(URI, Transactd::TYPE_SCHEMA_BDF, Transactd::TD_OPEN_NORMAL);
        checkStat($db);
        
        $vv = new BtrVersions();
        $db->getBtrVersion($vv);
        $server_ver = $vv->version(1);
        $GLOBALS['$mysql55'] = ($server_ver->majorVersion === 5) && ($server_ver->minorVersion === 5) 
                    /*&& (chr($server_ver->type) == 'M')*/;
        
        $def = $db->dbDef();
        $tableid = 1;
        // customers
        insertTable($def, $tableid, 'customers');
        addField($def, $tableid, 'id', Transactd::ft_autoinc, 4);
        addField($def, $tableid, '名前', Transactd::ft_myvarchar, 30);
        addField($def, $tableid, 'group', Transactd::ft_integer, 4);
        addField($def, $tableid, 'option', Transactd::ft_integer, 4);
        addField($def, $tableid, 'zip', Transactd::ft_myvarbinary, 10);
        addField($def, $tableid, 'address1', Transactd::ft_myvarchar, 30);
        addField($def, $tableid, 'address2', Transactd::ft_myvarchar, 30);
        addField($def, $tableid, 'phone', Transactd::ft_integer, 4, true);
        addField($def, $tableid, 'note', Transactd::ft_myvarbinary, 100);
        addField($def, $tableid, 'special_following', Transactd::ft_integer, 4, true);
        $fd = addField($def, $tableid, 'update_at', Transactd::ft_mytimestamp, 7, false);
        $fd->setDefaultValue(Transactd::DFV_TIMESTAMP_DEFAULT);
        $fd->setTimeStampOnUpdate(true);
        $keynum = 0;
        $kd = $def->insertKey($tableid, $keynum);
        $kd->segment(0)->fieldNum = 0;
        $kd->segment(0)->flags->bit8 = 1;
        $kd->segment(0)->flags->bit1 = 1;
        $kd->segmentCount = 1;
        $def->updateTableDef($tableid);

        $keynum = 1;
        $kd = $def->insertKey($tableid, $keynum);
        $kd->segment(0)->fieldNum = 2; //group
        $kd->segment(0)->flags->bit8 = 1;
        $kd->segment(0)->flags->bit1 = 1;
        $kd->segment(0)->flags->bit0 = 1;
        $kd->segmentCount = 1;

        $td = $def->tableDefs($tableid);
        $td->primaryKeyNum = 0;
        $def->updateTableDef($tableid);
        checkStat($def);
        makeCustData($db);
        
        $tableid = 2;
        // extension
        insertTable($def, $tableid, 'extensions');
        addField($def, $tableid, 'id', Transactd::ft_autoinc, 4);
        addField($def, $tableid, 'parent', Transactd::ft_integer, 4);
        addField($def, $tableid, 'hobby', Transactd::ft_myvarchar, 30);
        $keynum = 0;
        $kd = $def->insertKey($tableid, $keynum);
        $kd->segment(0)->fieldNum = 0;
        $kd->segment(0)->flags->bit8 = 1;
        $kd->segment(0)->flags->bit1 = 1;
        $kd->segmentCount = 1;
        $td = $def->tableDefs($tableid);
        $td->primaryKeyNum = $keynum;
        $def->updateTableDef($tableid);
        checkStat($def);
        makeExtData($db);
        // followers
        $tableid = 3;
        insertTable($def, $tableid, 'followers');
        addField($def, $tableid, 'following_id', Transactd::ft_uinteger, 4);
        addField($def, $tableid, 'followed_id', Transactd::ft_uinteger, 4);
        addField($def, $tableid, 'note', Transactd::ft_myvarbinary, 30);
        addField($def, $tableid, 'updated_at', Transactd::ft_mytimestamp, 7);
        if (!$GLOBALS['$mysql55']) {
            addField($def, $tableid, 'created_at', Transactd::ft_mytimestamp, 7);
        }
        $keynum = 0;
        $kd = $def->insertKey($tableid, $keynum);
        $kd->segment(0)->fieldNum = 0;
        $kd->segment(0)->flags->bit8 = 1;
        $kd->segment(0)->flags->bit1 = 1;
        $kd->segment(1)->fieldNum = 1;
        $kd->segment(1)->flags->bit8 = 1;
        $kd->segment(1)->flags->bit1 = 1;
        $kd->segmentCount = 2;
        $keynum = 1;
        $kd = $def->insertKey($tableid, $keynum);
        $kd->segment(0)->fieldNum = 0;
        $kd->segment(0)->flags->bit8 = 1;
        $kd->segment(0)->flags->bit1 = 1;
        $kd->segment(0)->flags->bit0 = 1;
        $kd->segmentCount = 1;
        $keynum = 2;
        $kd = $def->insertKey($tableid, $keynum);
        $kd->segment(0)->fieldNum = 1;
        $kd->segment(0)->flags->bit1 = 1;
        $kd->segment(0)->flags->bit0 = 1;
        $kd->segmentCount = 1;
        $td = $def->tableDefs($tableid);
        $td->primaryKeyNum = 0;
        $def->updateTableDef($tableid);
        checkStat($def);
        makeFollowersData($db);
        
        //group
        $tableid = 4;
        insertTable($def, $tableid, 'groups');
        addField($def, $tableid, 'id', Transactd::ft_autoinc, 4);
        addField($def, $tableid, 'name', Transactd::ft_myvarbinary, 30);
        $keynum = 0;
        $kd = $def->insertKey($tableid, $keynum);
        $kd->segment(0)->fieldNum = 0;
        $kd->segment(0)->flags->bit8 = 1;
        $kd->segment(0)->flags->bit1 = 1;
        $kd->segmentCount = 1;
        $td = $def->tableDefs($tableid);
        $td->primaryKeyNum = $keynum;
        $def->updateTableDef($tableid);
        checkStat($def);
        makeGrpData($db);
        
        $tableid = 5;
        insertTable($def, $tableid, 'comments');
        addField($def, $tableid, 'id', Transactd::ft_autoinc, 4);
        addField($def, $tableid, 'parent_type', Transactd::ft_myvarbinary, 30);
        addField($def, $tableid, 'parent_id', Transactd::ft_uinteger, 4);
        addField($def, $tableid, 'note', Transactd::ft_myvarbinary, 200);
        addField($def, $tableid, 'created_at', Transactd::ft_mytimestamp, 7);
        $keynum = 0;
        $kd = $def->insertKey($tableid, $keynum);
        $kd->segment(0)->fieldNum = 0;
        $kd->segment(0)->flags->bit8 = 1;
        $kd->segment(0)->flags->bit1 = 1;
        $kd->segmentCount = 1;
        $keynum = 1;
        $kd = $def->insertKey($tableid, $keynum);
        $kd->segment(0)->fieldNum = 1;
        $kd->segment(0)->flags->bit8 = 1;
        $kd->segment(0)->flags->bit1 = 1;
        $kd->segment(0)->flags->bit0 = 1;
        $kd->segment(1)->fieldNum = 2;
        $kd->segment(1)->flags->bit8 = 1;
        $kd->segment(1)->flags->bit1 = 1;
        $kd->segment(1)->flags->bit0 = 1;
        $kd->segmentCount = 2;

        $td = $def->tableDefs($tableid);
        $td->primaryKeyNum = 0;
        $def->updateTableDef($tableid);
        checkStat($def);
        
        
        $tableid = 6;
        insertTable($def, $tableid, 'tags');
        addField($def, $tableid, 'id', Transactd::ft_autoinc, 4);
        addField($def, $tableid, 'name', Transactd::ft_myvarchar, 30);
        $keynum = 0;
        $kd = $def->insertKey($tableid, $keynum);
        $kd->segment(0)->fieldNum = 0;
        $kd->segment(0)->flags->bit8 = 1;
        $kd->segment(0)->flags->bit1 = 1;
        $kd->segmentCount = 1;
        $td = $def->tableDefs($tableid);
        $td->primaryKeyNum = 0;
        $def->updateTableDef($tableid);
        checkStat($def);

        $tableid = 7;
        insertTable($def, $tableid, 'taggables');
        addField($def, $tableid, 'tag_id', Transactd::ft_uinteger, 4);
        addField($def, $tableid, 'taggable_type', Transactd::ft_myvarchar, 20);
        addField($def, $tableid, 'taggable_id', Transactd::ft_uinteger, 4);
        $keynum = 0;
        $kd = $def->insertKey($tableid, $keynum);
        $kd->segment(0)->fieldNum = 0;
        $kd->segment(0)->flags->bit8 = 1;
        $kd->segment(0)->flags->bit1 = 1;
        $kd->segment(1)->fieldNum = 1;
        $kd->segment(1)->flags->bit8 = 1;
        $kd->segment(1)->flags->bit1 = 1;
        $kd->segment(2)->fieldNum = 2;
        $kd->segment(2)->flags->bit8 = 1;
        $kd->segment(2)->flags->bit1 = 1;
        $kd->segmentCount = 3;
        $keynum = 1;
        $kd = $def->insertKey($tableid, $keynum);
        $kd->segment(0)->fieldNum = 1;
        $kd->segment(0)->flags->bit8 = 1;
        $kd->segment(0)->flags->bit1 = 1;
        $kd->segment(0)->flags->bit0 = 1;
        $kd->segment(1)->fieldNum = 2;
        $kd->segment(1)->flags->bit8 = 1;
        $kd->segment(1)->flags->bit1 = 1;
        $kd->segment(1)->flags->bit0 = 1;
        $kd->segmentCount = 2;
        $td = $def->tableDefs($tableid);
        $td->primaryKeyNum = 0;
        $def->updateTableDef($tableid);
        checkStat($def);
        return true;
    } catch (\Exception $e) {
        echo $e;
    }
    return false;
}

if (!createTestData()) {
    return 1;
}

init();

function getCount($records)
{
    $n = 0;
    foreach($records as $record) {
        ++$n;
    }
    return $n;
}

class TransactdTest extends PHPUnit_Framework_TestCase
{
   
    public function testCustomerAll()
    {
        $customers = Customer::all();
        $this->assertEquals(count($customers), 10000);
        $this->assertEquals($customers[0]->id, 1);
        $this->assertEquals($customers[9999]->id, 10000);
        $this->assertEquals($customers[9999]->name, 'User10000');
        $this->showMemoryUsage();
    }
    
    // The Cust class is specify table name
    public function testTablename()
    {
        $customers = Cust::all();
        $this->assertEquals(count($customers), 10000);
        $this->assertEquals($customers[0]->id, 1);
        $this->assertEquals($customers[9999]->id, 10000);
        $this->assertEquals($customers[9999]->name, 'User10000');
        $this->showMemoryUsage();
    }
    
    // The Cust class is specify table name
    public function testProtected()
    {
        $customers = Cust::find(2);
        $this->assertEquals($customers->id, 2);
        $customers->setPhone(5);
        $customers->save();
        Cust::clear();
        $customers = Cust::find(2);
        $this->assertEquals($customers->id, 2);
        $this->assertEquals($customers->getPhone(), 5);
        $customers->setPhone(2);
        $customers->save();
        Cust::clear();
        $this->showMemoryUsage();
    }

    public function testPrimaryKey()
    {
        $follower = Follower::first();
        $this->assertEquals($follower->following_id, 1);
        $this->assertEquals($follower->followed_id, 200);
        $this->assertEquals(Follower::table()->keyNum(), 0);
        $follower = Follower::index(1)->keyValue(10)->where('following_id', 10)->first();
        $this->assertEquals($follower->following_id, 10);
        $this->showMemoryUsage();
    }

    public function testMultiSegPrimaryKey()
    {
        $follower = Follower::find([2, 1]);
        $this->assertEquals($follower->following_id, 2);
        $this->assertEquals($follower->followed_id, 1);
        
        $follower = Follower::find([1, 10000]);
        $this->assertEquals($follower->following_id, 1);
        $this->assertEquals($follower->followed_id, 10000);
        $this->showMemoryUsage();
    }

    private function doTestTimeStamp()
    {
        $follower = Follower::find([2, 1]);
        $ctime = $follower->created_at;
        $utime = $follower->updated_at;
        $follower->note = "abc";
        
        // update utime
        $follower->timestamps = true;
        $follower->save();
        $this->assertEquals($follower->refresh(), true);
        $this->assertEquals($follower->created_at, $ctime);
        $this->assertNotEquals($follower->updated_at, $utime);
        $follower = Follower::find([2, 1]);
        $this->assertEquals($follower->created_at, $ctime);
        $this->assertNotEquals($follower->updated_at, $utime);
        $ctime = $follower->created_at;
        $utime = $follower->updated_at;
        
        // No update utime
        $follower->note = "efg";
        $follower->timestamps = false;
        $follower->save();
        $this->assertEquals($follower->refresh(), true);
        $this->assertEquals($follower->created_at, $ctime);
        $this->assertEquals($follower->updated_at, $utime);
        $follower = Follower::find([2, 1]);
        $this->assertEquals($follower->created_at, $ctime);
        $this->assertEquals($follower->updated_at, $utime);
        $this->showMemoryUsage();
    }
    
    public function testTimeStamp()
    {
        if ($GLOBALS['$mysql55']) {
            return;
        }
        $this->doTestTimeStamp();
        $this->showMemoryUsage();
    }

    public function testConnection()
    {
        DB::connect(URIQ, null, 'q2');
        $dbs = DB::connection('q2');
        $users = $dbs->queryExecuter('user')->all();
        $this->assertEquals(count($users), 20000);
        
        $users = User::all();
        $this->assertEquals(count($users), 20000);
        $this->assertEquals(get_class($users), 'Transactd\Collection');
        $this->showMemoryUsage();
    }

    public function testWhere()
    {
        $customers = Customer::where('id', '<=', '10')->get();
        $this->assertEquals(count($customers), 10);
        $this->assertEquals($customers[0]->id, 1);
        
        //first and second
        $customers = Customer::index(0)->keyValue(11)->where('id', '>', '10')
                        ->where('id', '<=', '100')->reject(1)->get();
        $this->assertEquals(count($customers), 90);
        $this->assertEquals($customers[0]->id, 11);
        
        //no operator
        $customers = Customer::index(0)->keyValue(10)->where('id', 10)
                        ->orWhere('id', 11)->reject(1)->get();
        $this->assertEquals(count($customers), 2);
        $this->assertEquals($customers[1]->id, 11);
        $this->showMemoryUsage();
    }

    public function testOrWhere()
    {
        //->orWhere('id', '=', '1010')
        $customers = Customer::index(0)->keyValue(11)->where('id', '>', '10')
                        ->where('id', '<=', '100')
                        ->orWhere('id', '=', '1000')
                        ->orWhere('id', '=', '1010')
                        ->reject(0xffff)->get();
        $this->assertEquals(count($customers), 92);
        $this->assertEquals($customers[91]->id, 1010);
        $this->showMemoryUsage();
    }
    
    public function testWhereNull()
    {
        //->whereNull('phone')
        //subsequent
        $customers = Customer::index(0)->keyValue(10)->where('id', '>=', '10')
                        ->where('id', '<=', '500')
                        ->whereNull('phone')
                        ->reject(0xffff)->get();
        $this->assertEquals(count($customers), 5);
        $this->assertEquals($customers[4]->id, 500);
        
        //first
        $customers = Customer::index(0)->whereNull('phone')
                        ->reject(0xffff)->get();
        $this->assertEquals(count($customers), 100);
        $this->assertEquals($customers[99]->id, 10000);
        $this->showMemoryUsage();
    }
    
    public function testWhereInKey()
    {
        //->whereInKey([1, 2, 3], 1)
        //first order only
        $customers = Customer::index(0)->whereInKey([100, 100, 101, 500, 5000])->get();
        $this->assertEquals(count($customers), 5);
        $this->assertEquals($customers[4]->id, 5000);
        
        $followers = Follower::index(0)->whereInKey([1], 1)->get();
        $this->assertEquals(count($followers), 50);
        $this->assertEquals($followers[49]->followed_id, 10000);
        $followers = Follower::index(0)->whereInKey([200, 199])->get();
        $this->assertEquals(count($followers), 1);
        $this->assertEquals($followers[0]->followed_id, 199);

        $followers = Follower::index(0)->whereInKey([200, 1])->get();
        $this->assertEquals(count($followers), 1);
        $this->assertEquals($followers[0], null);
        
        $followers = Follower::index(0)->whereInKey([200, 1])->removeNullRecord()->get();
        $this->assertEquals(count($followers), 0);
        $this->showMemoryUsage();
    }
    
    public function testWhereBetween()
    {
        //first order only
        $customers = Customer::index(0)->whereBetween('option', [121, 140])->reject(0xffff)->get();
        $this->assertEquals(count($customers), 20);
        $this->assertEquals($customers[19]->id, 140);
        $this->showMemoryUsage();
    }
    
    public function testWhereNotBetween()
    {
        //first order only
        $customers = Customer::index(0)->whereNotBetween('option', [121, 140])->reject(0xffff)->get();
        $this->assertEquals(count($customers), 10000-20);
        $this->assertEquals($customers[9979]->id, 10000);
        $this->showMemoryUsage();
    }
    
    public function testWhereIn()
    {
        //->whereIn('option', [1, 2, 3])
        //first order only
        $customers = Customer::index(0)->whereIn('option', [100, 100, 101, 500, 5000])
                        ->reject(Query::FULL_SCAN)->get();
        $this->assertEquals(count($customers), 4);
        $this->assertEquals($customers[3]->id, 5000);
        
        //subsequent orWhere
        $customers = Customer::index(0)->whereIn('option', [100, 100, 101, 500, 5000])
                        ->orWhere('id', 1000)
                        ->reject(Query::FULL_SCAN)->get();
        $this->assertEquals(count($customers), 5);
        $this->assertEquals($customers[4]->id, 5000);
        $this->showMemoryUsage();
    }
    
    public function testWhereNotIn()
    {
        //->whereNotIn('option', [1, 2, 3])
        $customers = Customer::index(0)->whereNotIn('option', [100, 100, 101, 500, 5000])
                        ->reject(Query::FULL_SCAN)->get();
        $this->assertEquals(count($customers), 9996);
        $this->assertEquals($customers[9995]->id, 10000);
        
        //subsequent where
        $customers = Customer::index(0)->whereNotIn('option', [100, 100, 101, 500, 5000])
                        ->where('phone', '<!=>', '')
                        ->reject(Query::FULL_SCAN)->get();
        $this->assertEquals(count($customers), 9899);
        $this->assertEquals($customers[9898]->id, 9999);
        $this->showMemoryUsage();
    }

    public function testWhereColumn()
    {
        //->whereColumn('option', 'id')
        $customers = Customer::index(0)->whereColumn('option', 'id')
                        ->reject(Query::FULL_SCAN)->get();
        $this->assertEquals(count($customers), 10000);
        $this->assertEquals($customers[9999]->id, 10000);
        
        $customers = Customer::index(0)->whereColumn('option', 'phone')
                        ->reject(Query::FULL_SCAN)->get();
        $this->assertEquals(count($customers), 9900);
        $this->assertEquals($customers[9899]->id, 9999);
        
        //subsequent where
        $customers = Customer::index(0)->whereColumn('option', 'phone')
                        ->where('phone', '<!=>', '')
                        ->reject(Query::FULL_SCAN)->get();
        $this->assertEquals(count($customers), 9900);
        $this->assertEquals($customers[9899]->id, 9999);
        $this->showMemoryUsage();
    }
    
    public function testQueryString()
    {
        $q = Customer::index(0)->whereColumn('option', 'phone')
                        ->where('phone', '<!=>', '')
                        ->reject(Query::FULL_SCAN);
        $this->assertEquals($q->queryString(), 'option = \'[phone]\' and phone <!=> \'\'');
        $q->reset();
        
        $q = Customer::index(0)->whereInKey([1, 2, 3, 4]);
        $this->assertEquals($q->queryString(), 'in \'1\',\'2\',\'3\',\'4\'');
        $q->reset();
        $this->showMemoryUsage();
    }
    
    public function testLogincException()
    {
        try {
            $customers = Customer::orWhere('id', 1);
            $this->assertEquals(1, 0);
        } catch (LogicException $e) {
        }
        try {
            $customers = Customer::orNull('id');
            $this->assertEquals(2, 0);
        } catch (LogicException $e) {
        }
        try {
            $customers = Customer::orNotNull('id');
            $this->assertEquals(3, 0);
        } catch (LogicException $e) {
        }
        try {
            $customers = Customer::orColumn('id', 'phone');
            $this->assertEquals(4, 0);
        } catch (LogicException $e) {
        }
        try {
            $customers = Customer::where('id', 1)->whereInKey([4]);
            $this->assertEquals(5, 0);
        } catch (LogicException $e) {
        }
        try {
            $customers = Customer::where('id', 1)->whereIn('id', [4]);
            $this->assertEquals(6, 0);
        } catch (LogicException $e) {
        }
        try {
            $customers = Customer::where('id', 1)->whereNotIn('id', [4]);
            $this->assertEquals(7, 0);
        } catch (LogicException $e) {
        }
        try {
            $customers = Customer::where('id', 1)->whereBetween('id', [1, 2]);
            $this->assertEquals(8, 0);
        } catch (LogicException $e) {
        }
        try {
            $customers = Customer::where('id', 1)->whereNotBetween('id', [1, 2]);
            $this->assertEquals(9, 0);
        } catch (LogicException $e) {
        }
        $this->showMemoryUsage();
    }

    public function testUnion()
    {   //(id >=1 and id <= 10) or (id >=31 and id <= 40)
        $rs = Customer::index(0)->keyValue(1)->where('id', '>=', 1)->where('id', '<=', 10)->recordset();
        $customers = Customer::keyValue(31)->where('id', '>=', 31)->where('id', '<=', 40)->union($rs)->get();
        $this->assertEquals(count($customers), 20);
        $this->assertEquals($customers[19]->id, 40);
        $this->showMemoryUsage();
    }
    
    public function testOrderBy()
    {
        $customers = Customer::index(0)->keyValue(1)->where('id', '>=', 1)
            ->where('id', '<=', 10)->orderBy('id', false)->get();
        $this->assertEquals(count($customers), 10);
        $this->assertEquals($customers[0]->id, 10);
        $this->assertEquals($customers[9]->id, 1);
        
        $followers = Follower::index(0)->whereInKey([1, 2], 1)
                ->orderBy('following_id', false)->orderBy('followed_id', true)->get();
        $this->assertEquals(count($followers), 100);
        $this->assertEquals($followers[0]->following_id, 2);
        $this->assertEquals($followers[0]->followed_id, 1);
        $this->assertEquals($followers[99]->following_id, 1);
        $this->assertEquals($followers[99]->followed_id, 10000);
        $this->showMemoryUsage();
    }
    
    public function testTake()
    {
        $customers = Customer::index(0)->keyValue(1)->where('id', '>=', 1)
            ->where('id', '<=', 1000)->take(10)->get();
        $this->assertEquals(count($customers), 10);
        $this->assertEquals($customers[0]->id, 1);
        $this->assertEquals($customers[9]->id, 10);
        
        $customers = Customer::take(10)->all();
        $this->assertEquals(count($customers), 10);
        $this->showMemoryUsage();
    }
    
    public function testSkip()
    {
        $customers = Customer::index(0)->keyValue(1)->where('id', '>=', 1)
            ->where('id', '<=', 1000)->take(10)->skip(20)->get();
        $this->assertEquals(count($customers), 10);
        $this->assertEquals($customers[0]->id, 21);
        $this->assertEquals($customers[9]->id, 30);
        
        $customers = Customer::skip(10)->all();
        $this->assertEquals(count($customers), 10000-10);
        $this->showMemoryUsage();
    }
    
    public function testChunk()
    {
        $n = 0;
        Customer::index(0)->keyValue(1)->where('id', '>=', 1)
            ->where('id', '<=', 100)->chunk(10,
                function ($customers) use (&$n) {
                    $this->assertEquals(count($customers), 10);
                    $this->assertEquals($customers[0]->id, $n * 10 + 1);
                    $n++;
                });
        $this->assertEquals($n, 10);
        $n = Customer::index(0)->keyValue(1)->where('id', '>=', 1)
            ->where('id', '<=', 100)->chunk(10, 'getCount');
        $this->assertEquals($n, 10);
        $this->showMemoryUsage();
    }
    
    public function testCursor()
    {
        $s = Customer::index(0)->keyValue(1)->where('id', '>=', 1)
            ->where('id', '<=', 100)->queryDescription();
        //echo PHP_EOL.($s);

        $cr = Customer::index(0)->keyValue(1)->where('id', '>=', 1)
            ->where('id', '<=', 100)->cursor();

        $i = 0;
        foreach ($cr as $c) {
            $this->assertEquals($c->id, ++$i);
        }
        $this->assertEquals($i, 100);
        $this->showMemoryUsage();
    }
    
    public function testFirst()
    {
        $customer = Customer::index(0)->first();
        $this->assertEquals($customer->id, 1);
        $this->showMemoryUsage();
    }

    public function testFind()
    {
        $customer = Customer::find(5000);
        $this->assertEquals($customer->id, 5000);
        
        $follower = Follower::find([200, 199]);
        $this->assertEquals($follower->following_id, 200);
        $this->assertEquals($follower->followed_id, 199);

        $follower = Follower::find([200, 1]);
        $this->assertEquals($follower, null);
        $this->showMemoryUsage();
    }
    
    public function testFindOrFail()
    {
        try {
            Customer::findOrFail(15000);
            $this->assertEquals(-1, 0);
        } catch (\Transactd\ModelNotFoundException $e) {
        }
        try {
            Follower::findOrFail([200, 1]);
            $this->assertEquals(-1, 1);
        } catch (\Transactd\ModelNotFoundException $e) {
        }
        $this->showMemoryUsage();
    }

    public function testSum()
    {
        $id = Customer::index(0)->keyValue(1)->where('id', '>=', 1)
            ->where('id', '<=', 10)->sum('id');
        $this->assertEquals($id, 55);
        $this->showMemoryUsage();
    }
    
    public function testMin()
    {
        $id = Customer::index(0)->keyValue(1)->where('id', '>=', 1)
            ->where('id', '<=', 10)->min('id');
        $this->assertEquals($id, 1);
        $this->showMemoryUsage();
    }
    
    public function testMax()
    {
        $id = Customer::index(0)->keyValue(1)->where('id', '>=', 1)
            ->where('id', '<=', 10)->max('id');
        $this->assertEquals($id, 10);
        $this->showMemoryUsage();
    }

    public function testAvg()
    {
        $id = Customer::index(0)->keyValue(1)->where('id', '>=', 1)
            ->where('id', '<=', 10)->avg('id');
        $this->assertEquals($id, 5.5);
        $this->showMemoryUsage();
    }

    public function testAvg2()
    {
        $rs = Customer::index(0)->keyValue(1)->where('id', '>=', 1)
            ->where('id', '<=', 10)->get(false);
        $id = \Transactd\AggregateFunction::avg($rs, 'id');
        $this->assertEquals($id, 5.5);
        $this->showMemoryUsage();
    }

    public function testSelect()
    {
        $customers = Customer::index(0)->keyValue(1)->select('id')->where('id', '>=', 1)
            ->where('id', '<=', 10)->get(false);
        $this->assertEquals($customers[0]->id, 1);
        try {
            $this->assertNotEquals($customers[0]->name, 'User1');
        } catch (\Exception $e) {
        }
    
        $q = Customer::index(0)->keyValue(1)->select('id')->where('id', '>=', 1)
            ->where('id', '<=', 10);
        $q->addSelect('name');
        $customers = $q->get();
        $this->assertEquals($customers[0]->id, 1);
        $this->assertEquals($customers[0]->name, 'User1');
        $this->showMemoryUsage();
    }
    
    public function testTableJoin()
    {
        $query = Follower::index(1)->select('followed_id');
        $customers = Customer::index(0)->keyValue(1)->select('id')->where('id', '>=', 1)
            ->where('id', '<=', 10)->join($query, ['id'])->get();
        $this->assertEquals($customers[0]->id, 1);
        $this->assertEquals($customers[0]->followed_id, 200);
        $this->assertEquals($customers[1]->followed_id, 400);
        $this->assertEquals($customers[2]->followed_id, 600);
        $this->assertEquals(count($customers), 500);
        $this->showMemoryUsage();
    }
    
    public function testRecordsetJoin()
    {
        $rs = Follower::index(1)->select('following_id', 'followed_id')->recordset();
        $rq = new RecordsetQuery;
        $rq->when('id', '=', 'following_id');
        $customers = Customer::index(0)->keyValue(1)->select('id')->where('id', '>=', 1)
            ->where('id', '<=', 10)->join($rs, $rq)->get();
        $this->assertEquals(count($customers), 500);
        $this->assertEquals($customers[0]->id, 1);
        $this->assertEquals($customers[0]->followed_id, 200);
        $this->assertEquals($customers[1]->followed_id, 400);
        $this->assertEquals($customers[2]->followed_id, 600);
        $this->showMemoryUsage();
    }
    
    public function testWhereArray()
    {
        $q = Customer::index(0)->keyValue(1)
            ->where(['id', '>=', 1])->queryString();
        $this->assertEquals($q, 'id >= \'1\'');
        Customer::reset();
        $q = Customer::index(0)->keyValue(1)
            ->where([['id', '>=', 1], ['id', '<=', 10]])->queryString();
        $this->assertEquals($q, 'id >= \'1\' and id <= \'10\'');
        Customer::reset();
        $this->showMemoryUsage();
    }
    
    public function testOrWhereArray()
    {
        $q = Customer::index(0)->keyValue(1)
            ->where(['id', '>=', 100])->orWhere(['id', '>=', 1])->queryString();
        $this->assertEquals($q, 'id >= \'100\' or id >= \'1\'');
        Customer::reset();
        $q = Customer::index(0)->keyValue(1)
            ->where(['id', '>=', 100])->orWhere([['id', '>=', 1], ['id', '<=', 10]])->queryString();
        $this->assertEquals($q, 'id >= \'100\' or id >= \'1\' or id <= \'10\'');
        Customer::reset();
        $this->showMemoryUsage();
    }
    
    public function testWhereColomun()
    {
        $q = Customer::index(0)->keyValue(1)
            ->whereColumn(['id', '>=', 'option'])->queryString();
        $this->assertEquals($q, 'id >= \'[option]\'');
        Customer::reset();
        $q = Customer::index(0)->keyValue(1)
            ->whereColumn([['id', '>=', 'option'], ['id', '<=', 'option']])->queryString();
        $this->assertEquals($q, 'id >= \'[option]\' and id <= \'[option]\'');
        Customer::reset();
        $this->showMemoryUsage();
    }
    
    public function testOrWhereColomun()
    {
        $q = Customer::index(0)->keyValue(1)
            ->where(['id', '>=', 100])->orColumn(['id', '>=', 'option'])->queryString();
        $this->assertEquals($q, 'id >= \'100\' or id >= \'[option]\'');
        Customer::reset();
        $q = Customer::index(0)->keyValue(1)
            ->where(['id', '>=', 100])->orColumn([['id', '>=', 'option'], ['id', '<=', 'option']])->queryString();
        $this->assertEquals($q, 'id >= \'100\' or id >= \'[option]\' or id <= \'[option]\'');
        Customer::reset();
        $this->showMemoryUsage();
    }
    public function testGroupBy()
    {
        $gq = new GroupQuery();
        $c = new Count('follows');
        $gq->keyField('id')->addFunction($c);
        $query = Follower::index(1)->select('followed_id');
        $customers = Customer::index(0)->keyValue(1)->select('id')->where('id', '>=', 1)
            ->where('id', '<=', 10)->join($query, ['id'])->groupBy($gq)->get();
        $this->assertEquals($customers[0]->id, 1);
        $this->assertEquals($customers[0]->follows, 50);
        $this->showMemoryUsage();
    }
    
    public function testWhen()
    {
        $id = 10;
        $f = function ($query) use ($id) {
            $query->where('id', '<=', $id);
        };
    
        $customers = Customer::index(0)->keyValue(1)->select('id')->where('id', '>=', 1)
            ->when(1, $f)->get();
        $this->assertEquals(count($customers), 10);
        
        $customers = Customer::index(0)->keyValue(1)->select('id')->where('id', '>=', 1)
            ->when(0, $f)->get();
        $this->assertEquals(count($customers), 10000);
        $this->showMemoryUsage();
    }
    
    public function testSave()
    {
        $user = new User;
        $user->name = 'User10001';
        $this->assertEquals($user->save(), true);
        $this->assertEquals($user->delete(), true);
        $customer = new Customer;
        $customer->name = 'User10001';
        
        $this->assertEquals($customer->save(), true);
        $this->assertEquals($customer->delete(), true);
        $this->showMemoryUsage();
    }
    
    public function testUpdate()
    {
        $count = Customer::keyValue(1)->where('id', '>=', 1)
            ->where('id', '<=', 5)->update(['phone' => null]);
        $this->assertEquals($count, 5);

        for ($i = 1; $i <= 5; ++$i) {
            $customer = Customer::find($i);
            $this->assertEquals($customer->phone, null);
            $customer->phone = $customer->id;
            $this->assertEquals($customer->save(), true);
        }
        

        $count = Customer::index(0)->keyValue(6)->update(['phone' => null]);
        $this->assertEquals($count, 1);
        $customer = Customer::find(6);
        $this->assertEquals($customer->id, 6);
        $this->assertEquals($customer->phone, null);
        $customer->phone = $customer->id;
        $this->assertEquals($customer->save(), true);
        $this->showMemoryUsage();
    }
    
    public function testFilterCreateAttribute()
    {
        //$guarded
        $attr = ['id' => 1, 'name' => 'abc', 'option' => 'abc', 'phone' => '123', 'note' => 'aaa'];
        $cust = new Cust;
        $attr =$cust->filterCreateAttribute($attr);
        $this->assertEquals(array_key_exists('note', $attr), false);

        //$fillable
        $attr = ['id' => 1, 'name' => 'abc', 'option' => 'abc', 'phone' => '123', 'note' => 'aaa'];
        $cust = new Customer;
        $attr = $cust->filterCreateAttribute($attr);
        $this->assertEquals(array_key_exists('note', $attr), false);
        $this->showMemoryUsage();
    }
    
    public function testCreate()
    {
        $attr = ['id' => 0, 'name' => 'abc', 'option' => 1, 'phone' => '123', 'note' => 'aaa'];
        $customer = Customer::create($attr);
        $this->assertEquals(($customer->id > 10000), true);
        $this->assertEquals($customer->phone, '123');
        $this->assertEquals($customer->note, '');
        $customer->delete();
        $this->showMemoryUsage();
    }
    
    public function testFirstOrCreate()
    {
        $attr = ['id' => 1, 'name' => 'abc', 'option' => 1, 'phone' => '123', 'note' => 'aaa'];
        $customer = Customer::firstOrCreate($attr);
        $this->assertEquals($customer->id, 1);
        $this->assertEquals($customer->phone, '1');
        
        $attr = ['id' => 10001, 'name' => 'abc', 'option' => 1, 'phone' => '123', 'note' => 'aaa'];
        $customer = Customer::firstOrCreate($attr);
        $this->assertEquals($customer->id, 10001);
        $this->assertEquals($customer->phone, '123');
        $this->assertEquals($customer->note, '');
        $customer->delete();
        $this->showMemoryUsage();
    }

    public function testDestroy()
    {
        $attr = ['id' => 10001, 'name' => 'abc', 'option' => 1, 'phone' => '123', 'note' => 'aaa'];
        $customer = Customer::firstOrCreate($attr);
        $attr = ['id' => 10002, 'name' => 'abc', 'option' => 1, 'phone' => '123', 'note' => 'aaa'];
        $customer = Customer::firstOrCreate($attr);
        $attr = ['id' => 10003, 'name' => 'abc', 'option' => 1, 'phone' => '123', 'note' => 'aaa'];
        $customer = Customer::firstOrCreate($attr);

        $n = Customer::destroy(10001);
        $this->assertEquals($n, 1);

        $n = Customer::destroy([10002, 10003]);
        $this->assertEquals($n, 2);
        
        $customer = Customer::find(10003);
        $this->assertEquals($customer, null);
        $this->showMemoryUsage();
    }
    
    public function testDelete()
    {
        $attr = ['id' => 10001, 'name' => 'abc', 'option' => 1, 'phone' => '123', 'note' => 'aaa'];
        $customer = Customer::firstOrCreate($attr);
        $attr = ['id' => 10002, 'name' => 'abc', 'option' => 1, 'phone' => '123', 'note' => 'aaa'];
        $customer = Customer::firstOrCreate($attr);
        $attr = ['id' => 10003, 'name' => 'abc', 'option' => 1, 'phone' => '123', 'note' => 'aaa'];
        $customer = Customer::firstOrCreate($attr);

        $n = Customer::keyValue(10001)->where('id', '>=', 10001)->delete();
        $this->assertEquals($n, 3);
        
        $customer = Customer::find(10003);
        $this->assertEquals($customer, null);
        
        $attr = ['id' => 10001, 'name' => 'abc', 'option' => 1, 'phone' => '123', 'note' => 'aaa'];
        $customer = Cust::firstOrCreate($attr);
        $n = Customer::keyValue(10001)->where('id', '>=', 10001)->delete();
        $this->assertEquals($n, 0);
        $this->showMemoryUsage();
    }

    public function testScope()
    {
        $s =  Customer::queryString();
        $this->assertEquals($s, '');
        $s = Customer::parent(5)->parent(6)->queryString();
        $this->assertEquals($s, "parent = '5' and parent = '6'");
        Customer::resetQuery();
        $this->showMemoryUsage();
    }
    
    public function testGetKeyFieldName()
    {
        $c = Customer::find(1);
        $name = $c->getPrimaryKeyFieldName(null);
        $this->assertEquals($name, ['id']);
        $name = $c->getPrimaryKeyFieldName('abc');
        $this->assertEquals($name, 'abc');
        $name = $c->getPrimaryKeyFieldName('efg');
        $this->assertEquals($name, 'efg');
        $this->showMemoryUsage();
    }
    
    public function testGetIndexByFieldNames()
    {
        $q = Customer::queryExecuter();
        $index = $q->getIndexByFieldNames('id');
        $this->assertEquals($index, 0);
        
        $q = Follower::queryExecuter();
        $index = $q->getIndexByFieldNames(['following_id', 'followed_id']);
        $this->assertEquals($index, 0);
        $index = $q->getIndexByFieldNames('following_id', true);
        $this->assertEquals($index, 1);
        $index = $q->getIndexByFieldNames(['followed_id'], true);
        $this->assertEquals($index, 2);
        $this->showMemoryUsage();
    }
    public function testHasOne()
    {
        $c = Customer::find(2);
        $ext = $c->extension;
        $this->assertEquals($ext->id, 2);
        
        $c = Customer::find(3);
        $ext = $c->ext;
        $this->assertEquals($ext->id, 3);
        $ext = $c->ext;
        $this->assertEquals($ext->id, 3);
        
        //erase cache test not find
        Customer::clear();
        $ext->delete();
        $c = Customer::find(3);
        $ext = $c->ext;
        $this->assertEquals($ext, null);
        
        // Add cache
        $ext = new Extension();
        $ext->id = 3;
        $ext->save();

        $c = Customer::find(3);
        $ext = $c->ext;
        $this->assertEquals($ext->parent, 0);
        
        // test refresh cache
        $ext->parent = 100;
        $ext->save();
        $c = Customer::find(3);
        $ext = $c->ext;
        $this->assertEquals($ext->parent, 100);
        $this->showMemoryUsage();
    }
    
    public function testBelongsTo()
    {
        $c = Customer::find(5);
        $ext = $c->extension;
        $this->assertEquals($ext->id, 5);
        $cp = $ext->customer;
        $this->assertEquals($cp->id, 5);
        $this->assertEquals($cp, $c);
        
        $exts = Extension::with('customer')->with('cust')->keyValue(5001)->where('id', '>', 5000)->get();
        $this->assertEquals(count($exts), 5000);
        $this->showMemoryUsage();
    }
        
    public function testHasMany()
    {
        $cust = Customer::find(1);
        Model::$resolvByCache = true;
        $followings = $cust->followings;
        $this->assertEquals(Model::$resolvByCache, false);
        $this->assertEquals(count($followings), 50);
        $this->assertEquals($followings[49]->followed_id, 10000);

        Model::$resolvByCache = true;
        $followings = $cust->followings2;
        $this->assertEquals(Model::$resolvByCache, false);
        $this->assertEquals(count($followings), 50);
        $this->assertEquals($followings[49]->followed_id, 10000);

        Model::$resolvByCache = true;
        $followings = $cust->followings;
        $this->assertEquals(Model::$resolvByCache, true);
        $this->assertEquals(count($followings), 50);
        $this->assertEquals($followings[49]->followed_id, 10000);

        Model::$resolvByCache = true;
        $followings = $cust->followings2;
        $this->assertEquals(Model::$resolvByCache, true);
        $this->assertEquals(count($followings), 50);
        $this->assertEquals($followings[49]->followed_id, 10000);
        unset($followings);
        unset($cust);
        Customer::clear();
        Follower::clear();
        
        $grps = Group::whereInKey([1, 3, 5])->with('customers')->with('cust')->get();
        $this->assertEquals(count($grps), 3);
        $this->assertEquals($grps[0]->customers[0]->group, 1);
        $this->assertEquals($grps[1]->customers[0]->group, 3);
        $this->assertEquals($grps[2]->customers[0]->group, 5);
        $this->assertEquals(count($grps[0]->customers), 2000);
        $this->assertEquals(count($grps[1]->customers), 2000);
        $this->assertEquals(count($grps[2]->customers), 2000);
        $this->assertEquals(get_class($grps), 'Transactd\Collection');
        $this->assertEquals(gettype($grps[0]->customers), 'object');
        
        $this->assertEquals($grps[0]->cust[0]->group, 1);
        $this->assertEquals($grps[1]->cust[0]->group, 3);
        $this->assertEquals($grps[2]->cust[0]->group, 5);
        $this->assertEquals(count($grps[0]->cust), 2000);
        $this->assertEquals(count($grps[1]->cust), 2000);
        $this->assertEquals(count($grps[2]->cust), 2000);
        $this->assertEquals(get_class($grps[0]->cust), 'Transactd\Collection');
        $this->assertEquals(get_class($grps), 'Transactd\Collection');
        
        //not with check collection
        $grps = Group::whereInKey([1])->get();
        $this->assertEquals(get_class($grps[0]->cust), 'Transactd\Collection');
        $this->assertEquals(gettype($grps[0]->customers), 'object');
        Customer::clear();
        Follower::clear();
        Group::clear();
        $this->showMemoryUsage();
    }
    
    public function testHasManyWith()
    {
        $custs = Customer::with('followings')->where('id', 1)->get();
        Model::$resolvByCache = true;
        $followings = $custs[0]->followings;
        $this->assertEquals(Model::$resolvByCache, true);
        $this->assertEquals(count($followings), 50);
        $this->assertEquals($followings[49]->followed_id, 10000);
        
        //Customer::resetQuery();
        $custs = Customer::with('followings')->all();
        $this->assertEquals(count($custs), 10000);
        
        Model::$resolvByCache = true;
        $followings = $custs[0]->followings;
        $this->assertEquals(Model::$resolvByCache, true);
        
        $this->assertEquals(count($followings), 50);
        $this->assertEquals($followings[49]->followed_id, 10000);
        
        Model::$resolvByCache = true;
        $followings = $custs[99]->followings;
        $this->assertEquals(Model::$resolvByCache, true);
        $this->assertEquals(count($followings), 50);
        $this->assertEquals($followings[49]->followed_id, 9899);
        
        // property is cached.
        Model::$resolvByCache = true;
        $followings = $custs[99]->followings;// set property
        $this->assertEquals(Model::$resolvByCache, true);
        $this->assertEquals(count($followings), 50);
        $this->assertEquals($followings[49]->followed_id, 9899);

        Model::$resolvByCache = true;
        $followings = $custs[99]->followings;// get cached property.
        $this->assertEquals(Model::$resolvByCache, true);
        $this->assertEquals(count($followings), 50);
        $this->assertEquals($followings[49]->followed_id, 9899);
        Customer::clear();
        Follower::clear();
        $this->showMemoryUsage();
    }
    
    public function testTransaction()
    {
        $c1 = Customer::find(1);
        $c2 = Customer::find(2);
        $c1->name = 'User1';
        $c2->name = 'User2';
        $c1->save();
        $c2->save();

        DB::beginTrn();
        $c1->name = 'AAA';
        $c2->name = 'BBB';
        $c1->save();
        $c2->save();
        DB::abortTrn();
        $c1 = Customer::find(1);
        $c2 = Customer::find(2);
        $this->assertNotEquals($c1->name, 'AAA');
        $this->assertNotEquals($c2->name, 'BBB');

        DB::beginTransaction();
        $c1->name = 'AAA';
        $c2->name = 'BBB';
        $c1->save();
        $c2->save();
        DB::commit();
        $c1 = Customer::find(1);
        $c2 = Customer::find(2);
        $this->assertEquals($c1->name, 'AAA');
        $this->assertEquals($c2->name, 'BBB');
        $c1->name = 'User1';
        $c2->name = 'User2';
        $c1->save();
        $c2->save();
        $this->showMemoryUsage();
    }
    
    public function testSnapshot()
    {
        DB::beginSnapshot();
        $c1 = Customer::find(1);
        $c2 = Customer::find(2);
        $c1->name = 'ABC';
        $c2->name = 'EFG';
        $c1->save();
        $c2->save();

        $c1 = Customer::find(1);
        $c2 = Customer::find(2);
        $this->assertEquals(Model::$resolvByCache, true);
        Customer::clear();

        $c1 = Customer::find(1);
        $c2 = Customer::find(2);
        $this->assertEquals(Model::$resolvByCache, false);

        $this->assertNotEquals($c1->name, 'ABC');
        $this->assertNotEquals($c2->name, 'EFG');
        DB::endSnapshot();
        Customer::clear();
        $c1 = Customer::find(1);
        $c2 = Customer::find(2);
        $this->assertEquals($c1->name, 'ABC');
        $this->assertEquals($c2->name, 'EFG');
        $c1->name = 'User1';
        $c2->name = 'User2';
        $c1->save();
        $c2->save();
        
        DB::slave()->beginSnapshot();
        $c1 = Customer::find(1);
        $c2 = Customer::find(2);
        DB::slave()->endSnapshot();
        $this->showMemoryUsage();
    }
    
    public function testUTCCObj()
    {
        if ($GLOBALS['$mysql55']) {
            return;
        }
        $tb = DB::master()->openTable("customers");
        $tb->seekFirst();
        $this->assertEquals($tb->stat(), 0);
        $customer = Customer::first();

        DB::beginTrn();
        $tb->setFV("名前", 'John');
        $tb->update();
        $this->assertEquals($tb->stat(), 0);
        $customer->name = 'mike';
        Customer::updateConflictCheck(true);
        try {
            $customer->save();
            $this->assertEquals(1, 0);
        } catch (IOException $e) {
            $this->assertEquals($e->getCode(), Transactd::STATUS_CHANGE_CONFLICT);
        }
        DB::abortTrn();
        $tb->seekFirst();
        $this->assertEquals($tb->stat(), 0);
        $customer = Customer::first();
        DB::beginTrn();

        $tb->setFV("名前", 'John');
        $tb->update();
        $this->assertEquals($tb->stat(), 0);
        $customer->name = 'mike';
        Customer::updateConflictCheck(false);
        try {
            $this->assertEquals($customer->save(), true);
            DB::abortTrn();
        } catch (IOException $e) {
            $this->assertEquals(1, 0);
            DB::abortTrn();
        }
        sleep(1);
        $this->showMemoryUsage();
    }

    public function testJson()
    {
        Customer::clear();
        $c2 = Customer::find(2);
        // tojson
        $c2->followings;
        $c2->name = '感じ';
        $json = $c2->toJson();
        // decode from json to stdClass
        $c = Model::deSerialize($json);
        $this->assertEquals($c->name, '感じ');
        $this->assertEquals($json, $c->toString());
        $this->showMemoryUsage();
    }
    
    public function testRelSave()
    {
        $customer = Customer::find(1000);
        $ext = new Extension(['hobby' => 'golf']);
        $customer->ext()->save($ext);
        $this->assertEquals($customer->ext->id, 1000);
        $this->assertEquals($customer->ext->hobby, 'golf');

        Customer::clear();
        Extension::clear();
        $customer = Customer::find(1000);
        $this->assertEquals($customer->ext->id, 1000);
        $this->assertEquals($customer->ext->hobby, 'golf');
        $this->showMemoryUsage();
    }
    
    public function testRelCreate()
    {
        $customer = Customer::find(1000);
        $customer->ext()->create(['hobby' => 'bike'])->save();
        
        Customer::clear();
        Extension::clear();
        $customer = Customer::find(1000);
        $this->assertEquals($customer->ext->id, 1000);
        $this->assertEquals($customer->ext->hobby, 'bike');
        $this->showMemoryUsage();
    }
    
    public function testRelAssociateDissociate()
    {
        $grp = Group::find(3);
        $this->assertEquals($grp->id, 3);
        $this->assertEquals(count($grp->customers), 2000);
        $customer = Customer::find(1000);
        $customer->grp()->associate($grp);
        $this->assertEquals($customer->group, 3);
        
        $customer->grp()->dissociate();
        $this->assertEquals($customer->group, null);
        $this->showMemoryUsage();
    }
    
    public function testBlongsToMany()
    {
        $customer = Customer::find(1);
        $rel = $customer->followings3();
        $this->assertEquals(count($customer->followings3), 50);
        $this->assertEquals($customer->followings3[49]->id, 10000);
        $this->assertEquals($customer->followings3[48]->id, 9800);
        $this->showMemoryUsage();
    }
    
    public function testBlongsToManyWith()
    {
        $custs = Customer::with('followings3')->where('id', '<=', 10)->get();
        $this->assertEquals(count($custs), 10);
        $this->assertEquals(count($custs[0]->followings3), 50);
        $this->assertEquals($custs[0]->followings3[49]->id, 10000);
        $this->assertEquals($custs[0]->followings3[48]->id, 9800);
        //test not resolv
        $customer = Customer::find(9800);
        $customer->delete();
        
        $custs = Customer::with('followings3')->where('id', '<=', 10)->get();
        $this->assertEquals(count($custs), 10);
        $this->assertEquals(count($custs[0]->followings3), 50);
        $this->assertEquals($custs[0]->followings3[49]->id, 10000);
        $this->assertEquals($custs[0]->followings3[48], null);
        $c = Customer::create(['id' => 9800, 'group' => 5, 'option' =>9800, 'phone' => null, 'special_following' => null]);
        $c->group = 5;
        $c->special_following = null;
        $c->save();
        $this->showMemoryUsage();
    }
    
    public function testBlongsToMany2With()
    {
        $custs = Customer::with('followings4')->where('id', '<=', 10)->get();
        $this->assertEquals(count($custs), 10);
        $this->assertEquals(count($custs[0]->followings3), 50);
        $this->assertEquals($custs[0]->followings3[49]->id, 10000);
        $this->assertEquals($custs[0]->followings3[48]->id, 9800);
        //test not resolv
        $customer = Customer::find(9800);
        $customer->delete();
        
        $custs = Customer::with('followings4')->where('id', '<=', 10)->get();
        $this->assertEquals(count($custs), 10);
        $this->assertEquals(count($custs[0]->followings3), 50);
        $this->assertEquals($custs[0]->followings3[49]->id, 10000);
        $this->assertEquals($custs[0]->followings3[48], null);
        $c = Customer::create(['id' => 9800, 'group' => 5, 'option' =>9800, 'phone' => null, 'special_following' => null]);
        $c->group = 5;
        $c->special_following = null;
        $c->save();
        $this->showMemoryUsage();
    }
    
    public function testCollection()
    {
        $a = array();
        $c = new Collection($a);
        $c[1] = 'ABC';
        $this->assertEquals($c[1], 'ABC');
        $b = $c->getNativeArray();
        $this->assertEquals($b[1], 'ABC');
        $b[1] = 'EFG';
        $c->setNativeArray($b);
        $this->assertEquals($c[1], 'EFG');
        $this->showMemoryUsage();
    }
    
    public function testCollectionAccess()
    {
        $array = array(); 
        $cust = new Customer();
        $cust->id = 0;
        array_push($array, $cust);
        $cust = new Customer();
        $cust->id = 1;
        array_push($array, $cust);
        $cust = new Customer();
        $cust->id = 2;
        array_push($array, $cust);
        $collection = new Collection($array);
        $this->assertEquals($collection[0]->id, 0);
        $this->assertEquals($collection[1]->id, 1);
        $this->assertEquals($collection[2]->id, 2);
        $i = 0;
        foreach($collection as $cust) {
            $this->assertEquals($cust->id, $i++);
        }
        // insert 
        $cust = new Customer();
        $cust->id = 3;
        $collection->insert(0, $cust);
        $this->assertEquals($collection[0]->id, 3);
        $ids = [3,0,1,2];
        $i = 0;
        foreach($collection as $cust) {
            $this->assertEquals($cust->id, $ids[$i++]);
        }
        for($i = 0; $i < count($collection); ++$i) {
            $this->assertEquals($collection[$i]->id, $ids[$i]);
        }
        // move
        $collection->move(0, 3);
        $i = 0;
        foreach($collection as $cust) {
            $this->assertEquals($cust->id, $i++);
        }
        for($i = 0; $i < count($collection); ++$i) {
            $this->assertEquals($collection[$i]->id, $i);
        }
        
        //remove
        $collection->remove(1);
        $ids = [0,2,3];
        $i = 0;
        foreach($collection as $cust) {
            $this->assertEquals($cust->id, $ids[$i++]);
        }
        for($i = 0; $i < count($collection); ++$i) {
            $this->assertEquals($collection[$i]->id, $ids[$i]);
        }
         
    }
    
    public function testCollectionSave()
    {
        $grp = new Group();
        $grp->id = 0;
        $grp->name = 'group 6';
        
        $c = new Customer();
        $c->id = 0;
        $c->name = 'Jhon';
        $grp->customers->add($c);
        
        $c = new Customer();
        $c->id = 0;
        $c->name = 'mike';
        $grp->customers->add($c);

        $c = new Customer();
        $c->id = 0;
        $c->name = 'akio';
        $grp->customers->add($c);
        
        Customer::prepareTable();
        Group::prepareTable();
        DB::beginTrn();
        $grp->save();
        $id = $grp->id;
        DB::endTrn();
        $grp = Group::find($id);
        $this->assertEquals(count($grp->customers), 3);
        $this->assertEquals($grp->customers[0]->name, 'Jhon');
        $grp->delete();
        
        Group::clear();
        $grp = Group::find($id);
        $this->assertEquals($grp, null);
        $this->showMemoryUsage();
    }
    
    public function testMorph()
    {
        $c = new Comment;
        $c->note = 'abc';
        $cust = new Customer();
        $cust->id = 0;
        $cust->name = 'kaito';
        $cust->comments->add($c);
        $cust->save(Model::SAVE_WITH_RELATIONS);
        
        $cid = $cust->id;
        $cust = $c->commentable;
        $this->assertEquals($cust->id, $cid);
        
        $c = new Comment;
        $c->id = 0;
        $c->note = 'efg';
        $grp = Group::find(1);
        $grp->comments->add($c);
        $grp->save(Model::SAVE_WITH_RELATIONS);
        unset($grp);
        $id = $c->id;
        $c = Comment::find($id);
        $grp = $c->commentable;
        $this->assertEquals($grp->id, 1);
        
        $comments = Comment::all();
        $this->assertEquals(count($comments), 2);
        $this->assertEquals(get_class($comments[0]->commentable), 'Customer');
        $this->assertEquals(get_class($comments[1]->commentable), 'Group');
        
        $grp = Group::find(1);
        $grp->comments->delete();
        
        $cust = Customer::find($cid);
        $cust->delete(Model::SAVE_WITH_RELATIONS);
        
        $comments = Comment::all();
        $this->assertEquals(count($comments), 0);
        $this->showMemoryUsage();
    }
    
    public function testMorphMap()
    {
        Comment::clear();
        Customer::clear();
        Group::clear();
        $c = new Comment;
        $c->note = 'abc';
        $cust = new Customer();
        $cust->id = 0; // autoinc id is not setted
        $cust->name = 'kaito';
        $cust->comments2->add($c);
        $cc = $c->commentable2; // retun null
        $this->assertEquals($cc, null);
        $cust->save(Model::SAVE_WITH_RELATIONS); // autoinc id is setted
        $cid = $cust->id;
        
        $cust = $c->commentable2; // retun not null
        $this->assertEquals($cust->id, $cid);
        $this->assertEquals($c->parent_type, 1);
        
        $c = new Comment;
        $c->id = 0;
        $c->note = 'efg';
        $grp = Group::find(1);
        $grp->comments2->add($c);
        $this->assertEquals($c->parent_type, 2);
        $grp->save();

        unset($grp);
        $id = $c->id;
        $c = Comment::find($id);
        $grp = $c->commentable2;
        $this->assertEquals($grp->id, 1);
        
        $comments = Comment::all();
        $this->assertEquals(count($comments), 2);
        $this->assertEquals(get_class($comments[0]->commentable2), 'Customer');
        $this->assertEquals(get_class($comments[1]->commentable2), 'Group');
        
        $grp = Group::find(1);
        $grp->comments2->delete();
        
        $cust = Customer::find($cid);
        $cust->delete();
        
        $comments = Comment::all();
        $this->assertEquals(count($comments), 0);
        $this->showMemoryUsage();
    }

    public function testMorphMany()
    {
        $tag = new Tag;
        $tag->id = 0;
        $tag->name = 'Good';
        $tag->save();
        $tagid = $tag->id;

        $tag1 = new Tag;
        $tag1->id = 0;
        $tag1->name = 'better';
        $tag1->save();

        $tag1 = new Tag;
        $tag1->id = 0;
        $tag1->name = 'normal';
        $tag1->save();
        $tagid1 = $tag1->id;

        $cust = Customer::find(1);
        $tags = $cust->tags;
        $tags->add($tag);
        $tags->add($tag1);
        $tags->save();

        Customer::clear();
        Tag::clear();
        
        $cust = Customer::find(1);
        $this->assertEquals(count($cust->tags), 2);
        $this->assertEquals($cust->tags[0]->id, $tagid);
        $this->assertEquals($cust->tags[1]->id, $tagid1);

        $this->assertEquals(count($cust->tags2), 2);
        $this->assertEquals($cust->tags2[0]->id, $tagid);
        $this->assertEquals($cust->tags2[1]->id, $tagid1);

        $cust->tags->delete();
        
        Customer::clear();
        $cust = Customer::find(1);
        $this->assertEquals(count($cust->tags), 0);
        
        $grp = Group::find(1);
        $tags = $grp->tags;
        
        $tag = Tag::all();
        $tags->add($tag);
        $tags->save();
        Group::clear();
        
        $grp = Group::find(1);
        $this->assertEquals(count($grp->tags2), 3);
        $grp->tags2->delete();
        $tag->saveOprions = 0;
        $tag->delete();
        $this->showMemoryUsage();
    }
    
    public function testMorphManyDelete()
    {
        $tag = new Tag;
        $tag->id = 1;
        $tag->name = 'Good';
        $tag->save();

        $tag1 = new Tag;
        $tag1->id = 2;
        $tag1->name = 'better';
        $tag1->save();

        $tag1 = new Tag;
        $tag1->id = 3;
        $tag1->name = 'normal';
        $tag1->save();

        $cust = Customer::find(1);
        $tags = $cust->tags;
        $tags->add($tag);
        $tags->add($tag1);
        $tags->saveOprions = Collection::SAVE_AFTER_DELETE_ITEMS;

        $ret = $tags->save();
        $this->assertEquals($ret, true);

        Customer::clear();
        Tag::clear();
        
        $cust = Customer::find(1);
        $this->assertEquals(count($cust->tags), 2);
        $this->assertEquals($cust->tags[0]->id, 1);
        $this->assertEquals($cust->tags[1]->id, 3);

        $this->assertEquals(count($cust->tags2), 2);
        $this->assertEquals($cust->tags2[0]->id, 1);
        $this->assertEquals($cust->tags2[1]->id, 3);
        $cust->tags->saveOprions = Collection::SAVE_AFTER_DELETE_ITEMS;
        $cust->tags->delete();
        
        Customer::clear();
        $cust = Customer::find(1);
        $this->assertEquals(count($cust->tags), 0);
        
        $grp = Group::find(1);
        $tags = $grp->tags;
        
        $tag = Tag::all();
        $tags->add($tag);
        $tags->save();
        Group::clear();
        $grp = Group::find(1);
        $this->assertEquals(count($grp->tags2), 3);
        $grp->tags2->saveOprions = Collection::SAVE_AFTER_DELETE_ITEMS;
        $grp->tags2->delete();
        Group::clear();
        $grp = Group::find(1);
        $this->assertEquals(count($grp->tags2), 0);
        
        $tags = Tag::all();
        $this->assertEquals($tags->delete(), true);
        
        Tag::clear();
        $tags = Tag::all();
        $this->assertEquals(count($tags), 0);
        $this->showMemoryUsage();
    }
    
    public function testTransfer()
    {
         $cust = Customer::find(1);
         $this->assertEquals($cust->address()->zip, '123-0');
         $cust->address()->zip = '390-0831';
         $cust->save();
         Customer::clear();
         $cust = Customer::find(1);
         $this->assertEquals($cust->address()->zip, '390-0831');
    }
        
    private function showMemoryUsage()
    {
        //echo debug_backtrace()[1]['function'].' : '. ((int)(memory_get_usage()/(1024*1024))).PHP_EOL;
    }
}

