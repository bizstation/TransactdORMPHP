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
use BizStation\Transactd\Benchmark;
use Transactd\QueryExecuter;
use Transactd\IOException;
use Transactd\Model;
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
    $masterUri = URI;
    $slaveUri = $masterUri;
    //$slaveUri = 'tdap://root@localhost:8611/ormtest?dbfile=test.bdf&pwd=1234';
    try {
        if ($masterUri !== $slaveUri) {
            sleep(1);
        }
        DB::connect($masterUri, $slaveUri);
    } catch (\Exception $e) {
        echo PHP_EOL.$e.PHP_EOL;
    }
}

class TableSchema
{
    private $def;
    private $tableid = 0;
    const UNIQUE = true;
    const DUPLICATABLE = false;
    const PRIMARY_KEY = true;
    const INDEX = false;
    public function __construct($def, $tableid) 
    {
        $this->def = $def;
        $this->tableid = $tableid;
    }
    
    function checkStat($obj)
    {
        if ($obj->stat()) {
            throw new IOException($obj->statMsg(), $obj->stat());
        }
    }
    
    function addField($name, $type, $len, $nullable=false)
    {
        $fd = $this->def->insertField($this->tableid, $this->def->tableDefs($this->tableid)->fieldCount);
        $fd->setName($name);
        $fd->type = $type;
        if ($type === Transactd::ft_myvarchar || $type === Transactd::ft_mychar) {
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
        $this->checkStat($this->def);
        return $fd;
    }
    
    function addIndex($fields, $unique, $primaryKey = false)
    {
        $td = $this->def->tableDefs($this->tableid);
        $keynum = $td->keyCount;
        $kd = $this->def->insertKey($this->tableid, $keynum);
        $i = 0;
        if (!is_array($fields)) {
            $fields = array($fields);
        }
        foreach($fields as $fdnum) {
            if (is_string($fdnum)) {
                $td = $this->def->tableDefs($this->tableid);
                $fdnum = $td->fieldNumByName($fdnum);
                if ($fdnum === -1) {
                    throw new \InvalidArgumentException();
                }
            }
            $kd->segment($i)->fieldNum = $fdnum;
            $kd->segment($i)->flags->bit8 = 1;
            $kd->segment($i)->flags->bit1 = 1;
            $kd->segment($i)->flags->bit0 = $unique ? 0 : 1;
            ++$i;
        }
        $kd->segmentCount = count($fields);
        if ($primaryKey) {
            $td = $this->def->tableDefs($this->tableid);
            $td->primaryKeyNum = $keynum;
        }
    }

    function save()
    {
        $this->def->updateTableDef($this->tableid);
        $this->checkStat($this->def);
    }
}

class  SchemaBuilder
{
    private $db;
    public function __construct($uri) 
    {
        $db = new Database();
        $db->create($uri);
        if ($db->stat() === Transactd::STATUS_TABLE_EXISTS_ERROR) {
            $db->drop(URI);
            $this->checkStat($db);
            $db->create(URI);
            $this->checkStat($db);
        }
        $db->open(URI, Transactd::TYPE_SCHEMA_BDF, Transactd::TD_OPEN_NORMAL);
        $this->checkStat($db);
        $this->db = $db;
    }

    function checkStat($obj)
    {
        if ($obj->stat()) {
            throw new IOException($obj->statMsg(), $obj->stat());
        }
    }
    
    function insertTable($tableid, $name)
    {
        $td = new Tabledef();
        $td->schemaCodePage = Transactd::CP_UTF8;
        $td->setTableName($name);
        $td->setFileName($name);
        $td->charsetIndex = Transactd::CHARSET_UTF8;
        $td->id = $tableid;
        $def = $this->db->dbDef();
        $def->insertTable($td);
        $this->checkStat($def);
        return new TableSchema($def, $tableid);
    }
    
