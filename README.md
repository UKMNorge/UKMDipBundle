UKMDipBundle
========================

Hva er dette?
-------------

Hvordan komme i gang?
---------------------

** Via Composer **

1.
   Legg til disse linjene i composer.json i prosjektet ditt, og kjør en composer install.

   ```composer
   "repositories": [
        {
            "url": "https://github.com/UKMNorge/UKMDipBundle.git",
            "type": "git"
        }
    ],
    "require": {
    	"ukmnorge/ukmdipbundle": "dev-master"
    }

   ``` 
   Siden UKMDipBundle ikke er i stabil versjon enda bruker vi et wildcard i `require`-keyen.

2. Sørg for at FOSUserBundle er installert:
   Dette skal i utgangspunktet skje automatisk når du requirer UKMDipBundle, men det skaper ingen problemer å gjøre det manuelt her.

   `composer require friendsofsymfony/user-bundle "~2.0@dev"`

3. Legg til linjene under i `app/AppKernel.php`

   ```
   new UKMNorge\UKMDipBundle\UKMDipBundle(),
   new FOS\UserBundle\FOSUserBundle(),
   ```

4. Opprett en bruker-klasse som extender DIPs brukerklasse. 
   Dip extender FOSUserBundle's brukerklasse for å legge til noen ekstra felt, og det er denne klassen du selv må extende for å kunne bruke DIP.

   OBS! Du kan ikke duplisere noen av feltene som finnes i BaseUser - se [https://github.com/UKMNorge/UKMDipBundle/blob/master/Entity/UserClass.php]

 ```php
	<?php
	// src/AppBundle/Entity/User.php

	namespace AppBundle\Entity;

	use UKMNorge\UKMDipBundle\Entity\UserClass as BaseUser;
	use Doctrine\ORM\Mapping as ORM;

	/**
	 * @ORM\Entity
	 * @ORM\Table(name="dip_user")
	 */
	class User extends BaseUser
	{

	    public function __construct()
	    {
	        parent::__construct();
	        // your own logic
	    }
	}

 ```

5. Endre app/config/security.yml
   Dip baserer seg på FOSUserBundle, som må være konfigurert rett. Det er derfor en del konfigurasjonsverdier som må settes inn.
   Det er også her du må sette opp brannmuren rett og konfigurere hvilke områder av appen som skal være tilgjengelig i de forskjellige tilgangsnivåene.

 ```yaml
    # app/config/security.yml (DipBundle-version)
    security:
	    encoders:
	        UKMDipBundle\Entity\User: 'sha256'
	        FOS\UserBundle\Model\UserInterface: sha512

	    role_hierarchy:
	        ROLE_ADMIN:       ROLE_USER
	        ROLE_SUPER_ADMIN: ROLE_ADMIN

	    providers:
	        dip:
	            #id: dipb_user_provider
	            entity:
	                class: UKMDipBundle:User
	            #d: fos_user.user_provider.username

	    firewalls:
	        secure_area:
	            pattern: ^/
	            provider: dipb_user_provider
	            #login_path: /dip/login
	            logout:
	                path: /logout
	                target: /
	            anonymous: true

	    access_control:
	        - { path: ^/login$, role: IS_AUTHENTICATED_ANONYMOUSLY }
	        - { path: ^/dip$, role: IS_AUTHENTICATED_ANONYMOUSLY }
	        - { path: ^/secure_area, roles: ROLE_USER }
	        - { path: ^/$, role: IS_AUTHENTICATED_ANONYMOUSLY }

 ```

6. Endre app/config/config.yml
   FOSUserBundle har noen detaljer som må inn i config-fila. DIP støtter kun ORM og ingen av FOSUserBundles alternativer.
   firewall_name er navnet på brannmuren du konfigurerte i forrige steg.
   user_class er brukerklassen din, inkludert namespace.

 ```yaml
    fos_user:
        db_driver: orm
        firewall_name: secure_area
        user_class: AppBundle\Entity\User
 ```

7. Endre app/config/routing.yml
   Lim inn linjen under i routing-konfigurasjonen din for å laste inn DIP-ruter:

 ```yaml
 	dip:
    	resource: "@UKMDipBundle/Resources/config/routing.yml"
 ```

8. Endre app/config/parameters.dist.yml
   `ukm_dip.firewall_area` er navnet på brannmuren du har satt opp i security.yml.
   `ukm_dip.location` er deprecated.
   `ukm_dip.api_key` er api-nøkkelen du har fått fra UKM support.
   `ukm_dip.api_secret` er api-secreten du har fått fra UKM support.
   `ukm_dip.entry_point` er navnet på en route til siden brukerne skal sendes til når de er innlogget.
   `ukm_dip.token_salt` er en tekst-streng som brukes til å salte tokens.
   `ukm_dip.user_class` er brukerentiteten din, med fullt namespace (i.e. AppBundle\Entity\User`).

 ```yaml
    ukm_dip.location: 'location'
    ukm_dip.firewall_area: secure_area
    ukm_dip.entry_point: ~
    ukm_dip.api_key: ~
    ukm_dip.api_secret: ~
    ukm_dip.token_salt: ~
    ukm_dip.user_class: AppBundle\Entity\User 
 ```

9. Oppdater database-mappingen
   
   ```
   php bin/console doctrine:schema:update --force
   ```

10. Kjør en `composer install`

TODO
----
- Fiks config slik at fos_user.user_class kan brukes i stedet for å måtte sette verdien i parameters.
- Oppdater rekkefølgen på punktene - composer install og parameters.dist.yml må nok lenger fram i lista.

Requirements
------------


