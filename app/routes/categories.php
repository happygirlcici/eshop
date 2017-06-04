<?php

$app->get('/category', function() use ($app) {
    
    $categoryList =  DB::query("SELECT * FROM categories");
    $categoryFirst =  DB::queryFirstRow("SELECT * FROM categories");
    $productList =  DB::query("SELECT * FROM products WHERE catId=%i",$categoryFirst['ID']);
    
    $app->render('category.html.twig', array(
        'categoryList' => $categoryList, 
        'productList' => $productList,
        "eshopuser" => $_SESSION['eshopuser']
    ));
});

$app->get('/category/:id', function($id) use ($app) {
    $categoryList =  DB::query("SELECT * FROM categories");
    $productList =  DB::query("SELECT * FROM products WHERE catId=%i",$id);
    
        
        
    $app->render('category.html.twig', array(
        'categoryList' => $categoryList, 
        'productList' => $productList,
        "eshopuser" => $_SESSION['eshopuser']
    ));
});
