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
use Transactd\Model;
use Transactd\IOException;
use Transactd\Collection;

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

class_alias('Transactd\DatabaseManager', 'DB');

function init()
{
    try {
        DB::connect(URI, URI);
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

function createTestData()
{
    try {
        echo PHP_EOL.'URI='.URI.PHP_EOL;
        $db = new Database();
        $db->create(URI);
        if ($db->stat() === Transactd::STATUS_TABLE_EXISTS_ERROR) {
            //return true;
            $db->drop(URI);
            checkStat($db);
            $db->create(URI);
            checkStat($db);
        }
        $db->open(URI, Transactd::TYPE_SCHEMA_BDF, Transactd::TD_OPEN_NORMAL);
        checkStat($db);
        $def = $db->dbDef();
        $tableid = 1;
        // customers
        insertTable($def, $tableid, 'customers');
        addField($def, $tableid, 'id', Transactd::ft_autoinc, 4);
        addField($def, $tableid, 'name', Transactd::ft_myvarchar, 30);
        addField($def, $tableid, 'group_id', Transactd::ft_integer, 4);
        addField($def, $tableid, 'phpne', Transactd::ft_myvarchar, 30);
        $fd = addField($def, $tableid, 'update_at', Transactd::ft_mytimestamp, 7, false);
        $fd->setDefaultValue(Transactd::DFV_TIMESTAMP_DEFAULT);
        $fd->setTimeStampOnUpdate(true);
        $keynum = 0;
        $kd = $def->insertKey($tableid, $keynum);
        $kd->segment(0)->fieldNum = 0;
        $kd->segment(0)->flags->bit8 = 1;
        $kd->segment(0)->flags->bit1 = 1;
        $kd->segmentCount = 1;
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
        
        $tableid = 2;
        // addreses
        insertTable($def, $tableid, 'addresses');
        addField($def, $tableid, 'customer_id', Transactd::ft_autoinc, 4);
        addField($def, $tableid, 'zip', Transactd::ft_myvarchar, 30);
        addField($def, $tableid, 'state', Transactd::ft_myvarchar, 30);
        addField($def, $tableid, 'city', Transactd::ft_myvarchar, 30);
        addField($def, $tableid, 'street', Transactd::ft_myvarchar, 30);
        $fd = addField($def, $tableid, 'update_at', Transactd::ft_mytimestamp, 7, false);
        $fd->setDefaultValue(Transactd::DFV_TIMESTAMP_DEFAULT);
        $fd->setTimeStampOnUpdate(true);
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
        
        $tableid = 3;
        // addreses
        insertTable($def, $tableid, 'groups');
        addField($def, $tableid, 'id', Transactd::ft_autoinc, 4);
        addField($def, $tableid, 'name', Transactd::ft_myvarchar, 30);
        $fd = addField($def, $tableid, 'update_at', Transactd::ft_mytimestamp, 7, false);
        $fd->setDefaultValue(Transactd::DFV_TIMESTAMP_DEFAULT);
        $fd->setTimeStampOnUpdate(true);
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
        
        $tableid = 4;
        // tags
        insertTable($def, $tableid, 'tags');
        addField($def, $tableid, 'id', Transactd::ft_autoinc, 4);
        addField($def, $tableid, 'name', Transactd::ft_myvarchar, 30);
        $fd = addField($def, $tableid, 'update_at', Transactd::ft_mytimestamp, 7, false);
        $fd->setDefaultValue(Transactd::DFV_TIMESTAMP_DEFAULT);
        $fd->setTimeStampOnUpdate(true);
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

        $tableid = 5;
        // tag_customer
        insertTable($def, $tableid, 'tag_customer');
        addField($def, $tableid, 'tag_id', Transactd::ft_integer, 4);
        addField($def, $tableid, 'customer_id', Transactd::ft_integer, 4);
        $fd = addField($def, $tableid, 'update_at', Transactd::ft_mytimestamp, 7, false);
        $fd->setDefaultValue(Transactd::DFV_TIMESTAMP_DEFAULT);
        $fd->setTimeStampOnUpdate(true);
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
        $kd->segment(0)->fieldNum = 1;
        $kd->segment(0)->flags->bit8 = 1;
        $kd->segment(0)->flags->bit1 = 1;
        $kd->segment(1)->fieldNum = 0;
        $kd->segment(1)->flags->bit8 = 1;
        $kd->segment(1)->flags->bit1 = 1;
        $kd->segmentCount = 2;
        $td = $def->tableDefs($tableid);
        $td->primaryKeyNum = 0;
        $def->updateTableDef($tableid);
        checkStat($def);
        
        $tableid = 6;
        // vendors
        insertTable($def, $tableid, 'vendors');
        addField($def, $tableid, 'id', Transactd::ft_autoinc, 4);
        addField($def, $tableid, 'name', Transactd::ft_myvarchar, 30);
        $fd = addField($def, $tableid, 'update_at', Transactd::ft_mytimestamp, 7, false);
        $fd->setDefaultValue(Transactd::DFV_TIMESTAMP_DEFAULT);
        $fd->setTimeStampOnUpdate(true);
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
        // taggables
        insertTable($def, $tableid, 'taggables');
        addField($def, $tableid, 'taggable_type', Transactd::ft_myvarchar, 30);
        addField($def, $tableid, 'taggable_id', Transactd::ft_integer, 4);
        addField($def, $tableid, 'tag_id', Transactd::ft_integer, 4);
        $fd = addField($def, $tableid, 'update_at', Transactd::ft_mytimestamp, 7, false);
        $fd->setDefaultValue(Transactd::DFV_TIMESTAMP_DEFAULT);
        $fd->setTimeStampOnUpdate(true);
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
        $kd->segment(0)->fieldNum = 2;
        $kd->segment(0)->flags->bit8 = 1;
        $kd->segment(0)->flags->bit1 = 1;
        $kd->segment(1)->fieldNum = 0;
        $kd->segment(1)->flags->bit8 = 1;
        $kd->segment(1)->flags->bit1 = 1;
        $kd->segment(2)->fieldNum = 1;
        $kd->segment(2)->flags->bit8 = 1;
        $kd->segment(2)->flags->bit1 = 1;
        $kd->segmentCount = 3;
        $td = $def->tableDefs($tableid);
        $td->primaryKeyNum = 0;
        $def->updateTableDef($tableid);
        checkStat($def);
        $db->close();
        return true;
    } catch (Exception $e) {
        echo $e->getMessage();
    }
    return false;
}

if (createTestData() === false) {
    return 1;
}

init();

class Address extends Model
{
    protected static $classTypeMap = [1 => 'Vendor', 2 => 'Customer'];// For Polymorphic Relationship
    
    function customer()
    {
        return $this->belongsTo('Customer');
    }
    
    function customer1()
    {
        return $this->relation('Customer', 0, 'customer_id');
    }
    
    function owner()
    {
        return $this->morphTo('owner', null, null, self::$classTypeMap);
    }
    
    function owner1()
    {
        return $this->relation(null, 0, ['owner_type', 'owner_id'])->setMorphClassMap(self::$classTypeMap);
    }
}
class Customer extends Model
{
    public function address()
    {
        return $this->hasOne('Address');
    }
    public function group()
    {
        return $this->belongsTo('Group');
    }
    /**
     * @return \Tag
     */
    public function tags()
    {
        return $this->belongsToMany('Tag');
    }
    
    public function address1()
    {
        return $this->relation('Address', 0, 'id');
    }
    public function group1()
    {
        return $this->relation('Group', 0, 'group_id');
    }

    /**
     * @return \Tag
     */
    public function tags1()
    {
        return $this->relation('TagCustomer', 1, 'id')
                ->addSubRelation('Tag', 0, 'tag_id');
    }
    
    // For Polymorphic Relationship
    public function address2()
    {
        return $this->morphOne('Address', 'owner', null, null, ['[2]', 'id']);
    }
    
    public function address3()
    {
        return $this->relation('Address', 0, ['[2]', 'id']);
    }
    
    public function address4()
    {
         return $this->morphMany('Address', 'owner', null, null, ['[2]', 'id']);
    }

    // or 
    public function address5()
    {
        // The index numbers are different.
        return $this->relation('Address', 1, ['[2]', 'id']);
    }
    
    function tags2()
    {
        return $this->morphToMany('Tag', 'taggable', 'Taggable');
    }

    function tags3()
    {
        return $this->relation('Taggable', 0, ['[Customer]', 'id'])
               ->addSubRelation('Tag', 0, 'tag_id');
    }

}

class Group extends Model
{
    public function customers()
    {
         return $this->hasMany('Customer');
    }
    public function customers1()
    {
         return $this->relation('Customer', 1, 'id');
    }
}

class Tag extends Model
{
    public function customers()
    {
        return $this->belongsToMany('Customer');
    }
    public function customers1()
    {
        return $this->relation('TagCustomer', 0, 'id')
                ->addSubRelation('Customer', 0, 'customer_id');
    }
    
    // Get all customers of this tag has.
    public function customers2()
    {
        return $this->morphedByMany('Customer', 'taggable', 'Taggable');
    }
    
    // Get all vendors of this tag has.
    public function vendors2()
    {
        return $this->morphedByMany('Vendor', 'taggable', 'Taggable');
    }

    // Or 
    public function customers3()
    {
        return $this->relation('Taggable', 1, ['id', '[Customer]'])
               ->addSubRelation('Customer', 0, 'taggable_id');
    }

    public function vendors3()
    {
        return $this->relation('Taggable', 1, ['id', '[Vendor]'])
               ->addSubRelation('Vendor', 0, 'taggable_id');
    }
}

// Defining a intermidiate class required.
class TagCustomer extends Model
{
    // Convert table name to snake case is not supported.
    protected static $table = 'tag_customer';
}

class Vendor extends Model
{
    public function address2()
    {
        return $this->morphOne('Address', 'owner', ['[1]', 'id']);
    }
    public function address3()
    {
        return $this->relation('Address', 0, ['[1]', 'id']);
    }
    
    function tags2()
    {
        return $this->morphToMany('Tag', 'taggable', 'Taggable');
    }

     function tags3()
    {
        return $this->relation('Taggable', 0, ['[Vendor]', 'id'])
               ->addSubRelation('Tag', 0, 'tag_id');
    }
}

class Taggable extends Model
{
}

class TransactdTest extends PHPUnit_Framework_TestCase
{
    public function testCreateGroup()
    {
        $groups = new Collection;
        $group = new Group;
        $group->name = 'Good';
        $groups->add($group);
        $group = new Group;
        $group->name = 'Better';
        $groups->add($group);
        $group = new Group;
        $group->name = 'Normal';
        $groups->add($group);
        $groups->save(Collection::SAVE_ALL_BY_INSERT); // bulkinsert
    }
    
    public function testCreateCustomer()
    {
        $groups = Group::all();
        $customer = new Customer;
        $customer->name = 'Akio';
        $customer->group()->associate($groups[0]);
        $customer->save();
        $address = new Address();
        
        $address->zip = '390-0831';
        $address->state = 'Nagano';
        $address->city = 'Matsumoto';
        $address->street = '3-1-5 igawaxyo';
        $address->customer()->associate($customer);
        $address->save();
        
        $customer = new Customer();
        $customer->name = 'Yoko';
        $customer->group()->associate($groups[1]);
        $customer->save();
        $address = new Address();
        $address->zip = '105-0831';
        $address->state = 'Tokyo';
        $customer->city = 'Suginami';
        $address->street = '3-1-5 midori';
        $customer->address = $address;
        $address->customer()->associate($customer);
        $address->save();
        
    }
    public function testReadCustomer()
    {
        $customer = Customer::find(1);
        $this->assertEquals($customer->address->zip , '390-0831');
        $this->assertEquals($customer->group->name , 'Good');
        
        //reverse
        Group::clear();
        $group = Group::find(1);
        $customers = $group->customers;
        $this->assertEquals($customers[0]->name , 'Akio');
        
        $address = Address::find(1);
        $this->assertEquals($address->customer->name , 'Akio');
        
    }
    
    public function testCreateCustomer1()
    {
        $groups = Group::all();
        $customer = new Customer;
        $customer->name = 'Yoshio';
        $customer->group1()->associate($groups[0]);
        $customer->save();
        
        $address = new Address();
        $address->zip = '390-0832';
        $address->state = 'Nagano';
        $address->city = 'Matsumoto';
        $address->street = '3-1-5 igawaxyo';
        $address->customer()->associate($customer);
        $address->save();
        
        $customer = new Customer();
        $customer->name = 'Todaiji';
        $customer->group1()->associate($groups[1]);
        $customer->save();
        
        $address = new Address();
        $address->zip = '105-0831';
        $address->state = 'Tokyo';
        $customer->city = 'Suginami';
        $address->street = '3-1-5 midori';
        $address->customer()->associate($customer);
        $address->save();
    }
    
    public function testReadCustomer1()
    {
        //forword 
        $customer = Customer::find(3);
        $this->assertEquals($customer->address->zip , '390-0832');
        $this->assertEquals($customer->group->name , 'Good');
        
        //reverse
        Group::clear();
        $group = Group::find(1);
        $customers = $group->customers;
        $this->assertEquals($customers[1]->name , 'Yoshio');
        
        $address = Address::find(4);
        $this->assertEquals($address->customer->name , 'Todaiji');
    }
    
    public function testCreateTag()
    {
        $tags = new Collection;
        $tag = new Tag;
        $tag->name = 'bycicle';
        $tags->add($tag);
        $tag = new Tag;
        $tag->name = 'car';
        $tags->add($tag);
        $tag = new Tag;
        $tag->name = 'byke';
        $tags->add($tag);
        $tags->save(Collection::SAVE_ALL_BY_INSERT); 
    }
    
    public function testAddTags()
    {
        $tag1 = Tag::find(1);
        $tag2 = Tag::find(2);
        $customer = Customer::find(1);
        $customer->tags->add($tag1);
        $customer->tags->add($tag2);
        $customer->tags->save();

        Customer::clear();
        $customer = Customer::find(1);
        $this->assertEquals(count($customer->tags) , 2);
        //reverse
        $tag = Tag::find(1);
        $this->assertEquals(count($tag->customers) , 1);
        $this->assertEquals($tag->customers[0]->id , 1);
    }
    
    public function testAddTags1()
    {
        $tag1 = Tag::find(1);
        $tag2 = Tag::find(2);
        $customer = Customer::find(2);
        $customer->tags1->add($tag1);
        $customer->tags1->add($tag2);
        $customer->tags1->save();
        
        Customer::clear();
        //$customer = Customer::find(2);
        //test With
        $customers = Customer::with('tags1')->keyValue(2)->where('id', 2)->get();
        $customer = $customers[0];
        $this->assertEquals(count($customer->tags1) , 2);
        
        //reverse
        $tags = Tag::with('customers1')->keyValue(2)->where('id', 2)->get();
        $tag = $tags[0];
        $this->assertEquals(count($tag->customers1) , 2);
        $this->assertEquals($tag->customers1[0]->id , 1);
    }
    
    public function testNewAddressesTable()
    {
        DB::reset();
        $db = new Database();
        $db->open(URI, Transactd::TYPE_SCHEMA_BDF, Transactd::TD_OPEN_NORMAL);
        $db->dropTable("addresses");
        checkStat($db);
        $tableid = 2;
        $def = $db->dbdef();
        
        $fd = $def->tableDefs($tableid)->fieldDef(0);
        $fd->len = 4;
        $fd->type = Transactd::ft_integer;
        $fd->setName('owner_type');
        $fd = $def->insertField($tableid, 1);
        $fd->setName('owner_id');
        $fd->len = 4;
        $fd->type = Transactd::ft_integer;
        $kd = $def->tableDefs($tableid)->keyDef(0);
        $kd->segment(0)->fieldNum = 0;
        $kd->segment(0)->flags->bit8 = 1;
        $kd->segment(0)->flags->bit1 = 1;
        $kd->segment(1)->fieldNum = 1;
        $kd->segment(1)->flags->bit8 = 1;
        $kd->segment(1)->flags->bit1 = 1;
        $kd->segmentCount = 2;
        $def->updateTableDef($tableid);
        checkStat($def);
        $db->close();
        init();
    }      
    
    function testCreateCustomerPolymorphicAddress()
    {
        $groups = Group::all();
        $customer = new Customer;
        $customer->name = 'Yoshio2';
        $customer->group1()->associate($groups[0]);
        $customer->save();
       
        $address = new Address();
        $address->zip = '390-0833';
        $address->state = 'Nagano';
        $address->city = 'Matsumoto';
        $address->street = '3-1-5 igawaxyo';
        $address->owner()->associate($customer);
        $address->save();
        
        $customer = new Customer;
        $customer->name = 'Takako';
        $customer->group1()->associate($groups[1]);
        $customer->save();
       
        $address = new Address();
        $address->zip = '390-0834';
        $address->state = 'Nagano';
        $address->city = 'Matsumoto';
        $address->street = '3-1-5 igawaxyo';
        $address->owner()->associate($customer);
        $address->save();
        
    }
    
    function testReadCustomerPolymorphicAddress()
    {
        $customer = Customer::find(5);
        $this->assertEquals($customer->address2->zip , '390-0833');
        $this->assertEquals($customer->group->name , 'Good');
        
        $address = Address::find([2, 5]);
        $this->assertEquals($address->owner->name , 'Yoshio2');
    }
    function testCreateCustomerPolymorphicAddress2()
    {
        $groups = Group::all();
        $customer = new Customer;
        $customer->name = 'Yoshio3';
        $customer->group1()->associate($groups[0]);
        $customer->save();
       
        $address = new Address();
        $address->zip = '390-0843';
        $address->state = 'Nagano';
        $address->city = 'Matsumoto';
        $address->street = '3-1-5 igawaxyo';
        $address->owner1()->associate($customer);
        $address->save();
        
        $customer = new Customer;
        $customer->name = 'Takako2';
        $customer->group1()->associate($groups[1]);
        $customer->save();
       
        $address = new Address();
        $address->zip = '390-0844';
        $address->state = 'Nagano';
        $address->city = 'Matsumoto';
        $address->street = '3-1-5 igawaxyo';
        $address->owner1()->associate($customer);
        $address->save();
        
    }
    function testReadCustomerPolymorphicAddress2()
    {
        $customer = Customer::find(7);
        $this->assertEquals($customer->address3->zip , '390-0843');
        $this->assertEquals($customer->group->name , 'Good');
        
        //Test With
        $addresses = Address::with('owner1')->keyValue(2, 7)->where('owner_type', 2)->where('owner_id', 7)->get();
        //$address = Address::find([2, 7]);
        $address = $addresses[0];
        $this->assertEquals($address->owner1->name , 'Yoshio3');
    }
    
    public function testNewAddressesTable2()
    {
        DB::reset();
        $db = new Database();
        $db->open(URI, Transactd::TYPE_SCHEMA_BDF, Transactd::TD_OPEN_NORMAL);
        $db->dropTable("addresses");
        checkStat($db);
        $tableid = 2;
        $def = $db->dbdef();
        
        $fd = $def->insertField($tableid, 0);
        $fd->setName('id');
        $fd->len = 4;
        $fd->type = Transactd::ft_autoinc;
        $kd = $def->tableDefs($tableid)->keyDef(0);
        $kd->segment(0)->fieldNum = 0;
        $kd->segment(0)->flags->bit8 = 1;
        $kd->segment(0)->flags->bit1 = 1;
        $kd->segmentCount = 1;
        $kd = $def->insertKey($tableid, 1);
        $kd->segment(0)->fieldNum = 1;
        $kd->segment(0)->flags->bit8 = 1;
        $kd->segment(0)->flags->bit0 = 1;
        $kd->segment(1)->fieldNum = 2;
        $kd->segment(1)->flags->bit8 = 1;
        $kd->segment(1)->flags->bit0 = 1;
        $kd->segmentCount = 2;
        $def->updateTableDef($tableid);
        checkStat($def);
        $db->close();
        init();
    }  
    
    function testCreateCustomerPolymorphicManyAddress()
    {
        $groups = Group::all();
        $customer = new Customer;
        $customer->name = 'Yoshio9';
        $customer->group1()->associate($groups[0]);
        $customer->save();
       
        $address = new Address();
        $address->zip = '390-0839';
        $address->state = 'Nagano';
        $address->city = 'Matsumoto';
        $address->street = '3-1-5 igawaxyo';
        $address->owner()->associate($customer);
        $address->save();
        
        $customer = new Customer;
        $customer->name = 'Takako10';
        $customer->group1()->associate($groups[1]);
        $customer->save();
       
        $address = new Address();
        $address->zip = '390-0810';
        $address->state = 'Nagano';
        $address->city = 'Matsumoto';
        $address->street = '3-1-5 igawaxyo';
        $address->owner()->associate($customer);
        $address->save();
    }
    
    function testReadCustomerPolymorphicManyAddress()
    {
        $customer = Customer::find(9);
        $this->assertEquals($customer->address4[0]->zip , '390-0839');
        $this->assertEquals($customer->group->name , 'Good');
        
        //Test With
        $addresses = Address::with('owner')->index(1)->keyValue(2, 9)->where('owner_type', 2)->where('owner_id', 9)->get();
        $address = $addresses[0];
        $this->assertEquals($address->owner->name , 'Yoshio9');
    }
    
    function testCreateCustomerPolymorphicManyAddress2()
    {
        $groups = Group::all();
        $customer = new Customer;
        $customer->name = 'Yoshio11';
        $customer->group1()->associate($groups[0]);
        $customer->save();
       
        $address = new Address();
        $address->zip = '390-0811';
        $address->state = 'Nagano';
        $address->city = 'Matsumoto';
        $address->street = '3-1-5 igawaxyo';
        $address->owner1()->associate($customer);
        $address->save();
        $address = new Address();
        $address->zip = '105-0222';
        $address->state = 'Tokyo';
        $address->city = 'koto-ku';
        $address->street = '3-21 shinkiba';
        $address->owner1()->associate($customer);
        $address->save();
        
        
        $customer = new Customer;
        $customer->name = 'Takako12';
        $customer->group1()->associate($groups[1]);
        $customer->save();
       
        $address = new Address();
        $address->zip = '390-0812';
        $address->state = 'Nagano';
        $address->city = 'Matsumoto';
        $address->street = '3-1-5 igawaxyo';
        $address->owner1()->associate($customer);
        $address->save();
        
        $address = new Address();
        $address->zip = '105-0221';
        $address->state = 'Tokyo';
        $address->city = 'koto-ku';
        $address->street = '3-21 shinkiba';
        $address->owner1()->associate($customer);
        $address->save();
    }
    
    function testReadCustomerPolymorphicManyAddress2()
    {
        $customer = Customer::find(11);
        //Test With
        $customers = Customer::with('address5')->keyValue(11)->where('id', 11)->get();
        $customer = $customers[0];
        
        $this->assertEquals(($customer->address5 instanceof Collection)  , true);
        $this->assertEquals(count($customer->address5) , 2);
        $this->assertEquals($customer->address5[0]->zip , '390-0811');
        $this->assertEquals($customer->group->name , 'Good');
        
        $addresses = Address::index(1)->keyValue(2,11)->where('owner_type', 2)->where('owner_id', 11)->get();
        
        $this->assertEquals(count($addresses) , 2);
        $this->assertEquals($addresses[0]->owner1->name , 'Yoshio11');
    }
    
    function testCreateMorphManyMany()
    {
        $tags = Tag::all();
        $customer = Customer::find(1);
        $tags2 = $customer->tags2;
        $tags2->add($tags[0]);
        $tags2->add($tags[1]);
        $tags2->save();
        Customer::clear();
        $customer = Customer::find(1);
        $tags2 = $customer->tags2;
        $this->assertEquals(count($tags2) , 2);
        
        //Vendor
        $vendor = new Vendor;
        $vendor->name = 'Japan systems'; 
        $vendor->save();
        $tags2 = $vendor->tags2;
        $tags2->add($tags[0]);
        $tags2->add($tags[1]);
        $tags2->save();
        Vendor::clear();
        $tags2 = $vendor->tags2;
        $this->assertEquals(count($tags2) , 2);
        
        //reverse
        $tag = $tags[0];
        $customers = $tag->customers2;
        $this->assertEquals(count($customers) , 1);
        $this->assertEquals($customers[0]->id , 1);
        
        $vendors = $tag->vendors2;
        $this->assertEquals(count($vendors) , 1);
        $this->assertEquals($vendors[0]->id , 1);
        
    }
    
    function testCreateMorphManyMany2()
    {
        $tags = Tag::all();
        // Test with 
        $customers = Customer::with('tags3')->keyValue(5)->where('id', 5)->get();
        $tags2 = $customers[0]->tags3;
        $tags2->add($tags[0]);
        $tags2->add($tags[1]);
        $tags2->add($tags[2]);
        $tags2->save();
        Customer::clear();
        $customers = Customer::with('tags3')->keyValue(5)->where('id', 5)->get();
        $tags2 = $customers[0]->tags3;
        $this->assertEquals(count($tags2) , 3);
        
        //Vendor
        $vendor = new Vendor;
        $vendor->name = 'Tokyo systems'; 
        $vendor->save();
        // No with
        $tags2 = $vendor->tags3;
        $tags2->add($tags[0]);
        $tags2->add($tags[1]);
        $tags2->add($tags[2]);
        $tags2->save();
        Vendor::clear();
        $tags2 = $vendor->tags3;
        $this->assertEquals(count($tags2) , 3);
        
        //reverse
        $tag = $tags[1];
        $customers = $tag->customers3;
        $this->assertEquals(count($customers) , 2);
        $this->assertEquals($customers[1]->id , 5);
        
        $vendors = $tag->vendors3;
        $this->assertEquals(count($vendors) , 2);
        $this->assertEquals($vendors[1]->id , 2);
    }
    
    function testWith()
    {
        $vendor = new Vendor;
        $vendor->name = 'Tokyo systems'; 
        $vendor->save();
        $vendors = Vendor::with('tags3')->keyValue(3)->where('id', 3)->get();
        $this->assertEquals(($vendors[0]->tags3 instanceof Collection)  , true);
        $this->assertEquals(count($vendors[0]->tags3) , 0);// return null OK!

        $customer = new Customer;
        $customer->id = 14;
        $customer->name = 'Takako12';
        $customer->save();
        $customers = Customer::with('group')->keyValue(14)->where('id', 14)->get();
        $this->assertEquals($customers[0]->group , null);// return null OK!

    }
    //ToDo with での取得とリレーションやコレクションの内容が正しいこと
}