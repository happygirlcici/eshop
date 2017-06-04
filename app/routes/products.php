<?php

$app->map('/product/:id', function($id) use ($app) {
    $product =  DB::queryFirstRow("SELECT * FROM products WHERE ID=%i",$id);
    $app->render('product.html.twig', array(
        'p' => $product, 
        "eshopuser" => $_SESSION['eshopuser']
    ));
}) -> via ('GET','POST');

