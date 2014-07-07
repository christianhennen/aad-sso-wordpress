<?php

// A class that provides authorization token for apps that need to access Azure Active Directory Graph Service.
class AADSSO_AuthorizationHelper
{
    // Get the authorization URL which makes the authorization request
    public static function getAuthorizationURL($settings, $antiforgery_id) {
        $authUrl = $settings->authorizationEndpoint .
                   http_build_query(
                        array(
                            'response_type' => 'code',
                            'domain_hint' => $settings->org_domain_hint,
                            'client_id' => $settings->client_id,
                            'resource' => $settings->resourceURI,
                            'redirect_uri' => $settings->redirect_uri,
                            'state' => $antiforgery_id
                        )
                   );
        return $authUrl;
    }

    // Takes an authorization code and obtains an gets an access token
    public static function getAccessToken($code, $settings) {

        // Construct the body for the access token request
        $authenticationRequestBody = http_build_query(
                                        array(
                                            'grant_type' => 'authorization_code',
                                            'code' => $code,
                                            'redirect_uri' => $settings->redirect_uri,
                                            'resource' => $settings->resourceURI,
                                            'client_id' => $settings->client_id,
                                            'client_secret' => $settings->client_secret
                                        )
                                    );

        return self::getAndProcessToken($authenticationRequestBody, $settings);
    }

    // Takes an authorization code and obtains an gets an access token as what AAD calls a "native app"
    public static function getAccessTokenAsNativeApp($code, $settings) {

        // Construct the body for the access token request
        $authenticationRequestBody = http_build_query(
                                        array(
                                            'grant_type' => 'authorization_code',
                                            'code' => $code,
                                            'redirect_uri' => $settings->redirect_uri,
                                            'resource' => $settings->resourceURI,
                                            'client_id' => $settings->client_id
                                        )
                                    );

        return getAndProcessToken($authenticationRequestBody, $settings);
    }

    // Does the request for the access token and some basic processing of the access and JWT tokens
    static function getAndProcessToken($authenticationRequestBody, $settings) {

        // Using curl to post the information to STS and get back the authentication response    
        $ch = curl_init();
        //curl_setopt($ch, CURLOPT_PROXY, '127.0.0.1:8888');
        curl_setopt($ch, CURLOPT_URL, $settings->tokenEndpoint); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);            // Get the response back as a string 
        curl_setopt($ch, CURLOPT_POST, 1);                      // Mark as POST request
        curl_setopt($ch, CURLOPT_POSTFIELDS,  $authenticationRequestBody);  // Set the parameters for the request
        //curl_setopt($ch, CURLOPT_PROXY, '127.0.0.1:8888');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);        // By default, HTTPS does not work with curl.
        $output = curl_exec($ch);                               // Read the output from the post request
        curl_close($ch);                                        // close cURL resource to free up system resources

        // Decode the JSON response from the STS. If all went well, this will contain the access token and the
        // id_token (a JWT token telling us about the current user)s
        $token = json_decode($output);

        if ( isset($token->access_token) ) {

            // Per section 7 of the current JWT draft [1], and section 5.2 of JWS draft [2],
            // a JWT with 'alg' == 'none' has no signature to validate, and is therefore valid unless
            // something else is wrong with it. The JWT class we're using explicitly checks for 3 segments
            // in the token (making it a JWS per section 9 of current JWE draft [3]).
            //  [1] http://tools.ietf.org/html/draft-ietf-oauth-json-web-token-25#section-7
            //  [2] http://tools.ietf.org/html/draft-ietf-jose-json-web-signature-31#section-5.2
            //  [3] http://tools.ietf.org/html/draft-ietf-jose-json-web-encryption-31#section-9
            $jwt = JWT::decode( $token->id_token );

            // Add the token information to the session so that we can use it later
            // TODO: these probably shouldn't be in SESSION... 
            $_SESSION['token_type'] = $token->token_type;
            $_SESSION['access_token'] = $token->access_token;
            $_SESSION['jwt'] = $jwt;        
        }

        return $token;
    }
}