<?php

namespace UKMNorge\UKMDipBundle\Services;

class URLSignerService {


	public function __construct($container) {
		$this->container = $container;
	}
	/**
	 * $method = GET|POST
	 * $array = [key1 => val1, key2 => val2, ]
	**/
	public function getSignedUrl( $method, $array ) {
		ksort( $array );

		$concat = strtoupper( $method ).'?'.http_build_query( $array );
		$sign = md5( $this->getApiKey() . $concat . $this->getApiSecret() );

		#return $concat.'&sign='.$sign;
		return $sign;
	}

	public function getApiKey() {
		$this->container->getParameter('ukm_dip.api_key');
		return $key->getApiKey();
	}

	private function getApiSecret() {
		return $this->container->getParameter('ukm_dip.api_secret');
	}
}