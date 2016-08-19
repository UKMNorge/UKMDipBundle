<?php

namespace UKMNorge\UKMDipBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class UKMDipBundle extends Bundle
{
	public function getParent()
    {
        return 'FOSUserBundle';
    }
}
