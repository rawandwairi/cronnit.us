<?php

use \RedBeanPHP\R as R;

if ($this->isLoggedIn()) {
  $this->redirect("dashboard");
}
