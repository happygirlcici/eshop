<?php

$app->get('/register', function() use ($app, $log) {
    $app->render('register.html.twig', array(
        "eshopuser" => $_SESSION['eshopuser']
    ));
});

// State 2: submission
$app->post('/register', function() use ($app, $log, $msg) {
    $name = $app->request->post('name');
    $email = $app->request->post('email');
    $pass1 = $app->request->post('pass1');
    $pass2 = $app->request->post('pass2');
    $valueList = array('name' => $name, 'email' => $email);
    // submission received - verify
    $errorList = array();
    if (strlen($name) < 4) {
        array_push($errorList, "Name must be at least 4 characters long");
        unset($valueList['name']);
    } else {
        $user = DB::queryFirstRow("SELECT ID FROM users WHERE name=%s", $name);
        if ($user) {
            array_push($errorList, "username already registered");
            unset($valueList['name']);
        }
    }

    if (filter_var($email, FILTER_VALIDATE_EMAIL) === FALSE) {
        array_push($errorList, "Email does not look like a valid email");
        unset($valueList['email']);
    } else {
        $user = DB::queryFirstRow("SELECT ID FROM users WHERE email=%s", $email);
        if ($user) {
            array_push($errorList, "Email already registered");
            unset($valueList['email']);
        }
    }
    // ALTERNATIVE: if (($msg = verifyPassword($pass1)) !== TRUE) {
    $msg1 = verifyPassword($pass1);
    if ($msg1 !== TRUE) {
        array_push($errorList, $msg1);
    } else if ($pass1 != $pass2) {
        array_push($errorList, "Passwords don't match");
    }
    //
    if ($errorList) {
        // STATE 3: submission failed        
        $app->render('register.html.twig', array(
            'errorList' => $errorList, 'v' => $valueList
        ));
    } else {
        // STATE 2: submission successful
        DB::insert('users', array(
            'name' => $name,
            'email' => $email,
            'password' => password_hash($pass1, CRYPT_BLOWFISH)
                // 'password' => hash('sha256', $pass1)
        ));
        $id = DB::insertId();
        $log->debug(sprintf("User %s created", $id));
        $msg->success('Registration successful. You may now login.');
        $msg->display();
        $app->render('eshop.html.twig');
    }
});

$app->get('/login', function() use ($app, $log) {
    
    $app_id = '306062613148736';
    $app_secret = 'b7f985a9ce4310a37c04c9a1c2bdb557';

    $fb = new \Facebook\Facebook([
        'app_id' => $app_id,
        'app_secret' => $app_secret,
        'default_graph_version' => 'v2.9',
            //'default_access_token' => '{access-token}', // optional
    ]);

    // Use one of the helper classes to get a Facebook\Authentication\AccessToken entity.
    $helper = $fb->getRedirectLoginHelper();
    //   $helper = $fb->getJavaScriptHelper();
    //   $helper = $fb->getCanvasHelper();
    //   $helper = $fb->getPageTabHelper();

    $permissions = ['email']; // Optional permissions
//    print_r('https://' . $_SERVER['SERVER_NAME'] . '/app/routes/fbcallback.php');
    $FBLoginUrl = $helper->getLoginUrl('https://' . $_SERVER['SERVER_NAME'] . '/fbcallback', $permissions);

    $app->render('login.html.twig', array(
        "FBLoginUrl" => $FBLoginUrl,
        "eshopuser" => $_SESSION['eshopuser']
    ));
});

