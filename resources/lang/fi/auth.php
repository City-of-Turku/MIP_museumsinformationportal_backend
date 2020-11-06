<?php
return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */
    'failed' => 'Antamasi käyttäjätunnus/salasana pari ei täsmää.',
    'throttle' => 'Olet yrittänyt kirjautua liian monta kertaa. Yritä uudelleen :seconds sekunnin kuluttua.',
		
		
		
	/*
	 * Custom lang definitions
	 */
	'custom' => [
		'attribute-name' => [
			'rule-name' => 'custom-message',
		],
		'login_success' 		=> 'Kirjautuminen onnistui.',
		'logout_success' 		=> 'Uloskirjautuminen onnistui.',
		'logout_failed' 		=> 'Uloskirjautuminen epäonnistui.',
		'token_not_provided' 	=> 'Vaadittu autentikointi token puuttuu.',
		'token_has_expired'		=> 'Autentikointi token on vanhentunut.',
		'token_not_refreshable' => 'Autentikointi token on vanhentunut eikä sitä voida enää uudistaa. Uudelleen kirjautuminen vaaditaan.',
		'token_not_valid'		=> 'Autentikointi token ei ole validi.',
		'token_is_valid'		=> 'Autentikonti token on validi.',
		'user_not_found'		=> 'Käyttäjää ei löydy.',
			
		/*
		 * * moved to "kayttaja"
		'register_failed'		=> 'Käyttäjän luonti ei onnistunut.',
		'register_success'		=> 'Uuden käyttäjän luominen onnistui.',
		'user_found_count'		=> 'Antamillasi hakuehdoilla löytyi :count käyttäjää.',
		'user_deleted'			=> "Käyttäjän poistaminen onnistui",
		'user_delete_failed'	=> 'Käyttäjän poistaminen ei onnistunut.',
		'user_updated'			=> 'Käyttäjän tiedot on nyt muutettu.',
		'user_update_failed'	=> 'Käyttäjän tietojen muuttaminen epäonnistui.',
		*/
	],
];
