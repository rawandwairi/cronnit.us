<?php

use \RedBeanPHP\R as R;

$account = $this->getAccount();

if (!isset($_POST['submit'])) {
  $this->vars['error'] = $_SESSION['submiterror'];
  unset($_SESSION['submiterror']);
  return;
}

if ($_SESSION['submiterror'] = $this->checkPost($_POST)) {
  $this->redirect("submit");
}

$post = R::dispense('post');
$post->account = $account;
$post->subreddit = strval($_POST['subreddit']);
$post->title = strval($_POST['title']);
$post->body = strval($_POST['body']);
$post->when = $this->convertTime($_POST['whendate'], $_POST['whentime'], $_POST['whenzone']);
$post->whenzone = strval($_POST['whenzone']);
$post->sendreplies = intval($_POST['sendreplies']);
$post->nsfw = intval($_POST['nsfw']);
$post->url = null;
$post->error = null;
R::store($post);

$this->redirect("dashboard");
