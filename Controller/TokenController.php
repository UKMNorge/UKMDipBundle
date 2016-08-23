<?php

namespace UKMNorge\UKMDipBundle\Controller;

// For å kunne dele sessions på flere sider
#ini_set('session.cookie_domain', '.ukm.dev' );

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

use HttpRequest;
use Symfony\Component\HttpFoundation\Request;
use UKMNorge\UKMDipBundle\Entity\Token;
use UKMNorge\UKMDipBundle\Entity\User;
use UKMNorge\UKMDipBundle\Security\Provider\DipBUserProvider;

use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\Security\Http\Event\AuthenticationEvent;

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
        #$location = $this->container->getParameter('ukm_dip.location');
        $location = $this->container->getParameter('ukm_dip.api_key');
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
                        $this->get('logger')->debug('UKMDipBundle: UserProvider: '.get_class($userProvider));
    					$user = $userProvider->loadUserByUsername($userId);
                        $this->get('logger')->debug('UKMDipBundle: Loaded user: '.$user->getUsername());

				        $token = new UsernamePasswordToken($user, $user->getPassword(), $firewall_name, $user->getRoles());

                        $this->get('logger')->debug('UKMDipBundle: UsernamePasswordToken: '. $token);
				        // For older versions of Symfony, use security.context here
                        if(\Symfony\Component\HttpKernel\Kernel::VERSION > 2.5) {
                            // Newer uses security.token_storage
				            $this->get("security.token_storage")->setToken($token);
                        }
                        else {
                            $this->get("security.context")->setToken($token);
                        }


                        // Apparently, we also need to store the token in the session
                        $session->set('_security_'.$firewall_name, serialize($token));

                        $this->get('event_dispatcher')->dispatch( 
                            AuthenticationEvents::AUTHENTICATION_SUCCESS, 
                            new AuthenticationEvent($token)
                        );

				        // Fire the login event
				        // Logging the user in above the way we do it doesn't do this automatically
					    #$request = $this->get("request");
                        $request = Request::CreateFromGlobals();
				        $event = new InteractiveLoginEvent($request, $token);
                        $this->get('logger')->info('UKMDipBundle: Dispatching interactive_login event.');
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
        $params['api_key'] = $this->getParameter('ukm_dip.api_key');
        $params['token'] = $token->getToken();
        $signer = $this->get('UKM.urlsigner');
        $params['sign'] = $signer->getSignedURL('POST', $params);
		#$curl->post(array('location' => $location, 'token' => $token->getToken()));
        $this->get('logger')->debug('UKMDipBundle: POSTing token to Delta: '.var_export($params));
        $curl->post($params);
		$res = $curl->process($this->dipURL);
        $this->get('logger')->debug('UKMDipBundle: Sent token to Delta.');
        $this->get('logger')->debug('UKMDipBundle: CURL-result: '.$res);

		// Redirect to Delta
        $this->get('logger')->debug('UKMDipBundle: Redirecting user to Delta.');
        $url = $this->deltaLoginURL.'?token='.$token->getToken().'&rdirurl='.$location;
        return $this->redirect($url);
    }

    // TODO: Fiks en listener som kan populate et eksternt brukerobjekt.
    public function receiveAction() {

        try {
    		// Receives a JSON-object in a POST-request from Delta
    		// This is all the user-data, plus a token
            $this->get('logger')->debug('UKMDipBundle: receiveAction.');

        	$request = Request::CreateFromGlobals();
        	$data = json_decode($request->request->get('json'));
            $this->get('logger')->debug('UKMDipBundle: Received data: '. var_export($data, true));

            $this->get('logger')->debug('UKMDipBundle: Token '.$data->token. ' received.');
            $this->get('logger')->debug('UKMDipBundle: User has delta_id '.$data->delta_id . '.');
            #$this->get('logger')->debug('UKMDipBundle: Data: '. var_export($data));

        	$repo = $this->getDoctrine()->getRepository('UKMDipBundle:Token');
        	$existingToken = $repo->findOneBy(array('token' => $data->token));
        	
            // Set token as authenticated
        	if (!$existingToken) {
                $this->get('logger')->error('UKMDipBundle: Token received from Delta does not exist in local database.');
                throw new Exception('Token does not exist', 20005);
            }
        	
            $this->get('logger')->debug('UKMDipBundle: Token exists in local database.');

            $existingToken->setAuth(true);
        	$existingToken->setUserId($data->delta_id);

        	$em = $this->getDoctrine()->getManager();
        	$em->persist($existingToken);

            $this->get('logger')->debug('UKMDipBundle: Token set as authenticated.');
        	#$em->flush(); // No need to flush more than once per request?

            if(!$this->validateData($data)) {
                $msg = 'UKMDipBundle: Dataene som ble tatt i mot feilet validering.';
                $this->get('logger')->error($msg);
                die($msg);
            }

        	// Find or update user
            $userClass = $this->getParameter('ukm_dip.user_class');
            $userRepo = $this->getDoctrine()->getRepository($userClass);
        	#$userRepo = $this->getDoctrine()->getRepository('UKMDipBundle:UserClass');
            #$user = null;
        	$user = $userRepo->findOneBy(array('deltaId' => $data->delta_id));
        	if (!$user) {
    			// Hvis bruker ikke finnes.
                // TODO: Event dispatcher som kan nekte brukere inntil de er godkjente.
                // TODO: Hvordan funker dette med ekstern bruker-klasse????
                $this->get('logger')->debug('UKMDipBundle: Creating new user of class '.$userClass);
                #$user = new $userClass();
                $userManager = $this->get('fos_user.user_manager');
                $user = $userManager->createUser();
        		#$user = new User();

        	}

            $this->get('logger')->debug('UKMDipBundle: UserClass: '.get_class($user).'. Saving user-data: ' . var_export($data, true));
            // TODO: Begrens lokal data-lagring, dette bør for det meste håndteres i brukerimplementasjon!
            // Vi har ikke nødvendigvis mottatt all data, så her bør det sjekkes. Kan også lagre null.
        	$user->setDeltaId($data->delta_id);
            $this->get('logger')->debug('UKMDipBundle: Satt delta-id');
            if($data->email)
                $user->setEmail($data->email);
            $this->get('logger')->debug('UKMDipBundle: Satt email');
    		if($data->first_name)  
                $user->setFirstName($data->first_name);
            $this->get('logger')->debug('UKMDipBundle: Satt firstName');
    		if($data->last_name)
                $user->setLastName($data->last_name);
            $this->get('logger')->debug('UKMDipBundle: Satt lastName');

            // La brukerobjektet lagre data
            $user->setData($data);
            $this->get('logger')->debug('UKMDipBundle: Kjørte setData på User');

    		#$time = new DateTime();
    		#$user->setBirthdate($time->getTimestamp());
    		#$user->setBirthdate($data['birthdate']);
    		// TODO: Set birthdate

            $this->get('logger')->debug('UKMDipBundle: Saving user.');
            // Lagre brukeren lokalt
    		$em->persist($user);
    		$em->flush();
            return new Response('Success (ReceiveAction)!');
        }
        catch (Exception $e) {
            $errorMsg = 'UKMDipBundle: receiveAction - En feil har oppstått: '.$e->getMessage(). ' at line '.$e->getLine();
            $this->get('logger')->error($errorMsg);
            $this->get('logger')->error('Stacktrace: '.$e->getTraceAsString());
            die('Error');
            throw new Exception($errorMsg);
        }
        
    	#return $this->render('UKMDipBundle:Default:index.html.twig', array('name' => 'Received'));
    }

    private function validateData($data) {
        $valid = true;
        if(!is_object($data))
            $valid = false;
        if(!isset($data->delta_id))
            $valid = false;

        return $valid;
    }

}