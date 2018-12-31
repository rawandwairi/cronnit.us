<?php

use \RedBeanPHP\R as R;

$account = $this->getAccount();
$post = $this->getPost(@intval($_GET['id']));

if (empty($post)) {
  $this->redirect("dashboard");
}

if ($post->account->id !== $account->id) {
  trigger_error("Attempt to edit someone elses post!");
  $this->redirect("dashboard");
}

if (isset($_POST['delete'])) {
  R::trash($post);
  $this->redirect("dashboard");
}

if (!isset($_POST['submit'])) {
  $post->whendate = $this->formatUserDate(intval($post->when), $post->whenzone ?: "UTC");
  $post->whentime = $this->formatUserTime(intval($post->when), $post->whenzone ?: "UTC");
  $this->vars['post'] = $post;
  $this->vars['error'] = $_SESSION['editerror'];
  unset($_SESSION['editerror']);
  return;
}

if ($_SESSION['editerror'] = $this->checkPost($_POST)) {
  $this->redirect("edit?id={$post->id}");
}

$post->subreddit = strval($_POST['subreddit']);
$post->title = strval($_POST['title']);
$post->body = strval($_POST['body']);
$post->when = $this->convertTime($_POST['whendate'], $_POST['whentime'], $_POST['whenzone']);
$post->whenzone = strval($_POST['whenzone']);
$post->sendreplies = intval($_POST['sendreplies']);
$post->nsfw = intval($_POST['nsfw']);
R::store($post);
$this->redirect("dashboard");
