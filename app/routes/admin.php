<?php

$view = $app->view();
$view->parserOptions = array(
    'debug' => true,
    'cache' => dirname(__FILE__) . '/../cache'
);

$app->get('/admin_panel', function() use ($app) {
    $app->render("admin_panel.html.twig", array(
        "eshopuser" => $_SESSION['eshopuser']
    ));
});

/*
//Admin_Panel->Manage Category->Add Category
$app->get('/admin_category_add', function() use ($app) {
    $app->render("admin_category_add.html.twig", array(
        "eshopuser" => $_SESSION['eshopuser']
    ));
});

$app->post('/admin_category_add', function() use ($app) {
    if (!$_SESSION['eshopuser']) {
        $app->render('forbidden.html.twig');
        return;
    }
    $name = $app->request()->post('name');
    $parent = $app->request()->post('parent');
    $layer = $app->request()->post('layer');
    $description = $app->request()->post('description');
    $status = $app->request()->post('status');
    $today = date("Y-m-d");
    $postDate = $today;
    
    $errorList = array();
    $valueList = array('name' => $name);
    if (strlen($name) < 2 || strlen($name) > 200) {
        array_push($errorList, "Username name must be 2-100 characters long");
    } else {
        $userList = DB::queryFirstRow("SELECT * FROM categories WHERE name=%s", $name);
        if ($userList) {
            array_push($errorList, "categories name already in use");
        }
    }

    if ($errorList) {
        $app->render("admin_category_add.html.twig", ["errorList" => $errorList,
            'v' => $valueList
        ]);
    } else {
        print_r($name . $layer);
        DB::insert('categories', array(
            "name" => $name, 
            "parent" => $parent,
            "layer" => $layer,
            "description" => $description,
            "status" => $status,
            "postDate" => $today
        ));
        $id = DB::insertId();
        //$log->debug(sprintf("User %s created", $id));
        $app->render("admin_category_add_success.html.twig", array(
            "name" => $name,
            "layer" => $layer
        ));
    }
});
 


//list message

$app->get('/admin_message', function() use ($app) {
    echo "admin admin admin";
    $messageList = DB::query("SELECT * FROM messages");
    //print_r($messageList);
    $app->render('admin_message.html.twig', array(
        'messageList' => $messageList,
        "eshopuser" => $_SESSION['eshopuser']
    ));
});
 */
$app->get('/admin_message', function() use ($app) {
    $mList = DB::query("SELECT * FROM messages");
    $app->render("admin_message.html.twig", array(
        'mList' => $mList,
        "eshopuser" => $_SESSION['eshopuser']
    ));
});

$app->get('/admin/message/replay/:id', function($id) use ($app) {
    $message = DB::queryFirstRow("SELECT * FROM messages WHERE id=%i", $id);
    $app->render("admin_message_reply.html.twig", array(
        'v' => $message, 
        "eshopuser" => $_SESSION['eshopuser']
    ));
});

$app->post('/admin/message/replay/:id', function($id) use ($app) {
    $name = $app->request()->post('name');
    $email = $app->request()->post('email');
    $phone = $app->request()->post('phone');
    $subject = $app->request()->post('subject');
    $message = $app->request()->post('message');
    $response = $app->request()->post('response');
    $today = date("Y-m-d");
    $creatDate = $today;
    $errorList = array();
    $valueList = array('name' => $name);
    if (strlen($response) < 2 || strlen($response) > 3000) {
        array_push($errorList, "Response must be 2-3000 characters long");
    }
    if ($errorList) {
        $app->render("admin_message_reply.html.twig", ["errorList" => $errorList,
            'v' => $valueList
        ]);
    } else {
        DB::update('messages', array(
            "name" => $name, 
            "email" => $email,
            "phone" => $phone,
            "subject" => $subject,
            "message" => $message,
            "creatDate" =>$creatDate,
            "response" => $response
            ), "id=%i", $id);
        
        $newmessage = DB::queryFirstRow("SELECT * FROM messages WHERE id=%i", $id);
        
        $app->render("admin_message.html.twig", array(
            "name" => $name,
            "email" => $email,
            'v'=> $newmessage
        ));
    }
    
});


$app->get('/admin_news_add', function() use ($app) {
    $mList = DB::query("SELECT * FROM news");
    $app->render("admin_news_add.html.twig", array(
        'mList' => $mList,
        "eshopuser" => $_SESSION['eshopuser']
    ));
});

$app->post('/admin_news_add', function() use ($app) {
    if (!$_SESSION['eshopuser']) {
        $app->render('forbidden.html.twig');
        return;
    }
    $subject = $app->request()->post('subject');
    $content = $app->request()->post('content');
    $status = "published";
    $today = date("Y-m-d");
    $postDate = $today;
    
    $errorList = array();
    $valueList = array('subject' => $subject);
    if (strlen($content) < 2 || strlen($content) > 200) {
        array_push($errorList, "Username name must be 2-100 characters long");
    }
    if ($errorList) {
        $app->render("admin_news_add.html.twig", ["errorList" => $errorList,
            'v' => $valueList
        ]);
    } else {
        DB::insert('news', array(
            "subject" => $subject,
            "content" => $content,
            "status" => $status,
            "postDate" => $today
        ));
        $id = DB::insertId();
        //$log->debug(sprintf("User %s created", $id));
        $app->render("index.html.twig", array(
            "subject" => $subject,
            "content" => $content
        ));
    }
});

$app->get('/admin_report', function() use ($app) {
    $mList = DB::query("SELECT * FROM orders");
    $app->render("admin_report.html.twig", array(
        'mList' => $mList,
        "eshopuser" => $_SESSION['eshopuser']
    ));
});

$app->get('/admin_report_monthly', function() use ($app) {
    $mList = DB::query("SELECT * FROM orders where date_format(dateTimePlaced,'%Y-%m')=date_format(now(),'%Y-%m') ");
    $app->render("admin_report.html.twig", array(
        'mList' => $mList,
        "eshopuser" => $_SESSION['eshopuser']
    ));
});


$app->get('/admin_report_lastmonth', function() use ($app) {
    $mList = DB::query("SELECT * FROM orders where date_format(dateTimePlaced,'%Y-%m')=date_format(DATE_SUB(curdate(), INTERVAL 1 MONTH),'%Y-%m')");
    $app->render("admin_report_lastmonth.html.twig", array(
        'mList' => $mList,
        "eshopuser" => $_SESSION['eshopuser']
    ));
});

$app->get('/admin_report_region', function() use ($app) {
    $mList = DB::query("SELECT * FROM orders where date_format(dateTimePlaced,'%Y-%m')=date_format(DATE_SUB(curdate(), INTERVAL 1 MONTH),'%Y-%m')");
    $app->render("admin_report_lastmonth.html.twig", array(
        'mList' => $mList,
        "eshopuser" => $_SESSION['eshopuser']
    ));
});
