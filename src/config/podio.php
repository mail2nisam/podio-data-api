<?php
/**
 * Created by PhpStorm.
 * User: nisamudheen
 * Date: 11/5/17
 * Time: 2:25 PM
 */

return [
    'auth'=>[
        'email'=>'',//You podio email address
        'password'=>'',//Podio account password,
    ],
    'auth_credentials'=>[
        /**
         * Add multiple client and secret here to avoid Podio api rate-limit error
         * @type array
         */
//      'client_id'=>'client_secret'
    ],
    'apps'=>[
        /**
         * Podio app ids
         * @type array
         */
        1234567,
        4545454
    ]
];