<?php

namespace UKMNorge\UKMDipBundle\Controller;

// For å kunne dele sessions på flere sider
#ini_set('session.cookie_domain', '.ukm.dev' );

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        if( $this->container->hasParameter('ukm_dip.token_address') ) {
            $this->dipURL = $this->container->getParameter('ukm_dip.token_address');
        }
        else {
            $this->get('logger')->info('UKMDipBundle: Not specifying ukm_dip.token_address in parameters is DEPRECATED. Please update your configuration.');
            if ( $this->container->hasParameter('UKM_HOSTNAME') && $this->container->getParameter('UKM_HOSTNAME') == 'ukm.dev') {
                $this->dipURL = 'http://delta.ukm.dev/app_dev.php/dip/token';
            } 
            else {
                $this->dipURL = 'https://delta.ukm.no/dip/token';
            }    
        }
        
        if( $this->container->hasParameter('ukm_dip.delta_login_address') ) {
            $this->deltaLoginURL = $this->container->getParameter('ukm_dip.delta_login_address');
        }
        else {
            $this->get('logger')->info('UKMDipBundle: Not specifying ukm_dip.delta_login_address in parameters is DEPRECATED. Please update your configuration.');
            if ( $this->container->hasParameter('UKM_HOSTNAME') && $this->container->getParameter('UKM_HOSTNAME') == 'ukm.dev') {
                $this->deltaLoginURL = 'http://delta.ukm.dev/app_dev.php/login';
            } 
            else {
                $this->deltaLoginURL = 'https://delta.ukm.no/login';
            }    
        }


        $this->get('logger')->debug('UKMDipBundle: dipURL = '.$this->dipURL);
        $this->get('logger')->debug('UKMDipBundle: deltaLoginURL = '.$this->deltaLoginURL);

        require_once('UKM/curl.class.php');
        // Dette er entry-funksjonen til DIP-innlogging.
        // Her sjekker vi om brukeren har en session med en autentisert token, 
        // og hvis ikke genererer vi en og sender brukeren videre til Delta.

        // Send request to Delta with token-info
        $location = $this->container->getParameter('ukm_dip.api_key');
        $firewall_name = $this->container->getParameter('ukm_dip.firewall_area');
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
                    if ($existingToken->getAuth() == true) {   
                        $this->get('logger')->debug('UKMDipBundle: Token is Authorized, logging in user.');

                        // Authorized, so trigger log in
                        $userId = $existingToken->getUserId();
                        
                        // Load user data?
                        $userProvider = $this->get('dipb_user_provider');
                        $this->get('logger')->debug('UKMDipBundle: UserProvider: '.get_class($userProvider));
                        $user = $userProvider->loadUserByUsername($userId);
                        $this->get('logger')->debug('UKMDipBundle: Loaded user: '.$user->getUsername());

                        #$token = new UsernamePasswordToken($user, $user->getPassword(), $firewall_name, $user->getRoles());
                        $token = new UsernamePasswordToken((string)$user->getUsername(), null, $firewall_name, $user->getRoles());

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
                        $session->save();
                        
                        $request = Request::CreateFromGlobals();
                        $event = new InteractiveLoginEvent($request, $token);
                        $this->get('logger')->info('UKMDipBundle: Dispatching interactive_login event.');
                        $this->get("event_dispatcher")->dispatch("security.interactive_login", $event);

                        // Har vi en referer-side?
                        $referer = $session->get('referer');
                        if($referer != null) {
                            $this->get('logger')->debug('UKMDipBundle: Referer: '.$referer);
                            return $this->redirect($referer);
                        }
                        // Hvis ikke, redirect til et gitt entry point i stedet
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
 
        // Do we have a referer-page?
        if( $this->container->hasParameter('ukm_dip.use_referer') ) {
            $this->get('logger')->debug('UKMDipBundle: The referer-setting is: '.$this->container->getParameter('ukm_dip.use_referer'));    
        } 
        // Do we have a referer-page?
        if( $this->container->hasParameter('ukm_dip.use_referer') && 'false' == $this->container->getParameter('ukm_dip.use_referer') ) {
            // Don't set referer
            $this->get('logger')->info('UKMDipBundle: Ignoring referer.');
        }
        else {
            $request = Request::CreateFromGlobals();
            $referer = $request->headers->get('referer'); 
            if(null != $referer) {
                $this->get('logger')->info('UKMDipBundle: Setting referer to '.$referer);
                $session->set('referer', $referer);
            }
        }
        
        // Generate token entity
        $token = new Token($this->container->getParameter('ukm_dip.token_salt'));
        $this->get('logger')->debug('UKMDipBundle: Created new token for user ('.$token->getToken().').');
        // Update session with token
        $session->set('token', $token->getToken());
        // Update database with the new token
        $em = $this->getDoctrine()->getManager();
        $em->persist($token);
        $em->flush();
        
        // Send token to Delta
        $params['api_key'] = $this->container->getParameter('ukm_dip.api_key');
        $params['token'] = $token->getToken();
        $signer = $this->get('UKM.urlsigner');
        $params['sign'] = $signer->getSignedURL('POST', $params);

        $this->get('logger')->debug('UKMDipBundle: POSTing token to Delta: '.var_export($params, true));
        $curl->post($params);
        $res = $curl->process($this->dipURL);
        // res should be a JSON-object.
        if(!is_object($res)) {
            $this->get('logger')->critical('UKMDipBundle: Delta returnerte ikke et objekt. Quitting.');
            throw new Exception('UKMDipBundle: Den felles innloggingsportalen til UKM er ikke tilgjengelig akkurat nå. Ta kontakt med UKM Support om problemet fortsetter.');
        }
        
        if($res->success) {
            $this->get('logger')->debug('UKMDipBundle: CURL-result: $res->success: '.$res->success);
            $this->get('logger')->debug('UKMDipBundle: CURL-result: $res->data: '.var_export($res->data, true));
        } else {
            $this->get('logger')->error('UKMDipBundle: Delta feilet i å lagre token.');
            $this->get('logger')->debug('UKMDipBundle: CURL-result: $res->data: '.var_export($res, true));
            throw new Exception('UKMDipBundle: innloggingsportalen svarte ikke - vennligst prøv igjen.');
        }
        // Redirect to Delta
        $this->get('logger')->debug('UKMDipBundle: Redirecting user to Delta.');
        $url = $this->deltaLoginURL.'?token='.$token->getToken().'&rdirurl='.$location;
        $url = $this->addScope($url);
        return $this->redirect($url);
    }

    /**
     * Legger til krav om scope til Delta.
     * Hvis scope følger innloggingsforespørselen, vil Delta først kreve at informasjonen vi ber om er lagt inn av brukeren,
     * og deretter sende den til oss på receive.
     *
     * @param $url - En fullverdig URL til delta-innloggingen.
     * @return $url - En URL inkl. scope.
     */
    public function addScope( $url ) {
        // Hvis vi vil be om mer informasjon fra Delta:
        if( $this->container->hasParameter('ukm_dip.scope') ) {
            if( strpos($url, '?') ) {
                $url = $url.'&scope=';
            }  
            else {
                $url = $url.'?scope=';
            }
            $url = $url . implode($this->container->getParameter('ukm_dip.scope'), ',');
        }  
        return $url;
    }

    public function receiveAction() {
        $this->get('logger')->debug('UKMDipBundle: receiveAction.');
        try {
            // Receives a JSON-object in a POST-request from Delta
            // This is all the user-data, plus a token

            $request = Request::CreateFromGlobals();
            $data = json_decode($request->request->get('json'));
            $this->get('logger')->debug('UKMDipBundle: Received data: '. var_export($data, true));

            $this->get('logger')->debug('UKMDipBundle: Token '.$data->token. ' received.');
            $this->get('logger')->debug('UKMDipBundle: User has delta_id '.$data->delta_id . '.');

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

            if(!$this->validateData($data)) {
                $msg = 'UKMDipBundle: Dataene som ble tatt i mot feilet validering.';
                $this->get('logger')->error($msg);
                die($msg);
            }

            // Find or update user
            $userClass = $this->container->getParameter('ukm_dip.user_class');
            $userRepo = $this->getDoctrine()->getRepository($userClass);
            
            $user = $userRepo->findOneBy(array('deltaId' => $data->delta_id));
            if (!$user) {
                // Hvis bruker ikke finnes.
                // TODO: Event dispatcher som kan nekte brukere inntil de er godkjente.
                // TODO: Hvordan funker dette med ekstern bruker-klasse????
                $this->get('logger')->debug('UKMDipBundle: Creating new user of class '.$userClass);
                #$user = new $userClass();
                $userManager = $this->get('fos_user.user_manager');
                $user = $userManager->createUser();
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

            return new JsonResponse(array('success' => true));
        }
        catch (Exception $e) {
            $errorMsg = 'UKMDipBundle: receiveAction - En feil har oppstått: '.$e->getMessage(). ' at line '.$e->getLine();
            $this->get('logger')->error($errorMsg);
            $this->get('logger')->error('Stacktrace: '.$e->getTraceAsString());
            return new JsonResponse(array('success' => false));
        }
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
