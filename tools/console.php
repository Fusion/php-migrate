<?php
/**
 * @package Lenses
 * @copyright (c) Chris F. Ravenscroft
 * @license See 'license.txt'
 */
global $WHEREAMI, $medium;
$postinput = '';
if(empty($_ENV['SHELL']))
{
	// Web invocation
	$WHEREAMI = dirname(getcwd());
	include($WHEREAMI.'/config.php');
	$ip = $_SERVER['REMOTE_ADDR'];
	if(!Config::$webcli || !in_array($ip, Config::$webcliips))
		die("Sorry, command-line only!");
	$medium = 'w';
	$prompt = '<body onload="document.getElementById(\'input\').focus();"><form method="post" action="console.php">Console > <input type="text" name="input" id="input" value="" style="width:800px;" /></form>';
	if(!empty($_POST['input']))
		$postinput = $_POST['input'];
}
else
{
	// Shell
	if(empty($argv[1]))
		die("Use 'please' rather than invoking this script directly!\n");
	$WHEREAMI = $argv[1];
	include($WHEREAMI.'/config.php');
	$medium = 'c';
	$prompt = 'Console > ';
}
$cli = array(
	'migrate' => array(
		'up' => array(
			'-1' => 'migrate up [version]',
			'0' => "migrate_model_up",
			'1' => "migrate_model_up"),
		'down' => array(
			'-1' => 'migrate down <version>',
			'1' => "migrate_model_down"),
	),
);

$depth = 0;

$innerWelcome = ($medium == 'w' ? '' : "Type '.' to exit.");
echo _format("Welcome to the Console. $innerWelcome\n");

$stdin = fopen('php://stdin', 'r');
echo _format($prompt);
$tokens = array();
$incomplete = false;
while(($line = fgets($stdin)) || !empty($postinput))
{
	if(!empty($postinput))
	{
		$line = $postinput;
		$postinput = '';
	}
	$probe = &$cli;
	$collectingArgs = false;
	$args = array();
	$line = str_replace(array("\n", "\r"), array('', ''), $line);
	if(empty($line))
	{
		$incomplete = false;
		echo _format($prompt);
		continue;
	}
	if($line == '.') break;
	$input = explode(' ', $line);
	if($incomplete)
	{
		if(empty($line))
		{
			$input = $tokens;
		}
		else
		{
			$input = array_merge($tokens, $input);
		}
	}
	$tokens = array();
	$incomplete = true;
	$curArgStr = '';
	foreach($input as $token)
	{
		if($collectingArgs)
		{
			if(empty($curArgStr))
			{
				if($token{0} == '"')
					$curArgStr = substr($token, 1);
				else
					$args[] = $token;
			}
			else
			{
				if($token{strlen($token)-1} == '"')
				{
					$args[] = $curArgStr.' '.substr($token, 0, strlen($token)-1);
					$curArgStr = '';
				}
				else
					$curArgStr .= ' '.$token;
			}
		}
		else if(isset($probe[$token]))
		{
			$tokens[] = $token;
			$probe = &$probe[$token];	
			if(!is_array($probe))
			{
				$arrptr = array($probe);
				$incomplete = false;
				$collectingArgs = true;
			}
			else
			{
				$keys = array_keys($probe);
				if(is_int($keys[0]))
				{
					$arrptr = &$probe;
					$incomplete = false;
					$collectingArgs = true;
				}
			}
		}
		else
		{
			echo _format("Syntax error: {$token}?\n");
			break;
		}
	}
	if($incomplete)
	{
		$comma = '';
		echo _format("Syntax: " . implode(' ', $tokens) . " < ");
		foreach($probe as $potentialToken => $whatever)
		{
			echo _format("$comma$potentialToken");
			$comma = ' | ';
		}
		echo _format(" >\n");
	}
	else if($collectingArgs)
	{
		$idx = count($args);
		if(!empty($arrptr[$idx]))
		{
			$fn = $arrptr[$idx];
			$fn($args);
		}
		else
		{
			echo _format("Wrong number of arguments: ".$arrptr[-1]."\n");
			$incomplete = true;
		}
	}
	if($medium != 'w')
		echo $prompt;
	if($incomplete)
	{
		if(0 < count($tokens))
			$value = implode(' ', $tokens) . ' ';
		else
			$value = '';
		if($medium != 'w')
			echo $value;
		else
			echo "<script>\ndocument.getElementById('input').value = '$value';\n</script>\n";
	}
}

