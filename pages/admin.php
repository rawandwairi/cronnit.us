<?php

$account = $this->getAccount();

if (empty($_SESSION['wasadmin'])) {
  if (empty($account) || intval($account->admin) <= 0) {
    $this->redirect("home");
  }
}

if (isset($_POST['becomeuser'])) {
  $username = @strval($_POST['username']);
  $account = $this->findAccount($username);

  if (empty($account)) {
    $_SESSION['adminerror'] = 'Unable to find user';
    $this->redirect("admin");
  }
  
  $_SESSION['wasadmin'] = true;
  $_SESSION['login'] = $account->name;
  $this->redirect("dashboard");
}

$this->vars['error'] = @$_SESSION['adminerror'];
unset($_SESSION['adminerror']);
