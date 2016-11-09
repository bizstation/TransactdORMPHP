TransactdORMPHP
===============================================================================
The TransactdORMPHP is PHP ORM library using the Transactd plugin for MySQL/MariaDB.
It is a fastest ORM for PHP.


Description
-------------------------------------------------------------------------------
- High-speed database access.
- A Model is possible to high speed access to properties.
- It can be used as the ActiveRecord.
- Easy to migrate from the Laravel in similar interface to Laravel5.
- It does not depend on any framework. Also available on any PHP framework. 


Execution environment
-------------------------------------------------------------------------------
### Database
- MySQL 5.6/MariaDB 5.5 or later
- [Transactd Plugin 3.6 or later](http://www.bizstation.jp/en/transactd/documents/install_plugin.html)

### Client
- [Transactd PHP Clinet](http://www.bizstation.jp/en/transactd/documents/install_guide_php.html)


Install
-------------------------------------------------------------------------------
### Composer
* Install via the composer.
```
$ cd [yourProjectDirectory]
$ composer require Trnasactd/orm
```
* Add the following to the beginning of your code.(If not added.)
```
<?php
require __DIR__ . '/vendor/autoload.php'
```
### Manual
1. [Download form the GitHub](https://github.com/bizstation/TransactdORMPHP/archive/master.zip).

2. Extruct to your project directry.
3. Add the following to the beginning of your code.
```
<?php
require __DIR__ . '/TransactdORMPHP-master/src/Require.php'
```


Connect to databases
-------------------------------------------------------------------------------
Master and Slave host, these are possible to same host.
If you specify a different host, the write operation is the master and the read
 operation is processed by the slave.

### Laravel 5.1 above
* Add the following class to your config/app.php service providers list.
```
Transactd\boot\Laravel\TransactdLaravelServiceProvider::class,
```
* Add following parameters to your .env file.
```
// Master and Slave. These are possible to same host.
TRANSACTD_MASTER=tdap://yousername@your_master_host/your_database?&pwd=xxxx
TRANSACTD_SLAVE=tdap://yousername@your_slave_host/your_database?&pwd=xxxx

```

### Otherwise
* Add the following code to your application code at beggining.
```
class_alias('Transactd\DatabaseManager', 'DB');
$masterUri = 'tdap://yousername@your_master_host/your_database?&pwd=xxxx';
$slaveUri = 'tdap://yousername@your_slave_host/your_database?&pwd=xxxx';
DB::connect($masterUri, $slaveUri);

```


Usage example
-------------------------------------------------------------------------------
Table names and field names follow the rules of ActiveRecord.

```php:
<?php
require __DIR__ . '/TransactdORMPHP-master/src/Require.php'

use BizStation\Transactd\Transactd;
use BizStation\Transactd\Database;
use Transactd\Model;

class Group extends Model
{
	public function customers()
	{
		this->hasMany('Customer', 'group')
	}
}

class Customer extends Model
{
	public function group()
	{
		return $this->belongsTo('Group', 'group', 'id');
	}
}

// Connect to databases (Master and Slave. These are possible to same host.)
class_alias('Transactd\DatabaseManager', 'DB');
$masterUri = 'tdap://root@masterhost/test?pwd=xxxx';
$slaveUri = 'tdap://root@slavehost/test?pwd=xxxx';
DB::connect($masterUri, $slaveUri);

// Get all customers
$customers = Customer::all();
echo 'The first customer's id = '. $customers[0]->id;

// Get all customers with group relationship in a snapshot.
DB::beginSnapshot();
$customers = Customer::with('group')->all();
DB::endSnapshot();

// Find a customer id = 1
$customer = Customer::find(1);
echo 'The customer name is '. $customer->name;

// Find customers that group number is 3.
// Transactd query are required that index number and keyValue for start of search.
$customers = Customer::index(1)->keyValue(3)->where('group','=', 3)->get();
echo 'There are '.count($customers).' customers in Group 3.';

// Get a group ralationship from a customer. (belongsTo)
$customer = Customer::find(1);
$group = $customer->group;

// Get customers ralationship from a group. (hasMany)
$group = Group::find(1);
$customers = $group->customers;

// Save a new customer.
$customer = new Customer();
$customer->id = 0;  //autoincrement
$customer->group = 1;
$customer->save();

// Delete a customer in a transaction.
DB::beginTransaction();
$customer->delete();
DB::commit();

// Using native API
$db = DB::master();
$tb = $db->openTable('customrs');
...

```


Documents
-------------------------------------------------------------------------------
1. [Tutorial](http://www.bizstation.jp/en/transactd/documents/tutorial_php_orm.html)
2. [Trnasactd ORM for PHP API](http://www.bizstation.jp/en/transactd/documents/php/orm/index.html)
3. [Trnasactd Client PHP API](http://www.bizstation.jp/en/transactd/documents/php/api/index.html)


Bug reporting, requests and questions
-------------------------------------------------------------------------------
If you have any bug-reporting, requests or questions, please send it to
[Issues tracker on github](https://github.com/bizstation/TransactdORMPHP/issues).


License
-------------------------------------------------------------------------------
This package is licensed under the MIT license.
