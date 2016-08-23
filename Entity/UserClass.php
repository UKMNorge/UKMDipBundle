<?php

namespace UKMNorge\UKMDipBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use FOS\UserBundle\Model\User as BaseUser;

// TODO: Gå over til SINGLE_TABLE_INHERITANCE - da kan man extende som forventet?
/**
 * User
 *
 * @ORM\MappedSuperclass
 */
class UserClass extends BaseUser implements UserInterface
{   
    // We don't use the password-functionality, but it needs to be implemented
    // so that Symfony will treat us like a proper user.
    protected $salt = 'saaaaalt';
    protected $password = 'dud';

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var integer
     *
     * @ORM\Column(name="delta_id", type="integer", unique=true)
     */
    protected $deltaId;

    /**
     *
     * @ORM\Column(name="first_name", type="string", length=255, nullable=true)
     *
     */
    protected $firstName;
    /**
     *
     * @ORM\Column(name="last_name", type="string", length=255, nullable=true)
     *
     */
    protected $lastName;

    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set deltaId
     *
     * @param integer $deltaId
     * @return User
     */
    public function setDeltaId($deltaId)
    {
        $this->deltaId = $deltaId;
        // FOSUserBundle-compatibility
        $this->setUsername($deltaId);

        return $this;
    }

    /**
     * Get deltaId
     *
     * @return integer 
     */
    public function getDeltaId()
    {
        return $this->deltaId;
    }

    /**
     * Set first_name
     *
     * @param string $firstName
     * @return User
     */
    public function setFirstName($firstName)
    {
        $this->first_name = $firstName;

        return $this;
    }

    /**
     * Get first_name
     *
     * @return string 
     */
    public function getFirstName()
    {
        return $this->first_name;
    }

    /**
     * Set last_name
     *
     * @param string $lastName
     * @return User
     */
    public function setLastName($lastName)
    {
        $this->last_name = $lastName;

        return $this;
    }

    /**
     * Get last_name
     *
     * @return string 
     */
    public function getLastName()
    {
        return $this->last_name;
    }

    public function getName() 
    {
        return $this->getFirstName() . ' ' . $this->getLastName();
    }

    public function setData($data) {
        // Implement all setters from DIP
    }


    ### SECURITY-related methods!
    // Implemented by FOSUserBundle?
    /*public function getRoles() {
        return array('ROLE_USER');
    }*/

    public function getPassword() {
        // We don't use the password-functionality, so this doesn't have to be safe.
        return hash('sha256', $this->password.$this->salt);
    }

    public function getSalt() {
        return $this->salt;
    }

    public function getUsername() {
        return $this->deltaId;
    }
    public function eraseCredentials() {
        // Not necessary to do anything.
    }


    public function serialize()
    {
        return serialize(array(
            $this->id,
            $this->username,
            $this->password,
            // see section on salt below
            // $this->salt,
        ));
    }

    public function unserialize($serialized)
    {
        list (
            $this->id,
            $this->username,
            $this->password,
            // see section on salt below
            // $this->salt
        ) = unserialize($serialized);
    }
}