<?php

namespace UKMNorge\UKMDipBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

// TODO: Gå over til SINGLE_TABLE_INHERITANCE - da kan man extende som forventet.
/**
 * User
 *
 * @ORM\MappedSuperclass
 */
class UserClass implements UserInterface
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
    private $id;

    /**
     * @var integer
     *
     * @ORM\Column(name="delta_id", type="integer")
     */
    private $deltaId;

    /**
     * @var string
     *
     * @ORM\Column(name="email", type="string", length=255)
     */
    private $email;

    /**
     * @var integer
     *
     * @ORM\Column(name="phone", type="integer", nullable=true, nullable=true)
     */
    private $phone;

    /**
     * @var string
     *
     * @ORM\Column(name="address", type="string", length=255, nullable=true)
     */
    private $address;

    /**
     * @var integer
     *
     * @ORM\Column(name="post_number", type="integer", nullable=true)
     */
    private $postNumber;

    /**
     * @var string
     *
     * @ORM\Column(name="post_place", type="string", length=255, nullable=true)
     */
    private $postPlace;

    /**
     * @var integer
     *
     * @ORM\Column(name="birthdate", type="integer", nullable=true)
     */
    private $birthdate = null;

    /**
     * @var string
     *
     * @ORM\Column(name="facebook_id", type="string", nullable=true)
     */
    protected $facebook_id;
    /**
     * @var string
     *
     * @ORM\Column(name="facebook_id_unencrypted", type="string", nullable=true)
     */
    protected $facebook_id_unencrypted;
    /** 
     *
     * @ORM\Column(name="facebook_access_token", type="string", length=255, nullable=true)
     */
    protected $facebook_access_token;

    /**
     *
     * @ORM\Column(name="first_name", type="string", length=255, nullable=true)
     *
     */
    protected $first_name;
    /**
     *
     * @ORM\Column(name="last_name", type="string", length=255, nullable=true)
     *
     */
    protected $last_name;

     /**
     *
     * @ORM\Column(name="gender", type="string", length=10, nullable=true)
     *
     */
    protected $gender;

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
     * Set email
     *
     * @param string $email
     * @return User
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get email
     *
     * @return string 
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Set phone
     *
     * @param integer $phone
     * @return User
     */
    public function setPhone($phone)
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * Get phone
     *
     * @return integer 
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * Set address
     *
     * @param string $address
     * @return User
     */
    public function setAddress($address)
    {
        $this->address = $address;

        return $this;
    }

    /**
     * Get address
     *
     * @return string 
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * Set postNumber
     *
     * @param integer $postNumber
     * @return User
     */
    public function setPostNumber($postNumber)
    {
        $this->postNumber = $postNumber;

        return $this;
    }

    /**
     * Get postNumber
     *
     * @return integer 
     */
    public function getPostNumber()
    {
        return $this->postNumber;
    }

    /**
     * Set postPlace
     *
     * @param string $postPlace
     * @return User
     */
    public function setPostPlace($postPlace)
    {
        $this->postPlace = $postPlace;

        return $this;
    }

    /**
     * Get postPlace
     *
     * @return string 
     */
    public function getPostPlace()
    {
        return $this->postPlace;
    }

    /**
     * Set birthdate
     *
     * @param integer $birthdate
     * @return User
     */
    public function setBirthdate($birthdate)
    {
        $this->birthdate = $birthdate;

        return $this;
    }

    /**
     * Get birthdate
     *
     * @return integer 
     */
    public function getBirthdate()
    {
        return $this->birthdate;
    }


    ### SECURITY-related methods!
    public function getRoles() {
        return array('ROLE_USER');
    }

    public function getPassword() {
        // We don't use the password-functionality
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

    /**
     * Set facebook_id
     *
     * @param string $facebookId
     * @return User
     */
    public function setFacebookId($facebookId)
    {
        $this->facebook_id = $facebookId;

        return $this;
    }

    /**
     * Get facebook_id
     *
     * @return string 
     */
    public function getFacebookId()
    {
        return $this->facebook_id;
    }

    /**
     * Set facebook_id_unencrypted
     *
     * @param string $facebookIdUnencrypted
     * @return User
     */
    public function setFacebookIdUnencrypted($facebookIdUnencrypted)
    {
        $this->facebook_id_unencrypted = $facebookIdUnencrypted;

        return $this;
    }

    /**
     * Get facebook_id_unencrypted
     *
     * @return string 
     */
    public function getFacebookIdUnencrypted()
    {
        return $this->facebook_id_unencrypted;
    }

    /**
     * Set facebook_access_token
     *
     * @param string $facebookAccessToken
     * @return User
     */
    public function setFacebookAccessToken($facebookAccessToken)
    {
        $this->facebook_access_token = $facebookAccessToken;

        return $this;
    }

    /**
     * Get facebook_access_token
     *
     * @return string 
     */
    public function getFacebookAccessToken()
    {
        return $this->facebook_access_token;
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

    // // Personal setters for Ambassador
    // /**
    //  * Get first_name
    //  *
    //  * @return string 
    //  */
    // public function getFirstname()
    // {
    //     return $this->first_name;
    // }/**
    //  * Get last_name
    //  *
    //  * @return string 
    //  */
    // public function getLastname()
    // {
    //     return $this->last_name;
    // }


    /**
     * Set gender
     *
     * @param string $gender
     * @return User
     */
    public function setGender($gender)
    {
        $this->gender = $gender;

        return $this;
    }

    /**
     * Get gender
     *
     * @return string 
     */
    public function getGender()
    {
        return $this->gender;
    }
}