    function addTable($name)
    {
        $def = $this->db->dbDef();
        $td = new Tabledef();
        $td->schemaCodePage = Transactd::CP_UTF8;
        $td->setTableName($name);
        $td->setFileName($name);
        $td->charsetIndex = Transactd::CHARSET_UTF8;
        $td->id = $def->tableCount() + 1;
        $def->insertTable($td);
        $this->checkStat($def);
        return new TableSchema($def, $td->id);
    }
   
    function close()
    {
        $this->db->close();
        unset($this->db);
    }
}

function createInvoiceSchema($sb)
{
    $tb = $sb->addTable('invoices');
    $tb->addField('id', Transactd::ft_integer, 4);
    $tb->addField('date', Transactd::ft_mydate, 3);
    $tb->addField('customer_id', Transactd::ft_integer, 4);
    $tb->addField('sales_amount', Transactd::ft_integer, 4);
    $tb->addField('tax_amount', Transactd::ft_integer, 4);
    $tb->addField('payment_amount', Transactd::ft_integer, 4);
    $tb->addField('balance_amount', Transactd::ft_integer, 4);
    $tb->addField('note', Transactd::ft_myvarchar, 20);
    $tb->addField('update_at', Transactd::ft_mytimestamp, 7);
    
    $tb->addIndex('id', TableSchema::UNIQUE, TableSchema::PRIMARY_KEY); 
    $tb->addIndex(['customer_id', 'date', 'id'], TableSchema::UNIQUE, TableSchema::INDEX); 
    $tb->addIndex(['date', 'id'], TableSchema::DUPLICATABLE, TableSchema::INDEX);
    $tb->save();
}

function createInvoiceItemSchema($sb)
{
    $tb = $sb->addTable('invoice_items');
    $tb->addField('invoice_id', Transactd::ft_integer, 4);
    $tb->addField('row', Transactd::ft_integer, 4);
    $tb->addField('line_type', Transactd::ft_integer, 1);
    $tb->addField('product_code', Transactd::ft_myvarchar, 20);
    $tb->addField('product_description', Transactd::ft_myvarchar, 30);
    $tb->addField('price', Transactd::ft_integer, 4);
    $tb->addField('quantity', Transactd::ft_integer, 4);
    $tb->addField('amount', Transactd::ft_integer, 4);
    $tb->addField('tax', Transactd::ft_integer, 4);
    $tb->addField('note', Transactd::ft_myvarchar, 20);
    $tb->addField('update_at', Transactd::ft_mytimestamp, 7);
    
    $tb->addIndex(['invoice_id', 'row'], TableSchema::UNIQUE, TableSchema::PRIMARY_KEY); 
    $tb->save();
}

function createCustomerSchema($sb)
{
    $tb = $sb->addTable('customers');
    $tb->addField('id', Transactd::ft_autoinc, 4);
    $tb->addField('name', Transactd::ft_myvarchar, 20);
    $tb->addField('update_at', Transactd::ft_mytimestamp, 7);
    
    $tb->addIndex('id', TableSchema::UNIQUE, TableSchema::PRIMARY_KEY); 
    $tb->save();
}

function createProductSchema($sb)
{
    $tb = $sb->addTable('products');
    $tb->addField('id', Transactd::ft_autoinc, 4);
    $tb->addField('code', Transactd::ft_myvarchar, 20);
    $tb->addField('description', Transactd::ft_myvarchar, 30);
    $tb->addField('price', Transactd::ft_integer, 4);
    $tb->addField('note', Transactd::ft_myvarchar, 20);
    $tb->addField('update_at', Transactd::ft_mytimestamp, 7);
    
    $tb->addIndex('id', TableSchema::UNIQUE, TableSchema::PRIMARY_KEY); 
    $tb->addIndex('code', TableSchema::UNIQUE, TableSchema::INDEX); 
    $tb->save();
}

function createStockSchema($sb)
{
    $tb = $sb->addTable('stocks');
    $tb->addField('code', Transactd::ft_myvarchar, 20);
    $tb->addField('quantity', Transactd::ft_integer, 4);
    $tb->addField('update_at', Transactd::ft_mytimestamp, 7);
    $tb->addIndex('code', TableSchema::UNIQUE, TableSchema::PRIMARY_KEY); 
    $tb->save();
}

function createDaylySummarySchema($sb)
{
    $tb = $sb->addTable('dayly_summaries');
    $tb->addField('date', Transactd::ft_mydate, 3);
    $tb->addField('slaes_amount', Transactd::ft_integer, 4);
    $tb->addField('tax_amount', Transactd::ft_integer, 4);
    $tb->addField('payment_amount', Transactd::ft_integer, 4);
    $tb->addIndex('date', TableSchema::UNIQUE, TableSchema::PRIMARY_KEY); 
    $tb->save();
}

function createTestData()
{
    try {
        $sb = new SchemaBuilder(URI);
        createInvoiceSchema($sb); 
        createInvoiceItemSchema($sb); 
        createCustomerSchema($sb);
        createProductSchema($sb);
        createStockSchema($sb);
        createDaylySummarySchema($sb);
        $sb->close();
    } catch(Exception $e) {
        echo PHP_EOL.$e->getMessage().PHP_EOL.PHP_EOL;
    }
}

if (createTestData() === false) {
    exit(1);
}

init();

class Customer extends Model
{
    protected static $guarded = ['id'];
    public function invoices()
    {
        return $this->hasMany('Invoice');
    }
    
