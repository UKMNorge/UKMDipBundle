<?php

namespace UKMNorge\UKMDipBundle\Security\Provider;

use UKMNorge\UKMDipBundle\Entity\User;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;

class DipBUserProvider implements UserProviderInterface
{
	public function __construct($doctrine, $container) {
		$this->doctrine = $doctrine;
		$this->container = $container;
		$this->logger = $container->get('logger');
	}
	
	public function loadUserByUsername($username) {
		// $username = delta_id
		#$userClass = $this->container->getParameter('fos_user.user_class');
		$userClass = $this->container->getParameter('ukm_dip.user_class');
		
		#$userRepo = $this->doctrine->getRepository('UKMDipBundle:User');
		$userRepo = $this->doctrine->getRepository($userClass);
		$user = $userRepo->findOneBy(array('deltaId' => $username));
		
		if (!$user) {
			$this->logger->info('UKMDipBundle: Ingen bruker funnet med deltaId '.$username);
			throw new UsernameNotFoundException(
				sprintf('User with DeltaID "%s" does not exist.', $username)
        	);
		}
		#$user = new User();
		return $user;
	}

	public function refreshUser(UserInterface $user) {
		#if (!$user instanceof User) {
		$userClass = $this->container->getParameter('ukm_dip.user_class');
		if (!$user instanceof $userClass) {
            throw new UnsupportedUserException(
                sprintf('Instances of "%s" are not supported.', get_class($user))
            );
        }
        return $this->loadUserByUsername($user->getUsername());
	}

	public function supportsClass($class) {
		//return $class === 'UKMNorge\DipBundle\Entity\User';
		$userClass = $this->container->getParameter('ukm_dip.user_class');
		return $class === $userClass;
	}
}