$app->post('/login', function() use ($app, $log, $msg) {
    $name = $app->request->post('name');
    $pass = $app->request->post('pass');
    $user = DB::queryFirstRow("SELECT * FROM users WHERE name=%s", $name);
    if (!$user || $user['status'] == 'Blocked') {
        $log->debug(sprintf("User failed for username %s from IP %s", $name, $_SERVER['REMOTE_ADDR']));
        $msg->warning(' You cannot login now. This user is blocked.');
        $msg->display();
        $app->render('login.html.twig', array('loginFailed' => TRUE));
    } else {
        // password MUST be compared in PHP because SQL is case-insenstive
        if (crypt($pass, $user['password']) == $user['password']) {
            // LOGIN successful
            unset($user['password']);
            $_SESSION['eshopuser'] = $user;
            $log->debug(sprintf("User %s logged in successfuly from IP %s", $user['ID'], $_SERVER['REMOTE_ADDR']));
            if ($user['role'] === 'admin') {
                $userList = DB::query("SELECT * FROM users");
                $app->render('admin_panel.html.twig', array(
                    'userList' => $userList,
                    "eshopuser" => $_SESSION['eshopuser']
                ));
            } else {
                $msg->success('Welcome ' . $user['name'] . ', you login successfully');
                $msg->display();
                $app->render('eshop.html.twig', array(
                    "eshopuser" => $_SESSION['eshopuser']
                ));
            }
        } else {
            $log->debug(sprintf("User failed for username %s from IP %s", $name, $_SERVER['REMOTE_ADDR']));
            $app->render('login.html.twig', array('loginFailed' => TRUE));
        }
    }
});

$app->get('/fbcallback', function() use ($app, $log, $msg) {
    
    $app_id = '306062613148736';
    $app_secret = 'b7f985a9ce4310a37c04c9a1c2bdb557';

    $fb = new \Facebook\Facebook([
        'app_id' => $app_id,
        'app_secret' => $app_secret,
        'default_graph_version' => 'v2.9',
    ]);
    
    $helper = $fb->getRedirectLoginHelper();
    try {
        $accessToken = $helper->getAccessToken();
    } catch (Facebook\Exceptions\FacebookResponseException $e) {
        // When Graph returns an error
        $log->error(sprintf("Graph returned an error: " . $e->getMessage()));
        exit;
    } catch (Facebook\Exceptions\FacebookSDKException $e) {
        // When validation fails or other local issues
        $log->error(sprintf('Facebook SDK returned an error: ' . $e->getMessage()));
        exit;
    }

    if (!isset($accessToken)) {
        if ($helper->getError()) {
            header('HTTP/1.0 401 Unauthorized');
            $log->error(sprintf("Error: " . $helper->getError() . "\n"));
            $log->error(sprintf("Error Code: " . $helper->getErrorCode() . "\n"));
            $log->error(sprintf("Error Reason: " . $helper->getErrorReason() . "\n"));
            $log->error(sprintf("Error Description: " . $helper->getErrorDescription() . "\n"));
        } else {
            header('HTTP/1.0 400 Bad Request');
            $log->error(sprintf('Bad request'));
        }
        exit;
    }

// Logged in
    $log->debug(sprintf('Access Token: ' . $accessToken->getValue()));
    
// The OAuth 2.0 client handler helps us manage access tokens
    $oAuth2Client = $fb->getOAuth2Client();

// Get the access token metadata from /debug_token
    $tokenMetadata = $oAuth2Client->debugToken($accessToken);
//    $log->debug(sprintf('Metadata: ' . var_dump($tokenMetadata)));
    
// Validation (these will throw FacebookSDKException's when they fail)
    $tokenMetadata->validateAppId($app_id);
    
// If you know the user ID this access token belongs to, 
// you can validate it here $tokenMetadata->validateUserId('123');
    $tokenMetadata->validateExpiration();

    if (!$accessToken->isLongLived()) {
        // Exchanges a short-lived access token for a long-lived one
        try {
            $accessToken = $oAuth2Client->getLongLivedAccessToken($accessToken);
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            $log->error(sprintf("<p>Error getting long-lived access token: " . $e->getMessage() . "</p>\n\n"));
            exit;
        }
//        $log->debug(sprintf('Long-lived: ' . var_dump($accessToken->getValue())));
    }
    try {
        // Returns a `Facebook\FacebookResponse` object
        $response = $fb->get('/me?fields=id,name,email', (string)$accessToken);
    } catch(Facebook\Exceptions\FacebookResponseException $e) {
        $log->error(sprintf("Graph returned an error: " . $e->getMessage()));
        exit;
    } catch(Facebook\Exceptions\FacebookSDKException $e) {
        $log->error(sprintf('Facebook SDK returned an error: ' . $e->getMessage()));
        exit;
    }
    
    $user = $response->getGraphUser();
    $_SESSION['eshopuser'] = $user;
//    session_id((string)$accessToken);
    $log->debug(sprintf("User %s logged in successfuly from IP %s", (string)$accessToken, $_SERVER['REMOTE_ADDR']));
    $u = DB::queryFirstRow("SELECT * FROM users WHERE name=%s", $user['name']);
    if((null === $u || $u['fbid'] != $user['id'])) {
        DB::insert('users', array(
        'name' => $user['name'],
        'email' => $user['email'],
        'fbid' => $user['id']
    ));
    }
    $msg->success('Welcome ' . $user['name'] . ', you login successfully');
    $msg->display();
    $app->render('eshop.html.twig', array(
        "eshopuser" => $_SESSION['eshopuser']
    ));
});