    public function transactions($startDate, $endDate)
    {
        return Invoice::index(1)->keyValue($this->id, $startDate)->
                where('customer_id', $this->id)->where('date', '<=', $endDate)->get();
    }
}

class Product extends Model
{
    protected static $guarded = ['id'];
    public function stock()
    {
        return $this->hasOne('Stock', 0, 'code');
    }
}

class Stock extends Model
{
    protected static $guarded = [];
    public function product()
    {
        return $this->belongsTo('Product', 'code', 1);
    }
}


Trait AmountBase
{
    public $sales = 0;
    public $tax = 0;
    public $payment = 0;
    public $balance = 0;
    public function reset()
    {
        $this->sales = 0;
        $this->tax = 0;
        $this->payment = 0;
    }

    public function sum($amount)
    {
        $this->sales += $amount->sales;
        $this->tax += $amount->tax;
        $this->payment += $amount->payment;
    }
    
    public function total()
    {
       return $this->sales + $this->tax - $this->payment; 
    }
}

class DaylySummary extends Model
{
    use AmountBase;
    protected static $guarded = [];
    protected static $table = 'dayly_summaries';
    static protected $aliases  = ['slaes_amount' => 'sales', 'tax_amount' => 'tax', 'payment_amount' => 'payment'];
    
    public function invoices()
    {
        return $this->hasMany('Invoice', 2, 'date');
    }
}

class InvoiceAmount
{
    use AmountBase;
    
    private $oldBlance = null;
    
    public function difference()
    {
        return $this->balance - $this->oldBlance;
    }

    public function calc(Collection $rows, $base)
    {
        if ($this->oldBlance === null) {
            $this->oldBlance = $this->balance;
        }
        $this->reset();
        $ar = $rows->getNativeArray();
        foreach($ar as $row) {
            $this->increment($row);
        }
        $this->balance = $base + $this->total();
    }

    public function increment(InvoiceItem $row)
    {
        if ($row->line_type === InvoiceItem::SALES) {
            $this->sales += $row->amount;
            $this->tax += $row->tax;
        }elseif ($row->line_type === InvoiceItem::PAYMENT) {
            $this->payment += $row->amount;
        }
    }

    public function decrement(InvoiceItem $row)
    {
        if ($row->line_type === InvoiceItem::SALES) {
            $this->sales -= $row->amount;
            $this->tax -= $row->tax;
        }elseif ($row->line_type === InvoiceItem::PAYMENT) {
            $this->payment -= $row->amount;
        }
    }
}

class InvoiceItemSaveHandler
{
    private $date;
    public function __construct($date)
    {
        $this->date = $date;
    }

    public function onStart()
    {
        $this->amount = new InvoiceAmount;
    }

