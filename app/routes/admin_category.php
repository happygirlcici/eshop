<?php
//$app->get('/admin/product/:op(/:id)', function($op, $id = 0) use ($app) {
//Admin_Panel->Manage Category->Add Category
$app->get('/admin/category/list', function() use ($app) {
    
    $categoryList =  DB::query("SELECT * FROM categories");
    $app->render("admin_category_list.html.twig", array(
        'categoryList' => $categoryList,
        "eshopuser" => $_SESSION['eshopuser']    
    ));
});

$app->get('/admin/category/:op(/:id)', function($op, $id = 0) use ($app) {
     
    if ($op == 'edit') {
        $category = DB::queryFirstRow("SELECT * FROM categories WHERE id=%i", $id);
        if (!$category) {
            echo 'category not found';
            return;
        }
        
        $app->render("admin_category_add.html.twig", array(
            'v' => $category, 'operation' => 'Update',
            "eshopuser" => $_SESSION['eshopuser']
        ));
    } else {
        $app->render("admin_category_add.html.twig",
                array('operation' => 'Add',
                      "eshopuser" => $_SESSION['eshopuser']
        ));
    }
})->conditions(array(
    'op' => '(add|edit)',
    'id' => '[0-9]+'));

$app->post('/admin/category/:op(/:id)', function($op, $id = 0) use ($app, $msg) {
    
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
    
    if (strlen($name) < 2 || strlen($name) > 100) {
      array_push($errorList, "Name must be 2-100 characters long");
    }
    
    $valueList = array('name' => $name);

    if ($errorList) {
        $app->render("admin_category_add.html.twig", ["errorList" => $errorList,
            'v' => $valueList,
            "eshopuser" => $_SESSION['eshopuser']
        ]);
    } else {
       
        if ($op == 'edit') {
            // unlink('') OLD file - requires select
            
            DB::update('categories', array(
            "name" => $name, 
            "parent" => $parent,
            "layer" => $layer,
            "description" => $description,
            "status" => $status,
            "postDate" => $today
            ), "id=%i", $id);
        } else {
            $op == 'add';
            
            DB::insert('categories', array(
            "name" => $name, 
            "parent" => $parent,
            "layer" => $layer,
            "description" => $description,
            "status" => $status,
            "postDate" => $today
        ));
        }
        $categoryList =  DB::query("SELECT * FROM categories");
        $msg->success('Operation successfully');
        $msg->display();
        
        $app->render("admin_category_list.html.twig", array(
            'categoryList' => $categoryList,
            "eshopuser" => $_SESSION['eshopuser']
        ));
    }
})->conditions(array(
    'op' => '(add|edit)',
    'id' => '[0-9]+'));

// AJAX: query categories with name
$app->get('/admin/category/search', function() use ($app) {
    $str = $_POST['searchinput']  . "%"; 
    $categoryList = DB::query("SELECT * FROM categories WHERE name LIKE %s", $str);
    $app->render("admin_category_list.html.twig", array(
        'categoryList' => $categoryList,
        'str' =>$_POST['searchinput'],
        "eshopuser" => $_SESSION['eshopuser']    
    ));
})->VIA('GET', 'POST');


//add category delete by chenchen 2017-05-24 
$app->get('/admin/category/delete/:id', function($id) use ($app) {
    $category = DB::queryFirstRow('SELECT * FROM categories WHERE id=%i', $id);
    $app->render('admin_category_delete.html.twig', array(
        'v' => $category,
        "eshopuser" => $_SESSION['eshopuser']
    ));
})->VIA('GET');

$app->post('/admin/category/delete/:id', function($id) use ($app, $msg) {
    
    DB::delete('categories', 'id=%i', $id);
    $newcategoryList = DB::query("SELECT * FROM categories");
    
    
    /*$app->render('admin_product_delete_success.html.twig');*/
    $msg->success('Delete Category Successfully');
    $msg->display();
    $app->render('admin_category_list.html.twig', array(
        'categoryList' => $newcategoryList,
        "eshopuser" => $_SESSION['eshopuser']    
    ));
    
})->VIA('POST');