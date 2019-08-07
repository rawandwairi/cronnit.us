<?php

use \RedBeanPHP\R as R;
use \League\OAuth2\Client\Token\AccessToken;
use \League\OAuth2\Client\Provider\Exception\IdentityProviderException;

require_once __DIR__."/vendor/autoload.php";
require_once __DIR__."/Cronnit.php";

$cronnit = new Cronnit();
$cronnit->connect();

$pending = R::find('post', '(`url` is null) and (`error` is null) and (`when` < ?) order by `when` asc', [time()]);
$reddit = $cronnit->getReddit();
$account_sums = [];

foreach ($pending as $post) {
  echo "posting {$post->id} for {$post->account->name}\n";

  if ($post->account->banned) {
    $post->error = $post->account->banned;
    R::store($post);
    continue;
  }

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

  try {
    $response = $cronnit->api($accessToken, 'POST', 'api/submit', [
      'body' => http_build_query($data)
    ]);
  } catch (\GuzzleHttp\Exception\ClientException $e) {
    $post->error = "HTTP error: {$e->getMessage()}";
    R::store($post);
    continue;
  }

  if (isset($response->json->data->url)) {
    $post->when_posted = time();
    $post->url = $response->json->data->url;
    R::store($post);
  } else if ($response->json->errors) {
    if (isset($response->json->ratelimit)) {
      $post->ratelimit_count += 1;
      $post->ratelimit_sum += (int)ceil($response->json->ratelimit) + @$account_sums[$post->account->id];
      $post->when_original = $post->when_original ?: $post->when;
      $post->when = time() + (int)ceil($response->json->ratelimit);
      R::store($post);
      @$account_sums[$post->account->id] += (int)$response->json->ratelimit;
    }
    
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