    public function onEnd()
    {
        $it = DaylySummary::index(0)->keyValue($this->date)->serverCursor();    
        if ($it->valid()) {
            $dayly = $it->current();
            $dayly->sum($this->amount);
            $it->update($dayly);
        }else {
            $dayly = new DaylySummary(['date' => $this->date]);
            $dayly->sum($this->amount);
            $it->insert($dayly);
        }
        DaylySummary::resetQuery();
        Stock::resetQuery();
    }

    public function onDeleteRow(InvoiceItem $row)
    {
        $this->amount->decrement($row);
        if ($row->line_type === InvoiceItem::SALES) {
            $it = Stock::keyValue($row->product_code)->serverCursor();
            $it->validOrFail();
            $stock = $it->current();
            $stock->quantity += $row->quantity;
            $it->update($stock);
            $stock->updateCache();
        }
    }

    public function onSaveRow(InvoiceItem $row)
    {
        $this->amount->increment($row);
        if ($row->line_type === InvoiceItem::SALES) {
            $it = Stock::keyValue($row->product_code)->serverCursor();
            if ($it->valid()) {
                $stock = $it->current();
                $stock->quantity -= $row->quantity;
                $it->update($stock);
            } else {
                $stock = new Stock(['code' => $row->product_code]);
                $stock->quantity = 0 - $row->quantity;
                $it->insert($stock);
            }
            $stock->updateCache();
        }
    }
}

class Invoice extends Model
{
    public static  $aliases  = ['sales_amount' => 'sales', 'tax_amount' => 'tax', 'payment_amount' => 'payment', 'balance_amount' => 'balance'];
    public static  $transferMap = ['sales' => 'amount', 'tax' => 'amount', 'payment' => 'amount', 'balance' => 'amount'];
    public static $guarded = ['id'];
    public $id = 0;
    public $date;
    public $amount = null;
    private $baseBalance = 0;
    const INSERT = true;
    public function __construct()
    {
        parent::__construct();
        $this->amount = new InvoiceAmount;
        $this->date = date("Y/m/d");
    }
    public function items()
    {
        return $this->hasMany('InvoiceItem');
    }
    public function customer()
    {
        return $this->belongsTo('Customer');
    }
    private function assignInvoiceNumber()
    {
        $it = Invoice::index(0)->serverCursor(QueryExecuter::SEEK_LAST);
        if ($it->valid()) {
            $inv = $it->current();
            $this->id = $inv->id + 1;
        } else {
            $this->id = 1;
        }
    }
    
    private function readBaseBalance()
    {
        $this->baseBalance = 0;
        $it = Invoice::index(1)->keyValue($this->customer_id, $this->date, $this->id)
                ->serverCursor(QueryExecuter::SEEK_LESSTHAN);
        if ($it->valid()) {
            $inv = $it->current();
            if ($inv->customer_id === $this->customer_id) {
                $this->baseBalance = $inv->amount->balance;
            }
        }
        return $it;
    }
    
    private function updateBalanceAmount($it, $difference)
    {
        if ($difference !== 0) {
            $it->next();
            foreach($it as $inv) {
                if ($inv->customer_id !== $this->customer_id) {
                    break;
                }
                $inv->amount->balance += $difference;
                $it->update($inv);// No cache update
                $inv->updateCache();
            }   
        }
    }
    
    private function conflictFail($inv)
    {
        if ($this->update_at !== $inv->update_at) {
            throw new Exception('This invoice is already changed by other user.');
        }
    }
        
    public function save($options = 0, $forceInsert = false)
    {
        InvoiceItem::$handler = new InvoiceItemSaveHandler($this->date);
        InvoiceItem::$handler->onStart();
        if ($this->id === 0) {
            $this->assignInvoiceNumber();
            $forceInsert = true;
        }
        $it = $this->readBaseBalance();

        $this->amount->calc($this->items, $this->baseBalance);
        if ($forceInsert) {
            $this->items->renumber('row');
            $it->insert($this);
            $it = $this->serverCursor(1, QueryExecuter::SEEK_EQUAL);
            $it->validOrFail();
        }else {
            $it = $this->serverCursor(1, QueryExecuter::SEEK_EQUAL);
            $it->validOrFail();
            $this->conflictFail($it->current());
            $it->update($this);
        }
        $this->items->save();
        $this->updateBalanceAmount($it, $this->amount->difference());
        InvoiceItem::$handler->onEnd();
    }
    
