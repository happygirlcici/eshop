<?php

// order handling
$app->map('/order', function () use ($app, $msg) {
    $totalBeforeTax = DB::queryFirstField(
                    "SELECT SUM(products.price * cartitems.quantity) "
                    . " FROM cartitems, products "
                    . " WHERE cartitems.sessionID=%s AND cartitems.productID=products.ID", session_id());
    // TODO: properly compute taxes, shipping, ...
    $shippingBeforeTax = 15.00;
    $taxes = ($totalBeforeTax + $shippingBeforeTax) * 0.15;
    $totalWithShippingAndTaxes = $totalBeforeTax + $shippingBeforeTax + $taxes;

    if ($app->request->isGet()) {
        $app->render('order.html.twig', array(
            'totalBeforeTax' => number_format($totalBeforeTax, 2),
            'shippingBeforeTax' => number_format($shippingBeforeTax, 2),
            'taxes' => number_format($taxes, 2),
            'totalWithShippingAndTaxes' => number_format($totalWithShippingAndTaxes, 2),
            "eshopuser" => $_SESSION['eshopuser']
        ));
    } else {
        $name = $app->request->post('name');
        $email = $app->request->post('email');
        $address = $app->request->post('address');
        $postalCode = $app->request->post('postalCode');
        $phoneNumber = $app->request->post('phoneNumber');
        $valueList = array(
            'name' => $name,
            'email' => $email,
            'address' => $address,
            'postalCode' => $postalCode,
            'phoneNumber' => $phoneNumber
        );
        // FIXME: verify inputs - MUST DO IT IN A REAL SYSTEM
        $errorList = array();
        //
        if ($errorList) {
            $app->render('order.html.twig', array(
                'totalBeforeTax' => number_format($totalBeforeTax, 2),
                'shippingBeforeTax' => number_format($shippingBeforeTax, 2),
                'taxes' => number_format($taxes, 2),
                'totalWithShippingAndTaxes' => number_format($totalWithShippingAndTaxes, 2),
                'v' => $valueList,
                "eshopuser" => $_SESSION['eshopuser']
            ));
        } else { // SUCCESSFUL SUBMISSION
            DB::$error_handler = FALSE;
            DB::$throw_exception_on_error = TRUE;
            // PLACE THE ORDER
            try {
                DB::startTransaction();
                // 1. create summary record in 'orders' table (insert)
                DB::insert('orders', array(
                    'userID' => $_SESSION['eshopuser'] ? $_SESSION['eshopuser']['ID'] : NULL,
                    'name' => $name,
                    'address' => $address,
                    'postalCode' => $postalCode,
                    'email' => $email,
                    'phoneNumber' => $phoneNumber,
                    'totalBeforeTax' => $totalBeforeTax,
                    'shippingBeforeTax' => $shippingBeforeTax,
                    'taxes' => $taxes,
                    'totalWithShippingAndTaxes' => $totalWithShippingAndTaxes,
                    'dateTimePlaced' => date('Y-m-d H:i:s')
                ));
                $orderID = DB::insertId();
                // 2. copy all records from cartitems to 'orderitems' (select & insert)
                $cartitemList = DB::query(
                                "SELECT productID as origProductID, quantity, price"
                                . " FROM cartitems, products "
                                . " WHERE cartitems.productID = products.ID AND sessionID=%s", session_id());
                // add orderID to every sub-array (element) in $cartitemList
                array_walk($cartitemList, function(&$item, $key) use ($orderID) {
                    $item['orderID'] = $orderID;
                });
                /* This is the same as the following foreach loop:
                  foreach ($cartitemList as &$item) {
                  $item['orderID'] = $orderID;
                  } */
                DB::insert('orderitems', $cartitemList);
                // 3. delete cartitems for this user's session (delete)
                DB::delete('cartitems', "sessionID=%s", session_id());
                DB::commit();
                // TODO: send a confirmation email
                /*
                  $emailHtml = $app->view()->getEnvironment()->render('email_order.html.twig');
                  $headers = "MIME-Version: 1.0\r\n";
                  $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                  mail($email, "Order " .$orderID . " placed ", $emailHtml, $headers);
                 */
                //
                $msg->success('Order placed successful.');
                $msg->display();
                $app->render('eshop.html.twig', array(
                            "eshopuser" => $_SESSION['eshopuser']
                ));
            } catch (MeekroDBException $e) {
                DB::rollback();
                sql_error_handler(array(
                    'error' => $e->getMessage(),
                    'query' => $e->getQuery()
                ));
            }
        }
    }
})->via('GET', 'POST');