$app->get('/logout', function() use ($app, $log, $msg) {
    
    $app_id = '306062613148736';
    $app_secret = 'b7f985a9ce4310a37c04c9a1c2bdb557';

    $fb = new \Facebook\Facebook([
        'app_id' => $app_id,
        'app_secret' => $app_secret,
        'default_graph_version' => 'v2.9',
    ]);
    
    $helper = $fb->getRedirectLoginHelper();
    try {
        $accessToken = $helper->getAccessToken();
    } catch (Facebook\Exceptions\FacebookResponseException $e) {
        // When Graph returns an error
        $log->error(sprintf("Graph returned an error: " . $e->getMessage()));
        exit;
    } catch (Facebook\Exceptions\FacebookSDKException $e) {
        // When validation fails or other local issues
        $log->error(sprintf('Facebook SDK returned an error: ' . $e->getMessage()));
        exit;
    }
    $user = $_SESSION['eshopuser'];
    $u = DB::queryFirstRow("SELECT * FROM users WHERE name=%s", $user['name']);
    if( null != $u['fbid']) {
        $url = 'https://www.facebook.com/logout.php?next=' . 'https://eshop.ipd9.info' .
                '&access_token=' . (string)$accessToken;
        session_destroy();
        header('Location: '.$url);
    }
    $log->debug(sprintf("User %s logout successfully", $user['name']));
    $_SESSION['eshopuser'] = array();
    $msg->success('Logout successfully');
    $msg->display();
    $app->render('eshop.html.twig');
});

// PASSWOR RESET
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

$app->map('/passreset', function () use ($app, $log, $msg) {
    // Alternative to cron-scheduled cleanup
    if (rand(1, 1000) == 111) {
        // TODO: do the cleanup 1 in 1000 accessed to /passreset URL
    }
    if ($app->request()->isGet()) {
        $app->render('passreset.html.twig');
    } else {
        $email = $app->request()->post('email');
        $user = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
        if ($user) {
            $secretToken = generateRandomString(50);
            // VERSION 1: delete and insert
            /*
              DB::delete('passresets', 'userID=%d', $user['ID']);
              DB::insert('passresets', array(
              'userID' => $user['ID'],
              'secretToken' => $secretToken,
              'expiryDateTime' => date("Y-m-d H:i:s", strtotime("+5 hours"))
              )); */
            // VERSION 2: insert-update TODO
            DB::insertUpdate('passresets', array(
                'userID' => $user['ID'],
                'secretToken' => $secretToken,
                'expiryDateTime' => date("Y-m-d H:i:s", strtotime("+5 minutes"))
            ));
            // email user
            $url = 'http://' . $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . '/passreset/' . $secretToken;
            $html = $app->view()->render('email_passreset.html.twig', array(
                'name' => $user['name'],
                'url' => $url
            ));
//            $headers = "MIME-Version: 1.0\r\n";
//            $headers.= "Content-Type: text/html; charset=UTF-8\r\n";
//            $headers.= "From: Noreply <noreply@ipd9.info>\r\n";
//            $headers.= "To: " . htmlentities($user['name']) . " <" . $email . ">\r\n";
//
//            mail($email, "Password reset from eShop", $html, $headers);

            /* CONFIGURATION */
            $crendentials = array(
                'email' => 'yangjun3461@gmail.com', //Your GMail adress
                'password' => 'eshop1234'               //Your GMail password
            );

            /* SPECIFIC TO GMAIL SMTP */
            $smtp = array(
                'host' => 'smtp.gmail.com',
                'port' => 587,                      // tls 587
                'username' => $crendentials['email'],
                'password' => $crendentials['password'],
                'secure' => 'TLS' //SSL or TLS
            );

            $mail = new PHPMailer;

            $mail->SMTPDebug = 3;                               // Enable verbose debug output

            $mail->isSMTP();                                      // Set mailer to use SMTP
            $mail->Host = $smtp['host'];  // Specify main and backup SMTP servers
            $mail->SMTPAuth = true;                               // Enable SMTP authentication
            $mail->Username = $smtp['username'];                 // SMTP username
            $mail->Password = $smtp['password'];                           // SMTP password
            $mail->SMTPSecure = $smtp['secure'];                            // Enable TLS encryption, `ssl` also accepted
            $mail->Port = $smtp['port'];                                    // TCP port to connect to

            $mail->setFrom('eshop@ipd9.info', 'Mailer');
            $mail->addAddress($email);               // Name is optional
//            $mail->addReplyTo('info@example.com', 'Information');
//            $mail->addCC('cc@example.com');
//            $mail->addBCC('bcc@example.com');
//            $mail->addAttachment('/var/tmp/file.tar.gz');         // Add attachments
//            $mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name
            $mail->isHTML(true);                                  // Set email format to HTML

            $mail->Subject = 'This is the password reset email from E-Shop';
            $mail->Body = $html;
//            $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

            if (!$mail->send()) {
                $log->debug(sprintf("Message could not be sent. Mailer Error: %s", $mail->ErrorInfo));
            } else {
                $log->debug(sprintf("Message has been sent"));
                $msg->success('Email with password reset code has been sent. Please allow the email a few minutes to arrive.');
                $msg->display();
                $app->render('eshop.html.twig', array(
                             "eshopuser" => $_SESSION['eshopuser']
                ));
            }
        } else {
            print_r('send error');
            $app->render('passreset.html.twig', array('error' => TRUE));
        }
    }
})->via('GET', 'POST');

