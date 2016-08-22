<?php

namespace UKMNorge\UKMDipBundle\Controller;

// For å kunne dele sessions på flere sider
#ini_set('session.cookie_domain', '.ukm.dev' );

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

use HttpRequest;
use Symfony\Component\HttpFoundation\Request;
use UKMNorge\UKMDipBundle\Entity\Token;
use UKMNorge\UKMDipBundle\Entity\User;
use UKMNorge\UKMDipBundle\Security\Provider\DipBUserProvider;

use UKMCurl;
use Exception;
use DateTime;


class TokenController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('UKMDipBundle:Default:index.html.twig', array('name' => $name));
    }

    public function loginAction() 
    {	
        if ( $this->container->hasParameter('UKM_HOSTNAME') && $this->container->getParameter('UKM_HOSTNAME') == 'ukm.dev') {
            $this->dipURL = 'http://delta.ukm.dev/web/app_dev.php/dip/token';
            $this->deltaLoginURL = 'http://delta.ukm.dev/web/app_dev.php/login';
        } 
        else {
            $this->dipURL = 'http://delta.ukm.no/dip/token';
            $this->deltaLoginURL = 'http://delta.ukm.no/login';
        }

        $this->get('logger')->debug('UKMDipBundle: dipURL = '.$this->dipURL);
        $this->get('logger')->debug('UKMDipBundle: deltaLoginURL = '.$this->deltaLoginURL);

       /* $this->dipURL = 'http://delta.ukm.no/dip/token';
        $this->deltaLoginURL = 'http://delta.ukm.no/login';*/

    	require_once('UKM/curl.class.php');
    	// Dette er entry-funksjonen til DIP-innlogging.
    	// Her sjekker vi om brukeren har en session med en autentisert token, 
    	// og hvis ikke genererer vi en og sender brukeren videre til Delta.

    	// Send request to Delta with token-info
    	// $dipURL = 'http://delta.ukm.dev/web/app_dev.php/dip/token';
        #$location = 'ambassador';
        $location = $this->container->getParameter('ukm_dip.location');
        #$firewall_name = 'secure_area';
        $firewall_name = $this->container->getParameter('ukm_dip.firewall_area');
        #$entry_point = 'ukm_amb_join_address';
        $entry_point = $this->container->getParameter('ukm_dip.entry_point');
    	$curl = new UKMCurl();

        $this->get('logger')->info('UKMDipBundle: Authorization flow started.');

    	// Har brukeren en session med token?
    	$session = $this->get('session');
    	if ($session->isStarted()) {
    		$token = $session->get('token');
            $this->get('logger')->debug('UKMDipBundle: User has session.');
            // Hvis token finnes
    		if ($token) {
                $this->get('logger')->debug('UKMDipBundle: User has token '.$token);
    			// Hvis token finnes, sjekk at det er autentisert i databasen
    			$repo = $this->getDoctrine()->getRepository('UKMDipBundle:Token');
    			$existingToken = $repo->findOneBy(array('token' => $token));
    			if ($existingToken) {
                    $this->get('logger')->debug('UKMDipBundle: The token is in database.');
    				// Hvis token finnes
    				if ($existingToken->getAuth() == true) {   
                        $this->get('logger')->debug('UKMDipBundle: Token is Authorized, logging in user.');

    					// Authorized, so trigger log in
    					$userId = $existingToken->getUserId();
                        
    					// Load user data?
    					$userProvider = $this->get('dipb_user_provider');
    					$user = $userProvider->loadUserByUsername($userId);
				        $token = new UsernamePasswordToken($user, $user->getPassword(), $firewall_name, $user->getRoles());

				        // For older versions of Symfony, use security.context here
                        // Newer uses security.token_storage
				        $this->get("security.token_storage")->setToken($token);

				        // Fire the login event
				        // Logging the user in above the way we do it doesn't do this automatically
					    $request = $this->get("request");
				        $event = new InteractiveLoginEvent($request, $token);
				        $this->get("event_dispatcher")->dispatch("security.interactive_login", $event);

				        // Redirect til en side bak firewall i stedet
				        return $this->redirect($this->generateUrl($entry_point));
    				}
    				else {
    					// Hvis token ikke er autentisert enda
                        $this->get('logger')->debug('UKMDipBundle: Token not authorized, invalidating it and restarting login flow.');
    					// Fjern lagret token
    					$session->invalidate();
                        // Redirect til Delta
                        return $this->redirect($this->get('router')->generate('ukm_dip_login'));
    				}
    			}
    			// Token finnes, men ikke i databasen.
    			// Ingen token som matcher, ugyldig?
    			// Genererer ny og last inn siden på nytt?
                $this->get('logger')->error('UKMDipBundle: User has invalid token '.$token);
                // Denne burde ikke dukke opp!
                $session->invalidate();
                return $this->redirect($this->get('router')->generate('ukm_dip_login'));
    		}
            // Brukeren har en session, men ikke token.
            $this->get('logger')->debug('UKMDipBundle: User has no token.');
    	}
        // Brukeren har ikke en session, start en.
    	else {
            $this->get('logger')->debug('UKMDipBundle: Created new session for user.');
    		$session = new Session();
    		$session->start();
    	}

		// Generate token entity
		$token = new Token($this->container->getParameter('ukm_dip.token_salt'));
        $this->get('logger')->debug('UKMDipBundle: Created new token for user.');
		// Update session with token
		$session->set('token', $token->getToken());
		// Update database with the new token
		$em = $this->getDoctrine()->getManager();
    	$em->persist($token);
    	$em->flush();
		
		// Send token to Delta
		$curl->post(array('location' => $location, 'token' => $token->getToken()));
		$res = $curl->process($this->dipURL);
    	
        $this->get('logger')->debug('UKMDipBundle: Sent token to Delta.');
		// Redirect to Delta
        $url = $this->deltaLoginURL.'?token='.$token->getToken().'&rdirurl='.$location;
        return $this->redirect($url);
    }

    // TODO: Fiks en listener som kan populate et eksternt brukerobjekt.
    public function receiveAction() {
		// Receives a JSON-object in a POST-request from Delta
		// This is all the user-data, plus a token
        $this->get('logger')->debug('UKMDipBundle: Token received.');
    	$request = Request::CreateFromGlobals();
    	$data = json_decode($request->request->get('json'));

    	$repo = $this->getDoctrine()->getRepository('UKMDipBundle:Token');
    	$existingToken = $repo->findOneBy(array('token' => $data->token));
    	
        // Set token as authenticated
    	if (!$existingToken) {
            $this->get('logger')->error('UKMDipBundle: Token received from Delta does not exist in local database.');
            throw new Exception('Token does not exist', 20005);
        }
    	
        $existingToken->setAuth(true);
    	$existingToken->setUserId($data->delta_id);

    	$em = $this->getDoctrine()->getManager();
    	$em->persist($existingToken);
    	#$em->flush(); // No need to flush more than once per request

    	// Find or update user
        $userClass = $this->getParameter('fos_user.user_class');
        $userRepo = $this->getDoctrine()->getRepository($userClass);
    	#$userRepo = $this->getDoctrine()->getRepository('UKMDipBundle:User');
    	$user = $userRepo->findOneBy(array('deltaId' => $data->delta_id));
    	if (!$user) {
			// Hvis bruker ikke finnes.
            // TODO: Event dispatcher som kan nekte brukere inntil de er godkjente.
            // TODO: Hvordan funker dette med ekstern bruker-klasse????
            $this->get('logger')->debug('UKMDipBundle: Creating new user of class '.$userClass);
            $user = new $userClass();
    		#$user = new User();

    	}

        $this->get('logger')->debug('UKMDipBundle: Saving user-data: '.var_export($data));
        // TODO: Begrens lokal data-lagring, dette håndteres for det meste i brukerimplementasjon!
        // Vi har ikke nødvendigvis mottatt all data, så her bør det sjekkes. Kan også lagre null.
    	$user->setDeltaId($data->delta_id);
        if($data->email)
            $user->setEmail($data->email);
        if($data->phone)
            $user->setPhone($data->phone);
        if($data->address)
            $user->setAddress($data->address);
        if($data->post_number)
            $user->setPostNumber($data->post_number);
		if($data->post_place)
            $user->setPostPlace($data->post_place);
		if($data->first_name)  
            $user->setFirstName($data->first_name);
		if($data->last_name)
            $user->setLastName($data->last_name);
        if($data->facebook_id)
		  $user->setFacebookId($data->facebook_id);
		if($data->facebook_id_unencrypted)
            $user->setFacebookIdUnencrypted($data->facebook_id_unencrypted);
		if($data->facebook_access_token)
            $user->setFacebookAccessToken($data->facebook_access_token);

		$time = new DateTime();
		$user->setBirthdate($time->getTimestamp());
		#$user->setBirthdate($data['birthdate']);
		// TODO: Set birthdate

        $this->get('logger')->debug('UKMDipBundle: Saving user.');
        // Lagre brukeren lokalt
		$em->persist($user);
		$em->flush();

    	return $this->render('UKMDipBundle:Default:index.html.twig', array('name' => 'Received'));
    }

}