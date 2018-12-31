<?php

use \RedBeanPHP\R as R;
use \League\OAuth2\Client\Token\AccessToken;
use \League\OAuth2\Client\Provider\Exception\IdentityProviderException;

require_once __DIR__."/vendor/autoload.php";
require_once __DIR__."/Cronnit.php";

$cronnit = new Cronnit();
$cronnit->connect();

$pending = R::find('post', '((`url` = "") or (`url` is null)) and (`when` < ?) and (`error` is null) order by `when` asc', [time()]);
$reddit = $cronnit->getReddit();

foreach ($pending as $post) {
  echo "posting {$post->id}\n";

  try {
    $accessToken = $cronnit->getAccessToken($post->account);
  } catch (IdentityProviderException $e) {
    $post->error = "Unable to get an access token - did you revoke access?";
    R::store($post);
    continue;
  }

  $data = [
    'title' => $post->title,
    'sr' => $post->subreddit,
    'api_type' => 'json',
    'sendreplies' => intval($post->sendreplies),
    'nsfw' => intval($post->nsfw),
    'resubmit' => true
  ];

  if (preg_match('#^http[s]?://#i', $post->body)) {
    $data['kind'] = 'link';
    $data['url'] = trim($post->body);
  } else {
    $data['kind'] = 'self';
    $data['text'] = $post->body;
  }

  $response = $cronnit->api($accessToken, 'POST', 'api/submit', [
    'body' => http_build_query($data)
  ]);

  if (isset($response->json->data->url)) {
    $post->url = $response->json->data->url;
    R::store($post);
  } else if ($response->json->errors) {
    if (count($response->json->errors) == 1 && $response->json->errors[0][0] == 'RATELIMIT') {
      var_dump($response->json);
      continue;
    }

    $errors = [];

    foreach ($response->json->errors as $error) {
      if ($error[0] != 'RATELIMIT') {
        $errors[] = $error[1];
      }
    }

    if (empty($errors)) {
      $post->error = "A strange error happened while posting";
      $post->errorResponse = json_encode($response->json);
    } else {
      $post->error = implode(',', $errors);
    }

    R::store($post);
    var_dump($response->json);
  }
}

$pendingComments = R::convertToBeans('comment', R::getAll("
  select `comment`.*
  from `comment`
  join `post` on (`post`.`id` = `comment`.`post_id`)
  where
    (`comment`.`error` is null) and
    (`comment`.`url` is null) and
    ((`post`.`when` + `comment`.`delay`) < ?)
", [time()]));

foreach ($pendingComments as $comment) {
  if (!preg_match('#comments/([^/]+)/#i', $comment->post->url, $match)) {
    $comment->error = "Malformed link";
    R::store($comment);
    trigger_error("Cannot extract thing ID for post #{$comment->post->id}");
    continue;
  }

  $thingID = "t3_{$match[1]}";
  $accessToken = $cronnit->getAccessToken($comment->post->account);

  $response = $cronnit->api($accessToken, 'POST', 'api/comment', [
    'body' => http_build_query([
      'thing_id' => $thingID,
      'text' => $comment->body,
      'return_rtjson' => true
    ])
  ]);

  if (isset($response->permalink)) {
    $comment->url = $response->permalink;

    if ($comment->url[0] === '/') {
      $comment->url = "https://www.reddit.com{$comment->url}";
    }

    R::store($comment);
  } else {
    $comment->error = "Problem while posting comment";
    R::store($comment);
    var_dump(json_encode($response));
  }
}
