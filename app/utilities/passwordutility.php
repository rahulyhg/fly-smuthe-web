<?php

namespace app\utilities;

class PasswordUtility {

	const PasswordSize = 16;

	static function GenerateSalt(){
		return bin2hex(openssl_random_pseudo_bytes(self::PasswordSize));
        }

}

?>
