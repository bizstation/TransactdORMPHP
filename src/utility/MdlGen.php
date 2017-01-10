<?php
require("transactd.php");
/**
 * Transactd PHP ORM Model genarator
 * 
 * Genarate a Model from exists database.
 */
use BizStation\Transactd\Transactd;
use BizStation\Transactd\Database;

const INDENT = '    ';

function get_singular_dictionary()
{
    return array('children' => 'child',
                'people' => 'person',
                'men' => 'man',
                'teeth' => 'tooth',
                'feet' => 'foot',
                'woman' => 'woman',
                'gentlemen' => 'gentleman',
                'mice' => 'mouse',
                'oxen' => 'ox',
                'data' => 'datum',
                'indeces' => 'index',
                'appendices' => 'appendix');
}

function get_singular($tableName)
{
    $singularDictionary = get_singular_dictionary();
    if (array_key_exists($tableName, $singularDictionary)) {
        return $singularDictionary[$tableName];
    }
    $s = mb_substr($tableName, -3);
    if ($s === 'ves') {
        return mb_substr($tableName, 0, -3).'f';
        // return mb_substr($tableName, 0, -3).'fe'; Not support
    }
    if ($s === 'ies') {
        return mb_substr($tableName, 0, -3).'y';
    }
    $s = mb_substr($tableName, -2);
    if ($s === 'es') {
        $s3 = mb_substr($tableName, -2, -1);
        if ($s3 === 'g' || $s3 === 's') {
            return mb_substr($tableName, 0, -1);
        }
        return mb_substr($tableName, 0, -2);
    }
    if ($s === 'ys') {
        return mb_substr($tableName, 0, -1);
    }
    
    $s = mb_substr($tableName, -1);
    if ($s === 's') {
        return mb_substr($tableName, 0, -1);
    }
    return $tableName;
}

function getPhpType($type)
{
    switch ($type) {
    case transactd::ft_integer:
    case transactd::ft_uinteger:
    case transactd::ft_autoinc:
    case transactd::ft_autoIncUnsigned:
    case transactd::ft_logical:
    case transactd::ft_bit:
        return 'integer';
    case transactd::ft_float:
    case transactd::ft_decimal:
    case transactd::ft_money:
    case transactd::ft_numeric:
    case transactd::ft_bfloat:
    case transactd::ft_numericsts:
    case transactd::ft_numericsa:
    case transactd::ft_currency:
        return 'float';
    default:
        return 'string';
    }
}

function makeHeader(&$s, $namespace)
{
    $s .= '<?php'.PHP_EOL;
    //$s .= 'require __DIR__ . \'/vendor/autoload.php\''.PHP_EOL;
    if ($namespace !== '') {
        $s .= 'namespace '.$namespace.';'.PHP_EOL;
    }
    $s .= PHP_EOL.'use Transactd\Model;'.PHP_EOL;
    $s .= PHP_EOL;
}

function makeClassDoc(&$s, $td, $aliases)
{
    $aliasCode = '';
    $s .= '/**'.PHP_EOL;
    $s .= ' * Original table name:'.$td->tableName().PHP_EOL;
    $s .= ' * '.PHP_EOL;
    $count =  $td->fieldCount;
    for ($i = 0; $i < $count; ++$i) {
        $fd = $td->fieldDef($i);
        $name = $fd->name();
        if (array_key_exists($name, $aliases)) {
            $name = $aliases[$name];
            $aliasCode .= INDENT.INDENT.'\''.$fd->name().'\' => \''.$name.'\','.PHP_EOL;
        }
        $s .= ' * @property '.sprintf('%-7s', getPhpType($fd->type))
            .' $'.$name.PHP_EOL;
    }
    $s .= ' */'.PHP_EOL;
    if ($aliasCode !== '') {
        $aliasCode = mb_substr($aliasCode, strlen(INDENT)*2, -3);
    }
    return $aliasCode;
}

function removeUnderscore($tableName)
{
    $tokens = preg_split("/_/", $tableName);
    if (count($tokens) > 1) {
        $tmp = '';
        foreach ($tokens as $token) {
            $tmp .= ucfirst($token);
        }
        return $tmp;
    } else {
        return $tableName;
    }
}

function makeClass(&$s, $td, $aliases, $aliasCode)
{
    $tableNameTmp = $td->tableName();
    if (array_key_exists($td->tableName(), $aliases)) {
        $tableName = $aliases[$tableNameTmp];
    } else {
        $tableNameTmp = get_singular($td->tableName());
        $tableName = removeUnderscore($tableNameTmp);
    }
    $wirteTableName = $tableName !== $tableNameTmp;
    $className = ucfirst($tableName);
    $s = $s.'class '.$className.' extends Model'.PHP_EOL;
    $s = $s.'{'.PHP_EOL;
    if ($wirteTableName) {
        $s .= INDENT.'protected static $table = \''
            .$td->tableName().'\';'.PHP_EOL;
    }
    if ($aliasCode !== '') {
        $s .= INDENT.'protected static $aliases = ['
            .$aliasCode.'];'.PHP_EOL;
    }
    $s = $s.'}'.PHP_EOL;
    return $className;
}

