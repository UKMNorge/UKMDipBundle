<?php 

namespace UKMNorge\UKMDipBundle\Utils;

use Exception;

class UserClassFactory {

    public static function getUserClass() {
        $param = "fos_user.user_class";
        $class = $this->container->getParameter($param);
        // Check its existence
        if(class_exists($class)) {
            // return a new Writer object
            return new $class();
        }
        // otherwise we fail
        throw new Exception('UKMDipBundle: UserClassFactory->getUserClass(): The class '.$class.', defined in '.$param.' in app/config/config.yml does not exist!');
    }
}