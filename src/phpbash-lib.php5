<?

function my_die($status, $msg = '')
{
  header('HTTP/1.0 210 ' . $status);
  die($msg);
}

function my_log($logconfig, $towrite)
{
  $logfile = $logconfig['file'];
  $maxsize = $logconfig['maxsize'];
  if ($logfile && $maxsize != 0) {
    if ($maxsize > 0 && filesize($logfile) > $maxsize)
      unlink($logfile);
    file_put_contents($logfile, $towrite, FILE_APPEND);
  }
}

function env_to_array($env, $ignores)
{
  $ex = explode("\n", $env);
  $a = array();
  foreach ($ex as $entry)
  {
    if (!$entry)
      continue;
    list($name, $value) = explode('=', $entry, 2);
    if (isset($ignores[$name]))
      continue;
    $a[$name] = $value;
  }
  return $a;
}

function env_of_array($a, $prefix = '')
{
  $cmd = '';
  foreach ($a as $name => $value)
    $cmd .= $prefix . $name . '=' . $value . "\n";
  return $cmd;
}

function exec_command($config, $command, $stdin, $state)
{
  $mode = $config['mode'];
  $log = $config['log'];
  $default_cwd = $config['default_cwd'];

  $command = trim($command);

  if (isset($state['cwd']) && $state['cwd'])
    chdir($state['cwd']);
  else
    chdir($default_cwd);

  if ($mode == 'bash')
  {
    $fd = array(
      0 => array('pipe', 'r'),
      1 => array('pipe', 'w'),
      3 => array('pipe', 'w'),
      4 => array('pipe', 'w'),
      5 => array('pipe', 'w'),
      6 => array('pipe', 'r'));

    $bash = proc_open('/bin/bash -s 2>&1', $fd, $pipes, NULL, array());

    is_resource($bash) || my_die('proc_open failed');

    my_log($log, '# '.date('c')."\n");

    if (isset($state['env'])) {
      // restore the environment
      $towrite = env_of_array($state['env'], 'export ');
      my_log($log, $towrite);
      fwrite($pipes[0], $towrite);
    }
    if (isset($state['localenv'])) {
      // restore the local environment
      $towrite = env_of_array($state['localenv']);
      my_log($log, $towrite);
      fwrite($pipes[0], $towrite);
    }
    // redirects stdin (if the command already have one, it should be run between () )
    $towrite = $command.' 0<&6'."\n";
    fwrite($pipes[0], $towrite);
    my_log($log, $towrite);
    if ($stdin)
      fwrite($pipes[6], $stdin);
    fclose($pipes[6]);
    fwrite($pipes[0], 'echo $? 1>&3'."\n");
    fwrite($pipes[0], 'env 1>&4'."\n");
    fwrite($pipes[0], 'set -o posix; set 1>&5'."\n"); // get only variables, not functions (todo)
    fclose($pipes[0]);

    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $code = stream_get_contents($pipes[3]);
    fclose($pipes[3]);
    $env = stream_get_contents($pipes[4]);
    fclose($pipes[4]);
    $localenv = stream_get_contents($pipes[5]);
    fclose($pipes[5]);
    
    proc_close($bash);

    $env_ignores = array('_' => 1, 'SHLVL' => 1);
    $localenv_ignores = $env_ignores + array('BASHOPTS' => 1, 'BASH_VERSINFO' => 1, 'EUID' => 1, 'PPID' => 1, 'SHELLOPTS' => 1, 'UID' => 1);

    $env = env_to_array($env, $env_ignores);
    $localenv = env_to_array($localenv, $localenv_ignores);
    $localenv = array_diff_key($localenv, $env);

    $result = array();
    $result['code'] = intval($code);
    $result['output'] = $output;
    $result['state'] = array('cwd' => $env['PWD'], 'env' => $env, 'localenv' => $localenv);

    echo json_encode($result);
  }
  else if ($mode == 'pcntl')
  {
    function_exists('pcntl_exec') || my_die('pcntl_exec undefined');

    $args = explode(' ', $command);
    $path = array_shift($args);
    pcntl_exec($path, $args); //, $env); 
  }
  else
  {

    $result = array();

    if ($command)
    {
      exec($command, $output, $code);

      $result['code'] = $code;
      $result['output'] = $output;
    }

    $result['state'] = array('cwd' => getcwd());

    echo json_encode($result);
  }
}

?>