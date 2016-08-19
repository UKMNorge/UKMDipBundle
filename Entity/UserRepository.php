<?php

namespace UKMNorge\DipBundle\Entity;

use Doctrine\ORM\EntityRepository;
use UKMNorge\DipBundle\Security\Provider;

/**
 * UserRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class UserRepository extends EntityRepository 
{

	public function loadUserByUsername($username) {
		$userProvider = $this->get('dipb_user_provider');
		return $userProvider->loadUserByUsername($username);
	}

	public function refreshUser($user) {
		$userProvider = $this->get('dipb_user_provider');
		return $userProvider->refreshUser($user);
	}
}