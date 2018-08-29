<?php

use \RedBeanPHP\R as R;

$account = $this->getAccount();

$this->vars['account'] = $account;
$this->vars['posts'] = $account->with('order by `when` desc')->ownPostList;
