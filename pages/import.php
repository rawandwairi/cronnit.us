<?php

use RedBeanPHP\R as R;

$account = $this->getAccount();

if (isset($_POST['submit'])) {
  if ($account->banned) {
    $_SESSION['importerror'] = $account->banned;
    $this->redirect('import');
  }

  if (!isset($_FILES["file"]["tmp_name"])) {
    $_SESSION['importerror'] = "No file uploaded";
    $this->redirect('import');
  }

  $fp = fopen($_FILES["file"]["tmp_name"], 'r');

  if (empty($fp)) {
    $_SESSION['importerror'] = "Cannot read file";
    $this->redirect('import');
  }

  $header = fread($fp, 3);

  if ($header != "\xef\xbb\xbf") {
    fseek($fp, 0);
  }

  $header = fgetcsv($fp);

  if (empty($header)) {
    $_SESSION['importerror'] = "No rows in CSV";
    $this->redirect('import');
  }

  $map = [];
  $rowNumber = 0;
  $checkOnly = (int)@$_POST['checkOnly'];

  foreach ($header as $index => $column) {
    switch ($column) {
    case "title":
    case "body":
    case "subreddit":
    case "date":
    case "time":
    case "timezone":
    case "sendreplies":
    case "nsfw":
      break;
    default:
      $_SESSION['importerror'] = "Unknown column '$column'";
      $this->redirect('import');
      break;
    }

    $map[$column] = $index;
  }

  foreach (['title', 'body', 'subreddit', 'date', 'time', 'timezone'] as $required) {
    if (!isset($map[$required])) {
      $_SESSION['importerror'] = "You must provide a '$required' column";
      $this->redirect('import');
    }
  }

  while (($row = fgetcsv($fp)) !== false) {
    $rowNumber++;

    if (empty($row)) {
      continue;
    }

    $post = [];

    foreach ($map as $column => $index) {
      $post[$column] = @trim($row[$index]);

      if (strlen($post[$column]) <= 0) {
        $_SESSION['importerror'] = "No $column for row #$rowNumber";
        $this->redirect('import');
      }
    }

    $post = (object) $post;

    if (!preg_match('#^\\d{4}-\\d{1,2}-\\d{1,2}$#', $post->date)) {
      $_SESSION['importerror'] = "Date should be YYYY-MM-DD for row #$rowNumber not ({$post->date})";
      $this->redirect('import');
    } else if (!preg_match('#^\\d{1,2}\\:\\d{1,2}$#', $post->time)) {
      $_SESSION['importerror'] = "Time should be hh::mm for row #$rowNumber not ({$post->time})";
      $this->redirect('import');
    }

    try {
      $post->when = $this->convertTime($post->date, $post->time, $post->timezone);
    } catch (Exception $e) {
      $_SESSION['importerror'] = "Invalid timezone for row #$rowNumber";
    }

    if ($post->when <= 0) {
      $_SESSION['importerror'] = "Cannot determine date/time for row #$rowNumber";
      $this->redirect('import');
    }

    $post->sendreplies = isset($post->sendreplies) ? intval($post->sendreplies) : 1;
    $post->nsfw = isset($post->nsfw) ? intval($post->nsfw) : 0;

    $limit = @$account->dailyLimit ?? 5;

    if ($this->countDailyPosts($account, $post->when) >= $limit) {
      $_SESSION['importerror'] = "Row #$rowNumber exceeds daily post limit of $limit";
      $this->redirect('import');
    }

    if ($checkOnly) {
      continue;
    }

    $bean = R::findOrCreate('post', [
      'account' => $account,
      'title' => $post->title,
      'subreddit' => $post->subreddit
    ]);

    if (isset($bean->url) || isset($bean->error)) {
      continue;
    }

    $bean->body = $post->body;
    $bean->when = $post->when;
    $bean->whenzone = $post->timezone;
    $bean->sendreplies = intval($post->sendreplies);
    $bean->nsfw = intval($post->nsfw);
    $bean->url = null;
    $bean->error = null;
    $bean->bulk = true;
    R::store($bean);
  }

  fclose($fp);
  $this->redirect('dashboard');
}

if (isset($_SESSION['importerror'])) {
  $this->vars['error'] = $_SESSION['importerror'];
  unset($_SESSION['importerror']);
}