if($medium != 'w')
	echo _format("Good bye!\n");

function _format($str)
{
global $medium;

	if($medium == 'w')
		$str = str_replace("\n", "<br />\n", $str);
	return $str;
}

function _open_db()
{
global $db, $WHEREAMI;

	if(!isset($db))
	{
		include($WHEREAMI.'/libs/adodb/adodb.inc.php');
		$db = NewADOConnection(
			Config::$dbengine.'://'.
			Config::$dbuser.':'.
			Config::$dbpassword.'@'.
			Config::$dbhost.'/'.
			Config::$dbname);
	}
	if(!$db)
	{
		echo _format("Unable to access database. Bad configuration in config.php?\n");
		return false;
	}
	return true;
}

function _create_dict()
{
global $db, $dict, $WHEREAMI;

	if(!isset($dict))
	{
		include($WHEREAMI.'/libs/adodb/adodb-datadict.inc.php');
		$dict = NewDataDictionary($db);
	}
	return $dict;
}

function _create_yml_parser()
{
	global $parserLoaded, $WHEREAMI;
	if(!isset($parserLoaded))
	{
		include($WHEREAMI.'/libs/spyc/spyc-php5.php');
		$parserLoaded = true;
	}
}

function _m_check_table($table_name)
{
	global $db;

	if(!_open_db()) return false;
	$qry = "SELECT id FROM $table_name";
	$rs = &$db->execute($qry);
	return (false !== $rs);
}

function _m_execute($qry)
{
	global $db;

	if(!_open_db()) return false;
	if(!$db->execute($qry))
	{
		echo _format("! Error executing '$qry'\n");
		return false;
	}
	return true;
}

function _m_execute_multi($qries)
{
        foreach($qries as $qry)
        {
                if(!_m_execute($qry))
                        return false;
        }
	return true;
}

function _m_query($qry)
{
	global $db;

	if(!_open_db()) return false;
	$rs = &$db->execute($qry);
	if(!$rs)
	{
		echo _format("! Error executing '$qry'\n");
		return false;
	}
	$ret = array();
	while(!$rs->EOF)
	{
		$ret[] = $rs->fields;
		$rs->moveNext();
	}
	return $ret;
}

function _m_create_table($table_name, $field_defs)
{
	global $db, $dict;
	if(!_open_db() || !_create_dict()) return false;
	$sql = $dict->CreateTableSQL($table_name, $field_defs, array());
	if(!$sql)
	{
		echo _format("! Error creating table '$table_name'\n");
		return false;
	}
	foreach($sql as $qry)
	{
		if(!$db->execute($qry))
		{
			echo _format("! Error creating table '$table_name':\n\"$qry\"\n");
			return false;
		}
	}
	print "Created table '$table_name'\n";
	return true;
}

function _m_drop_table($table_name)
{
	global $db, $dict;
	if(!_open_db() || !_create_dict()) return false;
	$sql = $dict->DropTableSQL($table_name);
	if(!$sql)
	{
		echo _format("! Error dropping table '$table_name'\n");
		return false;
	}
	foreach($sql as $qry)
	{
		if(!$db->execute($qry))
		{
			echo _format("! Error dropping table '$table_name':\n\"$qry\"\n");
			return false;
		}
	}
	print "Dropped table '$table_name'\n";
	return true;
}

function _m_error()
{
	throw new Exception('Migration problem');
}

function _migrate($mo)
{
	if(!empty($mo['drop']))
	{
		foreach($mo['drop'] as $row)
		{
			if(!_m_drop_table($row['name']))
				_m_error();
		}
	}
	if(!empty($mo['create']))
	{
		foreach($mo['create'] as $row)
		{
			if(!_m_create_table(
				$row['name'],
				$row['info']))
				_m_error();
		}
	}
	if(!empty($mo['execute']))
	{
		foreach($mo['execute'] as $row)
		{
			if(!_m_execute($row['query']))
				_m_error();
		}
	}
}

