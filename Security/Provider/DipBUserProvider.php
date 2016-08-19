<?php

namespace UKMNorge\DipBundle\Security\Provider;

use UKMNorge\DipBundle\Entity\User;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;

class DipBUserProvider implements UserProviderInterface
{
	public function __construct($doctrine) {
		$this->doctrine = $doctrine;
	}
	public function loadUserByUsername($username) {
		// $username = delta_id
		$userRepo = $this->doctrine->getRepository('UKMDipBundle:User');
		$user = $userRepo->findOneBy(array('deltaId' => $username));
		if (!$user) {
			throw new UsernameNotFoundException(
				sprintf('UserID "%s" does not exist.', $username)
        	);
		}
		#$user = new User();
		return $user;
	}

	public function refreshUser(UserInterface $user) {
		if (!$user instanceof User) {
            throw new UnsupportedUserException(
                sprintf('Instances of "%s" are not supported.', get_class($user))
            );
        }
        return $this->loadUserByUsername($user->getUsername());
	}

	public function supportsClass($class) {
		return $class === 'UKMNorge\DipBundle\Entity\User';
	}
}