$app->map('/passreset/:secretToken', function($secretToken) use ($app, $msg) {
    $row = DB::queryFirstRow("SELECT * FROM passresets WHERE secretToken=%s", $secretToken);
    if (!$row || strtotime($row['expiryDateTime']) < time()) {
        $msg->success('Password reset token does not exist or has expired. You may request a new token');
        $msg->display();
        $app->render('passreset.html.twig');
        return;
    }

    if ($app->request()->isGet()) {
        $app->render('passreset_form.html.twig');
    } else {
        $pass1 = $app->request()->post('pass1');
        $pass2 = $app->request()->post('pass2');
        // TODO: verify password quality and that pass1 matches pass2
        $errorList = array();
        $msg1 = verifyPassword($pass1);
        if ($msg1 !== TRUE) {
            array_push($errorList, $msg1);
        } else if ($pass1 != $pass2) {
            array_push($errorList, "Passwords don't match");
        }
        //
        if ($errorList) {
            $app->render('passreset_form.html.twig', array(
                'errorList' => $errorList
            ));
        } else {
            // success - reset the password
            DB::update('users', array(
                'password' => password_hash($pass1, CRYPT_BLOWFISH)
                    ), "ID=%d", $row['userID']);
            DB::delete('passresets', 'secretToken=%s', $secretToken);
            $msg->success('Password reset successful. You can login now');
            $msg->display();
            $app->render('eshop.html.twig', array(
                         "eshopuser" => $_SESSION['eshopuser']
            ));
        }
    }
})->via('GET', 'POST');

$app->get('/emailexists/:email', function($email) use ($app, $log) {
    $user = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
    if ($user) {
        echo "Email already registered";
    }
});

// returns TRUE if password is strong enough,
// otherwise returns string describing the problem
function verifyPassword($pass1) {
    if (!preg_match('/[0-9;\'".,<>`~|!@#$%^&*()_+=-]/', $pass1) || (!preg_match('/[a-z]/', $pass1)) || (!preg_match('/[A-Z]/', $pass1)) || (strlen($pass1) < 8)) {
        return "Password must be at least 8 characters " .
                "long, contain at least one upper case, one lower case, " .
                " one digit or special character";
    }
    return TRUE;
}

$app->get('/register', function() use ($app) {
    $app->render('register.html.twig', array(
        "eshopuser" => $_SESSION['eshopuser']
    ));
});
