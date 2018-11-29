<?php

use \RedBeanPHP\R as R;
use \League\OAuth2\Client\Token\AccessToken;

require_once __DIR__."/vendor/autoload.php";
require_once __DIR__."/Cronnit.php";

$cronnit = new Cronnit();
$cronnit->connect();

$pending = R::find('post', '((`url` = "") or (`url` is null)) and (`when` < ?) and (`error` is null)', [time()]);
$reddit = $cronnit->getReddit();

foreach ($pending as $post) {
  echo "posting {$post->id}\n";
  $token = json_decode($post->account->token, true);
  $accessToken = new AccessToken($token);

  if ($accessToken->hasExpired()) {
    $accessToken = $reddit->getAccessToken('refresh_token', ['refresh_token' => $accessToken->getRefreshToken()]);
    $token['access_token'] = $accessToken->getToken();
    $token['expires'] = $accessToken->getExpires();
    $post->account->token = json_encode($token);
    R::store($post->account);
  }

  $data = [
    'title' => $post->title,
    'sr' => $post->subreddit,
    'api_type' => 'json',
    'sendreplies' => true,
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
