<?php

use \RedBeanPHP\R as R;

if (!$this->isLoggedIn()) {
  $this->redirect("home");
}

$account = $this->getAccount();
$account->hideThanks = 1;
R::store($account);

$this->redirect("dashboard");