    public function delete($options = null)
    {
        InvoiceItem::$handler = new InvoiceItemSaveHandler($this->date);
        InvoiceItem::$handler->onStart();
        $it = $this->serverCursor(1, QueryExecuter::SEEK_EQUAL);
        $difference = $this->amount->total();
        $this->items->delete();
        $it->delete();
        $this->updateBalanceAmount($it, 0 - $difference);
        InvoiceItem::$handler->onEnd();
    }
        
    public function addSalesLine($code, $qty)
    {
        $prod = Product::index(1)->findOrFail($code);
        $item = new InvoiceItem;
        $item->assignSales($prod, $qty);
        $this->items->add($item);
        return $item;
    }
    
    public function addPaymentLine($amount, $desc)
    {
        $item = new InvoiceItem;
        $item->assignPayment($amount, $desc);
        $this->items->add($item);
        return $item;
    }
    
    private function prepareTables()
    {
        Stock::prepareTable();
        DaylySummary::prepareTable();
        InvoiceItem::prepareTable();
    }
    
    public function saveWithTransaction()
    {
        try {
            Benchmark::start();
            $this->prepareTables();
            DB::beginTrn(Transactd::MULTILOCK_GAP);
            $this->save();
            DB::commit();
            Benchmark::showTimeSec(true, ': Save invoice id = '. $this->id. ' items = ' . $this->items->count());
        } catch (Exception $e) {
            DB::rollBack(); 
            Benchmark::showTimeSec(true, ': Save invoice id = '. $this->id. ' items = ' . $this->items->count());
            throw $e;
        }
    }
    
    public function deleteWithTransaction()
    {
        try {
            Benchmark::start();
            $this->prepareTables();
            DB::beginTrn(Transactd::MULTILOCK_GAP);
            $this->delete();
            DB::commit();  
            Benchmark::showTimeSec(true, ': Delete invoice id = '. $this->id. ' items = ' . $this->items->count());
        } catch (Exception $e) {
            DB::rollBack(); 
            Benchmark::showTimeSec(true, ': Delete invoice id = '. $this->id. ' items = ' . $this->items->count());
            throw $e;
        }
    }
}

class InvoiceItem extends Model
{
    const SALES = 0;
    const PAYMENT = 1;
    const TAX_RATE = 0.08;
    public static $handler;
    protected static $table = 'invoice_items';
    protected static $guarded = [];
    
    public function invoice()
    {
        return $this->belongsTo('Invoice');
    }
    
    public function stock()
    {
        return $this->hasOne('Stock', 0, 'product_code');
    }
    
    public function product()
    {
        return $this->hasOne('Product', 1, 'product_code');
    }
    
    public function assignSales($prod, $qty)
    {
        $this->product_code = $prod->code;
        $this->product_description = $prod->description;
        $this->line_type = self::SALES;
        $this->price = $prod->price;
        $this->quantity = $qty;
        $this->amount = $prod->price * $qty;
        $this->tax = (int)(($this->amount * self::TAX_RATE) + 0.5);
    }
    public function assignPayment($amount, $desc)
    {
        $this->product_code = 'PAYMENT';
        $this->product_description = $desc;
        $this->line_type = self::PAYMENT;
        $this->amount = $amount;
    }
       
    public static function deleting(InvoiceItem $row)
    {
        self::$handler->onDeleteRow($row);
        return true;
    }
    
