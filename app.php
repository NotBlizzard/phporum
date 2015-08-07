<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
session_start();

$app = new Silex\Application();
try {
  $db = new PDO("pgsql:dbname=phporum;host=localhost;", "postgres", "anthony");
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
  echo $e->getMessage();
}
$app['debug'] = true;
$app->register(new Silex\Provider\TwigServiceProvider(), [
  'twig.path' => __DIR__ . '/views',
]);

$app->get('/', function() use ($app) {
  return $app['twig']->render('home.html', ['username' => $_SESSION['username']] );
});

$app->get('/logout', function() use ($app) {
  unset($_SESSION['username']);
  return $app->redirect('/');
});

$app->get('/new', function() use ($app) {
  if (isset($_SESSION['username'])) {
    return $app['twig']->render('new.html');
  } else {
    return $app->redirect('/');
  }
});

$app->get('/register', function() use ($app) {
  if (isset($_SESSION['username'])) {
    return $app->redirect('/');
  }
  return $app['twig']->render('register.html', ['error' => '']);
});

$app->post('/register', function(Request $request) use ($app, $db) {

  $username = $request->get('username');
  $email = $request->get('email');
  $pass = $request->get('password');
  if (trim($email) === '' || trim($username) === '' || trim($pass) === '') {
    return $app['twig']->render('register.html', ['error' => 'username, email, and password cannot be empty.']);
  }
  if (strlen($pass) < 5) {
    return $app['twig']->render('register.html', ['error' => 'password must be more than 5 characters.']);
  }
  $password = password_hash($pass, PASSWORD_DEFAULT);
  // We check if the username is taken.
  $st = $db->prepare("SELECT * FROM users WHERE username = :username");
  $st->execute([":username" => $username]);
  if ($st) {
    return $app['twig']->render('register.html', ['error' => 'username already exists.']);
  }
  // We check if email is taken.
  $st = $db->prepare("SELECT * FROM users WHERE email = :email");
  $st->execute([":username" => $email]);
  if ($st) {
    return $app['twig']->render('register.html', ['error' => 'email already exists.']);
  }
  $st = $db->prepare("INSERT INTO users (email, username, password_hash) VALUES(:email, :username, :password_hash)");
  $st->execute([":email" => $email, ':username' => $username, ':password_hash' => $password]);
  $_SESSION['username'] = $username;
  return $app->redirect("/");
});

$app->post('/new', function(Request $request) use ($app, $db) {
  if (isset($_SESSION['username'])) {
    $content = $request->get('content');
    $title = $request->get('title');
    $_st = $db->prepare("SELECT id FROM users WHERE username=:username");
    $_st->execute([":username" => $_SESSION['username']]);
    $user_id = $_st->fetch()[0];
    $st = $db->prepare("INSERT INTO posts (title, content, user_id, created_at, modified_at) VALUES(:title, :content, :user_id, :created_at, :modified_at) RETURNING id");

    $st->execute([':title' => $title, ':created_at' => date('Y-m-d H:i:s'), ':user_id' => $user_id, ':modified_at' => date('Y-m-d H:i:s'), ':content' => $content]);
    $id = $st->fetch()[0];
    return $app->redirect("/".$id);
  }else{
    return $app->redirect('/');
  }
});

$app->get("/{id}", function($id) use ($app, $db) {
  $st = $db->prepare("SELECT * FROM posts WHERE id = :id");
  $st->execute([":id" => intval($id)]);
  $data = $st->fetch();
  $_st = $db->prepare("SELECT username, email FROM users WHERE id=:id");
  $_st->execute([":id" => $data['user_id']]);
  $username = $_st->fetch()['username'];
  $email = $_st->fetch();
  $st_ = $db->prepare("SELECT * FROM comments WHERE post_id = :post_id");
  $st_->execute([":post_id" => intval($id)]);
  $comments = $st_->fetchAll();
  return $app['twig']->render('show.html', [
    'data' => $data,
    'username' => $username,
    'email' => md5($email),
    'comments' => $comments
    ]);
});

$app->post("/newcomment/{id}", function($id, Request $request) use ($app, $db) {
  if (!isset($_SESSION['username'])) {
    return $app->redirect('/');
  }
  $st = $db->prepare("SELECT id FROM posts WHERE id = :id");
  $st->execute([":id" => intval($id)]);
  $id = $st->fetch()[0];
  $_st = $db->prepare("SELECT id,email FROM users WHERE username=:username");
  $_st->execute([":username" => $_SESSION['username']]);
  $user_id = $_st->fetch()[0];
  $email = $st->fetch()[1];
  $st = $db->prepare("INSERT INTO comments (content, user_id, post_id, user_email) VALUES (:content, :user_id, :post_id, :user_email)");
  $st->execute([":content" => $request->get('content'), ":post_id" => $id, ":user_id" => $user_id, ":user_email" => $email ]);
  echo $email;
});

$app->run();
