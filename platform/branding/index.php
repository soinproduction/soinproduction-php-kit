<?php
	add_action( "login_head", "custom_login_logo" );

	function custom_login_logo() {
		$url = BUILD . 'img/logo.svg';

		echo "<style>
            body.login form { margin:25px 0 0 0; }
            body.login #login h1 a {
                background:url($url) no-repeat center center;
                background-size: contain;
                width: 100%;
                height: auto;
                aspect-ratio: 1 / .2;
                display:block;
                padding:0;
                margin:5px auto 0;
                position :relative;
                left: 0px;
            }
        </style>";
	}
