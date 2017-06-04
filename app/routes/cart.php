<?php

$app->get('/cart', function() use ($app) {
    $cartitemList = DB::query(
                    "SELECT cartitems.ID as ID, productID, quantity,"
                    . " name, desc1, imageName1, price "
                    . " FROM cartitems, products "
                    . " WHERE cartitems.productID = products.ID AND sessionID=%s", session_id());
    $app->render('cart.html.twig', array(
        'cartitemList' => $cartitemList,
        "eshopuser" => $_SESSION['eshopuser']
    ));
});

$app->post('/cart', function() use ($app) {
    $productID = $app->request()->post('productID');
    $quantity = $app->request()->post('quantity');
    // FIXME: make sure the item is not in the cart yet
    $item = DB::queryFirstRow("SELECT * FROM cartitems WHERE productID=%d AND sessionID=%s", $productID, session_id());
    if ($item) {
        DB::update('cartitems', array(
            'sessionID' => session_id(),
            'productID' => $productID,
            'quantity' => $item['quantity'] + $quantity
                ), "productID=%d AND sessionID=%s", $productID, session_id());
    } else {
        DB::insert('cartitems', array(
            'sessionID' => session_id(),
            'productID' => $productID,
            'quantity' => $quantity
        ));
    }
    // show current contents of the cart
    $cartitemList = DB::query(
                    "SELECT cartitems.ID as ID, productID, quantity,"
                    . " name, desc1, imageName1, price "
                    . " FROM cartitems, products "
                    . " WHERE cartitems.productID = products.ID AND sessionID=%s", session_id());
    $app->render('cart.html.twig', array(
        'cartitemList' => $cartitemList,
        "eshopuser" => $_SESSION['eshopuser']
    ));
});

// AJAX call, not used directy by eshopuser
$app->get('/cart/update/:cartitemID/:quantity', function($cartitemID, $quantity) use ($app) {
    if ($quantity == 0) {
        DB::delete('cartitems', 'cartitems.ID=%d AND cartitems.sessionID=%s', $cartitemID, session_id());
    } else {
        DB::update('cartitems', array('quantity' => $quantity), 'cartitems.ID=%d AND cartitems.sessionID=%s', $cartitemID, session_id());
    }
    echo json_encode(DB::affectedRows() == 1);
});


