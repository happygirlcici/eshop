<?php
session_cache_limiter(false);
session_start();

// enable on-demand class loader
require_once 'app/vendor/autoload.php';
//Email server
require_once 'app/vendor/phpmailer/phpmailer/PHPMailerAutoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// create a log channel
$log = new Logger('main');
$log->pushHandler(new StreamHandler('app/logs/everything.log', Logger::DEBUG));
$log->pushHandler(new StreamHandler('app/logs/errors.log', Logger::ERROR));

// create a flash message
$msg = new \Plasticbrain\FlashMessages\FlashMessages();

//ini_set('session.save_path',realpath(dirname($_SERVER['DOCUMENT_ROOT']) . '/../session'));

if ($_SERVER['SERVER_NAME'] === 'localhost') {
    DB::$dbName = 'eshop';
    DB::$user = 'eshop';
    DB::$password = 'FvUVdCWTv8GuWshh';
    DB::$host = '127.0.0.1';   // sometimes needed on Mac OSX
    DB::$port = 3333;
} else { // hosted on external server
    DB::$dbName = 'cp4776_eshop';
    DB::$user = 'cp4776_eshop';
    DB::$password = 'FvUVdCWTv8GuWshh';
    DB::$port = 3306;
}
DB::$error_handler = 'sql_error_handler';
DB::$nonsql_error_handler = 'nonsql_error_handler';

function nonsql_error_handler($params) {
    global $app, $log;
    $log->error("Database error: " . $params['error']);
    http_response_code(500);
    $app->render('error_internal.html.twig');
    die;
}

function sql_error_handler($params) {
    global $app, $log;
    $log->error("SQL error: " . $params['error']);
    $log->error(" in query: " . $params['query']);
    http_response_code(500);
    $app->render('error_internal.html.twig');
    die; // don't want to keep going if a query broke
}

// instantiate Slim - router in front controller (this file)
// Slim creation and setup
$app = new \Slim\Slim(array(
    'view' => new \Slim\Views\Twig()
        ));

$view = $app->view();
$view->parserOptions = array(
    'debug' => true,
    'cache' => dirname(__FILE__) . '/app/cache'
);
$view->setTemplatesDirectory(dirname(__FILE__) . '/app/templates');

if (!isset($_SESSION['eshopuser'])) {
    $_SESSION['eshopuser'] = array();
}

$twig = $app->view()->getEnvironment();
$twig->addGlobal('sessionUser', $_SESSION['eshopuser']);

$app->get('/', function() use ($app) {
    $app->render('index.html.twig');
});

require 'app/routes/users.php';
require 'app/routes/cart.php';
require 'app/routes/orders.php';
require 'app/routes/admin.php';
require 'app/routes/categories.php';
require 'app/routes/products.php';
require 'app/routes/admin_product.php';
require 'app/routes/admin_user.php';
require 'app/routes/admin_order.php';
require 'app/routes/admin_category.php';

$app->get('/scheduled/daily', function() use ($app, $log) {
    DB::$error_handler = FALSE;
    DB::$throw_exception_on_error = TRUE;
            // PLACE THE ORDER
    $log->debug("Daily scheduler run started");
    // 1. clean up old password reset requests
    try {
        DB::delete('passresets', "expiryDateTime < NOW()");    
        $log->debug("Password resets clean up, removed " . DB::affectedRows());
    } catch (MeekroDBException $e) {
        sql_error_handler(array(
                    'error' => $e->getMessage(),
                    'query' => $e->getQuery()
                ));
    }
    // 2. clean up old cart items (normally we never do!)
    try {
        DB::delete('cartitems', "createdTS < DATE(DATE_ADD(NOW(), INTERVAL -1 DAY))");
    } catch (MeekroDBException $e) {
        sql_error_handler(array(
                    'error' => $e->getMessage(),
                    'query' => $e->getQuery()
                ));
    }
    $log->debug("Cart items clean up, removed " . DB::affectedRows());
    $log->debug("Daily scheduler run completed");
    echo "Completed";
});

//display news not so pretty so hidden in index.html.twig 
$app->get('/index', function() use ($app) {
    
    $newsList = DB::query('SELECT * FROM news order by postDate');
    if ($newsList) {
        $app->render("index.html.twig", array(
            "eshopuser" => $_SESSION['eshopuser'],
            'newsList' => $newsList
        ));
    } else {
    $app->render('index.html.twig',array(
        "eshopuser" => $_SESSION['eshopuser']
    ));}
});

$app->get('/eshop', function() use ($app) {
    echo ($app->flash['logout']);
    $app->render('eshop.html.twig',array(
        "eshopuser" => $_SESSION['eshopuser']
    )); 
});

$app->get('/contact', function() use ($app) {
    $app->render('contact.html.twig',array(
        "eshopuser" => $_SESSION['eshopuser']
    ));          
});

$app->get('/about', function() use ($app) {
    $app->render('about.html.twig',array(
        "eshopuser" => $_SESSION['eshopuser']
    ));          
});

$app->get('/services', function() use ($app) {
    $app->render('services.html.twig',array(
        "eshopuser" => $_SESSION['eshopuser']
    ));          
});

$app->post('/contact', function() use ($app) {
    if (!$_SESSION['eshopuser']) {
        $app->render('forbidden.html.twig');
        return;
    }
    $name = $app->request()->post('name');
    $email = $app->request()->post('email');
    $phone = $app->request()->post('phone');
    $subject = $app->request()->post('subject');
    $message = $app->request()->post('message');
    $today = date("Y-m-d");
    $postDate = $today;
    $errorList = array();
    $valueList = array('name' => $name);
    if (strlen($name) < 2 || strlen($name) > 200) {
        array_push($errorList, "Username name must be 2-100 characters long");
    }
    if ($errorList) {
        $app->render("contact.html.twig", ["errorList" => $errorList,
            'v' => $valueList
        ]);
    } else {
        DB::insert('messages', array(
            "name" => $name, 
            "email" => $email,
            "phone" => $phone,
            "subject" => $subject,
            "message" => $message
        ));
        $app->render("submit_success.html.twig", array(
            "name" => $name,
            "email" => $email
        ));
    }
});


$app->run();