function _prepare_to_migrate()
{
	// First, does system table exist?
	if(!_m_check_table('system'))
	{
		if(!_m_create_table(
			'system',
			"
			id	I		AUTO KEY,
			setting	VARCHAR(32)	INDEX setting NOTNULL,
			value   VARCHAR(64)     NOTNULL
			"))
			return false;
		if(!_m_execute("INSERT INTO `system`(`setting`,`value`) VALUES('version', 0)"))
			return false;
	}
	$row = _m_query("SELECT * FROM `system` WHERE `setting`='version'");
	if(count($row) != 1)
	{
		echo _format("! Wrong row number when reading version from system table\n");
		return false;
	}
	$curVersion  = intval($row[0]['value']);
	if(0 > $curVersion)
	{
		echo _format("Sorry, but it appears that the database got corrupted while trying to migrate to version ".
			(-1 * $curVersion).".\nThe database needs to be fixed before any new migration.\n");
		return false;
	}
	return $curVersion;
}

function migrate_model_down($args = null)
{
global $WHEREAMI;

	$targetVersion = intval($args[0]);
	if(0 > $targetVersion)
	{
		echo _format("Wrong parameter for version number\n");
		return false;
	}
	$curVersion = _prepare_to_migrate();
	if(false === $curVersion)
		return false;
	if($curVersion <= $targetVersion)
	{
		echo _format("Nothing to do: current version=$curVersion, target version=$targetVersion\n");
		return true;
	}
	$nextVersion = $curVersion - 1;
	try
	{
		while($nextVersion >= $targetVersion)
		{
			$fName = $WHEREAMI . '/migrations/' . sprintf('%03d', ($nextVersion + 1)) . '.yml';
			if(!file_exists($fName))
				break;
			echo _format("----------------------------------------\n");
			echo _format("Migrating from version $curVersion to version $nextVersion\n");
			echo _format("----------------------------------------\n");
			_create_yml_parser();
			$arr = Spyc::YAMLLoad($fName);
			$down   = &$arr['down'];
			_migrate($down);
			unset($arr);
			$curVersion = $nextVersion;
			$nextVersion --;
		}
	}
	catch(Exception $e)
	{
		// Oh no I failed! Store wannabe version number...with a twist: it's negative!
		_m_execute("UPDATE `system` SET `value`=".(-1 * $nextVersion)." WHERE `setting`='version'");
		echo _format("Alas, there was an issue migrating from #$curVersion to $nextVersion!\n");
		return false;
	}
	_m_execute("UPDATE `system` SET `value`=".($nextVersion + 1)." WHERE `setting`='version'");
	echo _format("Database fully migrated to version ".($nextVersion + 1).".\n");
	return true;
}

function migrate_model_up($args = null)
{
global $WHEREAMI;

	if(!empty($args) && !empty($args[0]))
		$targetVersion = intval($args[0]);
	else
		$targetVersion = 999999;
	if(0 >= $targetVersion)
	{
		echo _format("Wrong parameter for version number\n");
		return false;
	}
	$curVersion = _prepare_to_migrate();
	if(false === $curVersion)
		return false;
	if($curVersion >= $targetVersion)
	{
		echo _format("Nothing to do: current version=$curVersion, target version=$targetVersion\n");
		return true;
	}
	$nextVersion = $curVersion + 1;
	try
	{
		while($nextVersion <= $targetVersion)
		{
			$fName = $WHEREAMI . '/migrations/' . sprintf('%03d', $nextVersion) . '.yml';
			if(!file_exists($fName))
				break;
			echo _format("----------------------------------------\n");
			echo _format("Migrating from version $curVersion to version $nextVersion\n");
			echo _format("----------------------------------------\n");
			_create_yml_parser();
			$arr = Spyc::YAMLLoad($fName);
			$up   = &$arr['up'];
			_migrate($up);
			unset($arr);
			$curVersion = $nextVersion;
			$nextVersion ++;
		}
	}
	catch(Exception $e)
	{
		// Oh no I failed! Store wannabe version number...with a twist: it's negative!
		_m_execute("UPDATE `system` SET `value`=".(-1 * $nextVersion)." WHERE `setting`='version'");
		echo _format("Alas, there was an issue migrating from #$curVersion to $nextVersion!\n");
		return false;
	}
	_m_execute("UPDATE `system` SET `value`=".($nextVersion - 1)." WHERE `setting`='version'");
	echo _format("\n# Database fully migrated to version ".($nextVersion - 1).".\n");
	return true;
}
?>