function writeToFile($className, $dir, $s)
{
    $filename = $dir !=='' ? $dir.DIRECTORY_SEPARATOR.$className.'.php' : $className.'.php';
    if (!file_put_contents($filename, $s)) {
        throw new \Exception('Error: Can not save '.$filename);
    }
    echo PHP_EOL.'output = '.$filename.PHP_EOL;
}

function generateModel($td, $aliases, $namespace, $dir)
{
    $s = '';
    makeHeader($s,  $namespace);
    $aliasCode = makeClassDoc($s, $td, $aliases);
    $className = makeClass($s, $td, $aliases, $aliasCode);
    writeToFile($className, $dir, $s);
}

function getTableDef($db, $uri, $tableName)
{
    echo PHP_EOL.'URI='.$uri.PHP_EOL.PHP_EOL;
    $db->open($uri, Transactd::TYPE_SCHEMA_BDF, Transactd::TD_OPEN_READONLY);
    if ($db->stat()) {
        throw new \RuntimeException($db->statMsg(), $db->stat());
    }
    $tb = $db->openTable($tableName, Transactd::TD_OPEN_READONLY);
    if ($db->stat()) {
        throw new \RuntimeException($db->statMsg(), $db->stat());
    }
    $td = $tb->tableDef();
    if ($td === null) {
        throw new \InvalidArgumentException('Error: Invalid table anme '.$tableName);
    }
    return $td;
}

function perseUri($opt)
{
    $user = array_key_exists('u', $opt) ? $opt['u'].'@' : '';
    $pwd = array_key_exists('p', $opt) ? '?pwd='.$opt['p'] : '';
    return 'tdap://'.$user.$opt['s'].'/'.$opt['d'].$pwd;
}

function perseAlias($opt)
{
    $aliasFile = array_key_exists('a', $opt) ? $opt['a']: false;
    $aliases = array();
    if ($aliasFile !== false) {
        $aliases = parse_ini_file($aliasFile);
        if ($aliases === false) {
            throw new \InvalidArgumentException('Error: Invalid alias list file.');
        }
    }
    return $aliases;
}

function perseDir($opt, $namespace)
{
    $dir = '';
    if (array_key_exists('o', $opt)) {
        if (!file_exists($opt['o'])) {
            throw new \InvalidArgumentException('Error: Directry '.$opt['o'].' is not exists.');
        }
        $dir = $opt['o'];
    }
    if ($dir === '') {
        $dir =  __DIR__ 
                .DIRECTORY_SEPARATOR
                .'..'.DIRECTORY_SEPARATOR
                .'..'.DIRECTORY_SEPARATOR
                .'..'.DIRECTORY_SEPARATOR
                .'..'.DIRECTORY_SEPARATOR
                .'..'.DIRECTORY_SEPARATOR.$namespace;
        if (mb_substr($dir, -1) === DIRECTORY_SEPARATOR) {
            $dir = mb_substr($dir, 0, -1);
        }
        if (!file_exists($dir)) {
            throw new \InvalidArgumentException('Error: Directry '. $dir .' is not exists.');
        }
    }
    return realpath($dir);
}

function printUSAGE()
{
    echo PHP_EOL.PHP_EOL;
    echo 'USAGE: php mdlgen.php -s[server] -d[database] -t[table]'
         .' -u[userName] -p[password] -a[aliasList] -n[namespace] -o[output-directory]'.PHP_EOL.PHP_EOL;
    echo '  -s : Name or ipaddress of a Transactd server.'.PHP_EOL;
    echo '  -d : Database name.'.PHP_EOL;
    echo '  -t : Table name of generate target.'.PHP_EOL;
    echo '  -u : [optional] Username for server access.'.PHP_EOL;
    echo '  -p : [optional] Password for server access.'.PHP_EOL;
    echo '  -a : [optional] File name of alias list. (ini file format (key=value))'.PHP_EOL;
    echo '  -n : [optional] Namespace of the target Model.'.PHP_EOL;
    echo '  -o : [optional] Output directry name.(Include namespace)'.PHP_EOL;
}

function main()
{
    $db = new Database();
    try {
        $opt = getopt("s:d:t:u::p::a::n::o::");
        $namespace = array_key_exists('n', $opt) ? $opt['n'] : '';
        $dir = perseDir($opt, $namespace);
        $td = getTableDef($db, perseUri($opt), $opt['t']);
        generateModel($td, perseAlias($opt), $namespace, $dir);
        $db->close();
        echo 'Generate done!'.PHP_EOL;
    } catch (Exception $e) {
        $db->close();
        echo $e->getMessage();
        printUSAGE();
        return 1;
    }
    return 0;
}

exit(main());
