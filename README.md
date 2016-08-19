UKMDipBundle
========================

Hva er dette?
-------------

Hvordan komme i gang?
---------------------

1. Klon dette Git-repoet til src/-mappen i Symfony-prosjektet.
2. Sørg for at FOSUserBundle er installert:

   `composer require friendsofsymfony/user-bundle "~2.0@dev"`

3. Legg til linjene under i `app/AppKernel.php`
   ```
   new UKMNorge\UKMDipBundle\UKMDipBundle(),
   new FOS\UserBundle\FOSUserBundle(),
   ```

4. Opprett en bruker-klasse som extender DIPs brukerklasse. 
   Dip extender FOSUserBundle's brukerklasse for å legge til noen ekstra felt, og det er denne klassen du selv må extende for å kunne bruke DIP.

 ```php
	<?php
	// src/AppBundle/Entity/User.php

	namespace AppBundle\Entity;

	use UKMNorge\UKMDipBundle\Entity\User as BaseUser;
	use Doctrine\ORM\Mapping as ORM;

	/**
	 * @ORM\Entity
	 * @ORM\Table(name="dip_user")
	 */
	class User extends BaseUser
	{
		/**
	     * @ORM\Id
	     * @ORM\Column(type="integer")
	     * @ORM\GeneratedValue(strategy="AUTO")
	     */
	    protected $id;

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

7. Endre app/config/parameters.dist.yml
    ukm_dip.token_salt

8. Lag en klasse som implementerer DIPUserInterface
   Fordi du skal ha muligheten til å benytte din egen klasse, trenger DIP en måte å få tilgang til objektet ditt for å lagre data. Måten dette gjøres på, er ved hjelp av et UserInterface du implementerer. 

 ```php
 <?php
 interface DIPUserInterface {
 	public function get($userId);
 	public function getByUsername($username);
 	public function save(User $user);
 }

 ``` 


Requirements
------------


