<?php

// ADMIN - CRUD for orders table
$app->get('/admin/order/:op(/:id)', function($op, $id = 0) use ($app) {
    
    // FOR PROJECTS WITH MANY ACCESS LEVELS
//    if (($_SESSION['eshopuser']) || ($_SESSION['role'] != 'admin')) {
//       $app->render('forbidden.html.twig');
//       return;
//    } 
    
    if ($op == 'edit') {
        $order = DB::queryFirstRow("SELECT * FROM orders WHERE id=%i", $id);
        if (!$order) {
            echo 'Order not found';
            return;
        }
        $app->render("admin_order_add.html.twig", array(
            'v' => $order, 'operation' => 'Update',
            "eshopuser" => $_SESSION['eshopuser']
        ));
    } else {
        $app->render("admin_order_add.html.twig", array(
            'operation' => 'Add',
            "eshopuser" => $_SESSION['eshopuser']
        ));
    }
})->conditions(array(
    'op' => '(add|edit)',
    'id' => '[0-9]+'));

$app->post('/admin/order/:op(/:id)', function($op, $id = 0) use ($app, $msg) {
    
    if (!$_SESSION['eshopuser']) {
        $app->render('forbidden.html.twig');
        return;
    }
    $name = $app->request()->post('name');
    $userID = $app->request()->post('userID');
    $address = $app->request()->post('address');
    $postalCode = $app->request()->post('postalCode');
    $email = $app->request()->post('email');
    $phoneNumber = $app->request()->post('phoneNumber');
    $totalBeforeTax = $app->request()->post('totalBeforeTax');
    $shippingBeforeTax = $app->request()->post('shippingBeforeTax');
    $taxes = $app->request()->post('taxes');
    $totalWithShippingAndTaxes = $app->request()->post('totalWithShippingAndTaxes');
    $dateTimePlaced = $app->request()->post('dateTimePlaced');
    $dateTimeShipped = $app->request()->post('dateTimeShipped');
    $status = $app->request()->post('status');
    $today = date("Y-m-d");
    $postDate = $today;

    $errorList = array();
    $valueList = array('name' => $name);
    if (strlen($name) < 2 || strlen($name) > 200) {
        array_push($errorList, "name name must be 2-100 characters long");
    } /* else {
      $userList = DB::queryFirstRow("SELECT * FROM orders WHERE name=%s", $name);
      if ($userList) {
      array_push($errorList, "orderID name already in use");
      }
      } */

    if ($errorList) {
        $app->render("admin_order_add.html.twig", ["errorList" => $errorList,
            'v' => $valueList,
            "eshopuser" => $_SESSION['eshopuser']
        ]);
    } else {
        if ($op == 'edit') {
            // unlink('') OLD file - requires select
            $oldImagePath = DB::queryFirstField(
                            'SELECT imagePath FROM orders WHERE id=%i', $id);
            if (($oldImagePath) && file_exists($oldImagePath)) {
                unlink($oldImagePath);
            }
            DB::update('orders', array(
                "name" => $name,
                "userID" => $userID,
                "address" => $address,
                "postalCode" => $postalCode,
                "email" => $email,
                "phoneNumber" => $phoneNumber,
                "totalBeforeTax" => $totalBeforeTax,
                "shippingBeforeTax" => $shippingBeforeTax,
                "taxes" => $taxes,
                "totalWithShippingAndTaxes" => $totalWithShippingAndTaxes,
                "dateTimePlaced" => $dateTimePlaced,
                "dateTimeShipped" => $dateTimeShipped,
                "status" => $status
                    ), "id=%i", $id);
               $msg->success('Edit successfully');
        } else {
            $op == 'add';
            DB::insert('orders', array(
                "name" => $name,
                "userID" => $userID,
                "address" => $address,
                "postalCode" => $postalCode,
                "email" => $email,
                "phoneNumber" => $phoneNumber,
                "totalBeforeTax" => $totalBeforeTax,
                "shippingBeforeTax" => $shippingBeforeTax,
                "taxes" => $taxes,
                "totalWithShippingAndTaxes" => $totalWithShippingAndTaxes,
                "dateTimePlaced" => $dateTimePlaced,
                "dateTimeShipped" => $dateTimeShipped,
                "status" => $status
            ));
            $msg->success('Add successfully');
            $id = DB::insertId();
        }
        
        $msg->display();
        $orderList = DB::query("SELECT * FROM orders");
        $app->render("admin_order_list.html.twig", array(
            'orderList' => $orderList,
            "eshopuser" => $_SESSION['eshopuser']    
        ));
    }
})->conditions(array(
    'op' => '(add|edit)',
    'id' => '[0-9]+'));


$app->get('/admin/order/list', function() use ($app) {
    $orderList = DB::query("SELECT * FROM orders");
    $app->render("admin_order_list.html.twig", array(
        'orderList' => $orderList,
        "eshopuser" => $_SESSION['eshopuser']    
    ));
});

// AJAX: query orders with name
$app->get('/admin/order/search', function() use ($app) {
    $str = $_POST['searchinput']  . "%"; 
    $orderList = DB::query("SELECT * FROM orders WHERE name LIKE %s", $str);
    $app->render("admin_order_list.html.twig", array(
        'orderList' => $orderList,
        'str' =>$_POST['searchinput'],
        "eshopuser" => $_SESSION['eshopuser']    
    ));
})->VIA('GET', 'POST');


//add category delete by chenchen 2017-05-24 
$app->get('/admin/order/delete/:id', function($id) use ($app) {
    
    $order = DB::queryFirstRow('SELECT * FROM orders WHERE id=%i', $id);
    $app->render('admin_order_delete.html.twig', array(
        'v' => $order,
        "eshopuser" => $_SESSION['eshopuser']
    ));
})->VIA('GET');

$app->post('/admin/order/delete/:id', function($id) use ($app, $msg) {
    
    DB::delete('orders', 'id=%i', $id);
    $neworderList = DB::query("SELECT * FROM orders");
    
    $msg->success('Delete successfully');
    $msg->display();
        
    $app->render('admin_order_list.html.twig', array(
        'orderList' => $neworderList,
        "eshopuser" => $_SESSION['eshopuser']    
    ));
    
})->VIA('POST');
