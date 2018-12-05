<?php

use \RedBeanPHP\R as R;

$post_id = @intval($_GET['post']);
$post = $this->getPost($post_id);

if (empty($post)) {
  $this->redirect("dashboard");
}

if (isset($_POST['submit'])) {
  $body = @trim($_POST['body']);
  $delay = 0;
  $delay += intval($_POST['delayHours']) * 60 * 60;
  $delay += intval($_POST['delayMinutes']) * 60;
  $delay += intval($_POST['delaySeconds']);

  $comment = R::dispense('comment');
  $comment->post = $post;
  $comment->body = $body;
  $comment->delay = $delay;
  $comment->url = null;
  $comment->error = null;
  R::store($comment);
  $this->redirect("edit?id={$post->id}");
}

$this->vars['post'] = $post;
