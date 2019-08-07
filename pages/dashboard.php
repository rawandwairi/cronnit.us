<?php

use \RedBeanPHP\R as R;

$account = $this->getAccount();
$this->vars['account'] = $account;
$posts = $account->with('order by `when` desc')->ownPostList;

switch (@$_GET['view']) {
case 'calendar':
  $this->vars['view'] = 'posts-calendar.html';
  $this->vars['time'] = @$_GET['time'];
  $indexedPosts = [];

  foreach ($posts as $post) {
    $year = date('Y', $post->when_original);
    $month = date('n', $post->when_original);
    $day = date('j', $post->when_original);
    $indexedPosts[$year][$month][$day][] = $post;
  }

  $this->vars['posts'] = $indexedPosts;

  break;
case 'list':
default:
  $this->vars['view'] = 'posts-list.html';
  $this->vars['posts'] = $posts;
  break;
}
