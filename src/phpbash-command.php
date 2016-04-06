<?

require_once 'phpbash-lib.php';

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

$config = array(
   'mode' => $mode,
   'default_cwd' => '..',
   'log' => array('file' => realpath('.').'/phpbash.log', 'maxsize' => 50000)
);

exec_command($config, $command, $stdin, $state);

?>