    public static function saving(InvoiceItem $row)
    {
        self::$handler->onSaveRow($row);
        return true;
    }
}

class Invoice2 extends Model
{
    protected static $connection = 'connection2'; 
    protected static $table = 'invoices'; 

}
if (class_exists('Thread')) {
    class InvoiceInsert extends Thread
    {
        public function __construct()
        {
            $this->id = 0;
            
        }
        public function run()
        {
            class_alias('Transactd\DatabaseManager', 'DB');
            DB::connect(URI, URI);
           
            $cust = Customer::find(3);
            $inv = new Invoice;
            $inv->customer()->associate($cust);
            $inv->addSalesLine('ORANGE1', 1);
            $inv->saveWithTransaction(0, true);
            $this->id = $inv->id;
        }
        public function getResult()
        {
            return $this->id;
        }
    }
}
const WAIT_UTIME = 10000;
class TransactdTest extends PHPUnit_Framework_TestCase
{
    private function sleep()
    {
        if (DB::master()->uri() !== DB::slave()->uri()) {
            usleep(WAIT_UTIME);
        }
    }
    private function addProduct($code, $desc, $price, $qty)
    {
        $prod = Product::create(['code' => $code, 'description' => $desc, 'price' => $price]);
        
        $stock = new Stock(['quantity' => $qty]);
        $stock->product()->associate($prod);
        $stock->save();
    }
    
    private function addCustomer($name)
    {
         Customer::create(['name' => $name]);
    }
    
    public function testCreateData()
    {
        $this->addProduct('CAR123', 'CAR 123', 5000000, 1);
        $this->addProduct('BIKE650', 'BIKE 650', 320000, 5);
        $this->addProduct('WATCH777', 'WATCH 777', 198000, 10);
        $this->addProduct('APPLE1', 'APPLE FUJI', 300, 120);
        $this->addProduct('APPLE2', 'APPLE SHINANO', 280, 240);
        $this->addProduct('ORANGE1', 'ORANGE UNSYUU', 320, 54);
        
        $this->addCustomer('Suzuki');
        $this->addCustomer('Honda');
        $this->addCustomer('Yamaha');
        $this->addCustomer('Kawasaki');
        $this->addCustomer('Matsuda');
        $this->addCustomer('Toyota');
    }
    
    /*
        code       quantity
       --------------------
       APPLE1       120
       APPLE2       240
       BIKE650        5
       CAR123         1
       ORANGE1       54
       WATCH777      10
     */
    public function testBalance_InsertBefore()
    {
        $cust = Customer::find(3);
        $inv = new Invoice;   // id 1
        $inv->customer()->associate($cust);
        $inv->addSalesLine('APPLE1', 12);       // 120 -> 108
        $inv->addSalesLine('ORANGE1', 2);       // 54 -> 52
        $inv->addPaymentLine(4579, 'Cash');
        $inv->saveWithTransaction();
        $this->sleep();
        //Stock
        $stock = Stock::find('APPLE1');
        $this->assertEquals($stock->quantity , 108);
        $stock = Stock::find('ORANGE1');       
        $this->assertEquals($stock->quantity , 52);
        //Summary
        $sum = DaylySummary::find($inv->date);
        $this->assertEquals($sum->sales , $inv->amount->sales);
        $this->assertEquals($sum->tax , $inv->amount->tax);
        $this->assertEquals($sum->payment , $inv->amount->payment);
        
        $inv = new Invoice; // id 2
        $inv->date = date('Y-m-d', strtotime("- 1 days"));
        $inv->customer()->associate($cust);
        $inv->addSalesLine('WATCH777', 1);    //10 -> 9
        $inv->addPaymentLine(10000, 'Cash');
        $inv->saveWithTransaction();
        $this->sleep();
        //Invoice::clear();
        $inv = Invoice::find(1);
        $this->assertEquals($inv->amount->balance , 203840);
    }
    
