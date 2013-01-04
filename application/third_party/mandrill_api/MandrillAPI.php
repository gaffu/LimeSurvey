<?php

/**
 * Class for handling Mandrill API calls
 * Copyright (C) 2012 Jeppe Poss
 * All rights reserved.
 */
class MandrillAPI {
    
    private $baseURL;
    
    /**
     * 
     * @param string $baseURL the base url e.g. https://mandrillapp.com/api/1.0
     */
    function __construct($baseURL) {
       $this->baseURL = $baseURL;
   }

    /**
     * Method that calls the Mandrill api
     * @param string $url string containing relativ (method) url
     * @param array $data array containing all the information
     * @return string respons from mandrill
     */
    function callAPI($url, $data) {
        // Check if curl is installed, if not throw exception
        if (!function_exists('curl_version') == 'Enabled' ){
            throw new Exception('cURL is required to send emails through Mandrill, but cURL is not installed!');
        }
        
        $url = $this->baseURL . $url;
        $data = json_encode($data);

        $curl = curl_init();
        
        // All api calls should be post
        curl_setopt($curl, CURLOPT_POST, 1);
        if ($data){
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        return curl_exec($curl);
    }

    /**
     * Method for sending an email through Mandrill
     * @param array $data containing data to be send
     * @param String $baseURL the base url to which you want to communicate with mandrill
     * @return string respons from mandrill
     */
    function send($data) {
        $methodUrl = '/messages/send.json';
        return $this->callAPI($methodUrl, $data);
    }
    
    /**
     * Get info about the current connected API user
     * @param array $data array containing the api key
     * @return string respons from mandrill
     */
    function getInfo($data) {
        $methodUrl = '/users/info.json';
        return $this->callAPI($methodUrl, $data);
    }
}

?>