    public function testBalance_Update()
    {
        Invoice::clear();
        $inv = Invoice::find(2);
        $inv->items[1]->amount = 20000; //10000 -> 20000
        $inv->saveWithTransaction();
        $this->sleep();
        Invoice::clear();
        $inv = Invoice::find(2);
        $this->assertEquals($inv->amount->balance , 193840);
        
        $sum = DaylySummary::find($inv->date);
        $this->assertEquals($sum->sales , $inv->amount->sales);
        $this->assertEquals($sum->tax , $inv->amount->tax);
        $this->assertEquals($sum->payment , $inv->amount->payment);
    }
    
    public function testBalance_InsertUser()
    {
        $cust = Customer::find(1);
        $inv = new Invoice;  // id 3
        $inv->customer()->associate($cust);
        $inv->addSalesLine('ORANGE1', 1);
        $inv->addPaymentLine(10, 'Cash');
        $inv->saveWithTransaction();
        $this->sleep();
        
        Invoice::clear();
        $inv2 = Invoice::find(1);
        //Summary
        DaylySummary::clear();
        $sum = DaylySummary::find($inv->date);
        $this->assertEquals($sum->sales , $inv->amount->sales + $inv2->amount->sales);
        $this->assertEquals($sum->tax , $inv->amount->tax+ $inv2->amount->tax);
        $this->assertEquals($sum->payment , $inv->amount->payment+ $inv2->amount->payment);
        
        $inv = Invoice::find(2);
        $this->assertEquals($inv->amount->balance , 193840);
    }
    
    public function testUpdateConlict()
    {
        Invoice::clear();
        $inv = Invoice::find(2);
        $this->assertEquals($inv === null , false);
        $inv2 = Invoice::find(2);
        $inv->saveWithTransaction();
        try {
            $inv2->saveWithTransaction();
            $this->assertEquals(true , false);
        } catch (Exception $ex) {
            $this->assertEquals(true , true);
        }
    }   
    
    public function testDelete()
    {
        Invoice::clear();
        $inv = Invoice::find(2);
        $this->assertEquals($inv === null , false);
        $inv->deleteWithTransaction();
        $this->sleep();
        $inv2 = Invoice::find(1);
        $this->assertEquals($inv2->amount->balance , 0);
        
        DaylySummary::clear();
        $sum = DaylySummary::find($inv->date);
        $this->assertEquals($sum->sales , 0);
        $this->assertEquals($sum->tax , 0);
        $this->assertEquals($sum->payment , 0);
    }
    
    public function test_InvoiceNumber()
    {
        if (class_exists('Thread')) {
            $thread = new InvoiceInsert();
            DB::connect(URI, URI, 'connection2');
            Invoice2::prepareTable();
            DB::connection('connection2')->beginTransaction();
            $thread->start();
            $id = 0;
            usleep(1000000);
            $it = Invoice2::index(0)->serverCursor(QueryExecuter::SEEK_LAST);
            
            if ($it->valid()) {
                $inv = $it->current();
                $id = $inv->id + 1;
                $inv->id = $id;
                $inv->save();
            } else {
                $id = 1;
            }
            DB::connection('connection2')->commit();
            $thread->join();
            $this->assertNotEquals($thread->getResult() , $id);
        }
    }
    
    public function test_Relation()
    {
        $inv = Invoice::find(1);
        $this->assertEquals($inv->customer->name , 'Yamaha');
        
        $cust = Customer::find(3);
        $invs = $cust->invoices;
        $this->assertEquals(count($invs) , 1);
        
        $inv = $invs[0];
        $items = $inv->items;
        $this->assertEquals(count($items) , 3);
        $this->assertEquals($items[0]->stock->code, 'APPLE1');
        $this->assertEquals($items[0]->stock->quantity, 108);
        $this->assertEquals($items[0]->stock->product->description, 'APPLE FUJI');
        $this->assertEquals($items[0]->product->stock->quantity, 108);
        $this->assertEquals($items[0]->invoice->id, 1);
        
        $summery = DaylySummary::find($inv->date);
        $invs = $summery->invoices;
        $this->assertEquals(count($invs) , 2);
        
        $cust = Customer::find(3);
        $invs = $cust->transactions($inv->date, $inv->date);
        $this->assertEquals(count($invs) , 1);
    }
}