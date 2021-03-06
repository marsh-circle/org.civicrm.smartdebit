<?php

class CRM_Smartdebit_Api {

  /**
   * Return API URL with base prepended
   * @param array $processorDetails Array of processor details from CRM_Core_Payment_Smartdebit::getProcessorDetails()
   * @param string $path
   * @param string $request
   * @return string
   */
  public static function buildUrl($processorDetails, $path = '', $request = '') {
    if (empty($processorDetails['url_api'])) {
      throw new Exception('Missing API URL in payment processor configuration!');
    }
    $baseUrl = $processorDetails['url_api'];

    // Smartdebit API is picky about double // in URL path so make sure we remove it
    if (substr($baseUrl, -1) != '/') {
      $baseUrl .= '/';
    }
    if (substr($path,0,1) == '/') {
      $path = substr($path,1);
    }

    $url = $baseUrl . $path;
    if (!empty($request)) {
      if ($request[0] != '?') {
        $request = '?' . $request;
      }
      $url .= $request;
    }
    return $url;
  }

  /**
   *   Send a post request with cURL
   *
   * @param string $url URL to send request to
   * @param array $data POST data to send
   * @param string $username
   * @param string $password
   * @return array
   */
  public static function requestPost($url, $data, $username, $password){
    // Prepare data
    $data = self::encodePostParams($data);

    // Set a one-minute timeout for this script
    set_time_limit(60);

    $options = array(
      CURLOPT_RETURNTRANSFER => true, // return web page
      CURLOPT_HEADER => false, // don't return headers
      CURLOPT_POST => true,
      CURLOPT_USERPWD => $username . ':' . $password,
      CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
      CURLOPT_HTTPHEADER => array("Accept: application/xml"),
      CURLOPT_USERAGENT => "CiviCRM PHP DD Client", // Let Smartdebit see who we are
      CURLOPT_SSL_VERIFYHOST => 2,
      CURLOPT_SSL_VERIFYPEER => 1,
    );

    $session = curl_init($url);
    curl_setopt_array($session, $options);

    // Tell curl that this is the body of the POST
    curl_setopt($session, CURLOPT_POSTFIELDS, $data);

    // $output contains the output string
    $output = curl_exec($session);
    $header = curl_getinfo($session);

    //Store the raw response for later as it's useful to see for integration and understanding
    $_SESSION["rawresponse"] = $output;

    // Set return values
    if (isset($header['http_code'])) {
      $resultsArray['statuscode'] = $header['http_code'];
    }
    else {
      $resultsArray['statuscode'] = -1;
    }

    if(curl_errno($session)) {
      $resultsArray['success'] = FALSE;
      $resultsArray['message'] = 'cURL Error';
      $resultsArray['error'] = curl_error($session);
    }
    else {
      // Results are XML so turn this into a PHP Array (simplexml_load_string returns an object)
      $resultsArray = json_decode(json_encode((array) simplexml_load_string($output)),1);
      if (!isset($resultsArray['error'])) {
        $resultsArray['error'] = NULL;
      }

      // Determine if the call failed or not
      $resultsArray['statuscode'] = $header['http_code'];
      switch ($header['http_code']) {
        case 200:
          $resultsArray['message'] = 'OK';
          $resultsArray['success'] = TRUE;
          break;
        case 400:
          $resultsArray['message'] = 'BAD REQUEST';
          $resultsArray['success'] = FALSE;
          break;
        case 401:
          $resultsArray['message'] = 'UNAUTHORIZED';
          $resultsArray['success'] = FALSE;
          break;
        case 404:
          $resultsArray['message'] = 'NOT FOUND';
          $resultsArray['success'] = FALSE;
          break;
        case 422:
          $resultsArray['message'] = 'UNPROCESSABLE ENTITY';
          $resultsArray['success'] = FALSE;
          break;
        default:
          $resultsArray['message'] = 'Unknown Error';
          $resultsArray['success'] = FALSE;
      }
    }
    // Return the output
    return $resultsArray;
  }

  public static function reportError($response) {
    if (isset($response['head']['title'])) {
      $msg = $response['head']['title'];
    }
    else {
      $msg = $response['statuscode'] . ': ' . $response['message'];
    }
    CRM_Core_Session::setStatus($msg, 'Smart Debit', 'error');
    Civi::log()->error('Smart Debit: ' . $msg);
  }

  /**
   * Format response error for display to user
   *
   * @param array $responseErrors Array or string of errors
   * @return string
   */
  static function formatResponseError($responseErrors)
  {
    if (!$responseErrors) {
      return NULL;
    }

    $message = NULL;
    if (!is_array($responseErrors)) {
      $message = $responseErrors . '.';
    }
    else {
      foreach ($responseErrors as $error) {
        $message .= $error . '. ';
      }
    }
    return $message;
  }

  /**
   * Retrieve Audit Log from Smartdebit
   * Called during daily sync job
   * @param null $referenceNumber
   * @return array|bool
   */
  static function getSystemStatus($test = FALSE)
  {
    $userDetails = CRM_Core_Payment_Smartdebit::getProcessorDetails(NULL, $test);
    $username = CRM_Utils_Array::value('user_name', $userDetails);
    $password = CRM_Utils_Array::value('password', $userDetails);

    // Send payment POST to the target URL
    $url = CRM_Smartdebit_Api::buildUrl($userDetails, 'api/system_status');

    $response = CRM_Smartdebit_Api::requestPost($url, NULL, $username, $password);

    /* Expected Response:
    Array (
      [api_version] => 1.1
      [user] => Array (
        [login] => testuserapitest
        [assigned_service_users] => Array (
          [service_user] => Array (
            [pslid] => testusertest
          )
    OR
            0 => Array (1)
              pslid => "testusertest"
            1 => Array (1)
              pslid => "otherusertest"
            )
      )
      [Status] => OK
    )
    */
    // As we're just displaying this onscreen, convert pslid array to a string and return it
    if (isset($response['user']['assigned_service_users']['service_user'][0]['pslid'])) {
      $pslIds = '';
      foreach ($response['user']['assigned_service_users']['service_user'] as $key => $value) {
        $pslIds .= $value['pslid'] . '; ';
      }
      $response['user']['assigned_service_users']['service_user'] = array('pslid' => $pslIds);
    }
    return $response;
  }

  /**
   * Retrieve Audit Log from Smartdebit
   * Called during daily sync job
   * @param null $referenceNumber
   * @return array|bool
   */
  static function getAuditLog($referenceNumber = NULL) {
    $userDetails = CRM_Core_Payment_Smartdebit::getProcessorDetails();
    $username = CRM_Utils_Array::value('user_name', $userDetails);
    $password = CRM_Utils_Array::value('password', $userDetails);
    $pslid = CRM_Utils_Array::value('signature', $userDetails);

    // Send payment POST to the target URL
    $url = CRM_Smartdebit_Api::buildUrl($userDetails, '/api/data/auditlog', "query[service_user][pslid]="
      . urlencode($pslid) . "&query[report_format]=XML");

    // Restrict to a single payer if we have a reference
    if ($referenceNumber) {
      $url .= "&query[reference_number]=" . urlencode($referenceNumber);
    }
    $response = CRM_Smartdebit_Api::requestPost($url, NULL, $username, $password);

    // Take action based upon the response status
    if ($response['success']) {
      $smartDebitArray = array();
      if (isset($response['Data']['AuditDetails']['@attributes'])) {
        // Cater for a single response
        $smartDebitArray[] = $response['Data']['AuditDetails']['@attributes'];
      }
      else {
        // Multiple records
        foreach ($response['Data']['AuditDetails'] as $key => $value) {
          $smartDebitArray[] = $value['@attributes'];
        }
      }
      return $smartDebitArray;
    }
    else {
      self::reportError($response);
      return false;
    }
  }

  /**
   * Retrieve Payer Contact Details from Smartdebit
   * Called during daily sync job
   * @param null $referenceNumber
   * @return array|bool
   */
  static function getPayerContactDetails($referenceNumber = NULL)
  {
    $userDetails = CRM_Core_Payment_Smartdebit::getProcessorDetails();
    $username = CRM_Utils_Array::value('user_name', $userDetails);
    $password = CRM_Utils_Array::value('password', $userDetails);
    $pslid = CRM_Utils_Array::value('signature', $userDetails);

    // Send payment POST to the target URL
    $url = CRM_Smartdebit_Api::buildUrl($userDetails, '/api/data/dump', "query[service_user][pslid]="
      .urlencode($pslid)."&query[report_format]=XML");

    // Restrict to a single payer if we have a reference
    if ($referenceNumber) {
      $url .= "&query[reference_number]=".urlencode($referenceNumber);
    }
    $response = CRM_Smartdebit_Api::requestPost($url, NULL, $username, $password);

    // Take action based upon the response status
    if ($response['success']) {
      $smartDebitArray = array();
      // Cater for a single response
      if (isset($response['Data']['PayerDetails']['@attributes'])) {
        $smartDebitArray[] = $response['Data']['PayerDetails']['@attributes'];
      }
      else {
        foreach ($response['Data']['PayerDetails'] as $key => $value) {
          $smartDebitArray[] = $value['@attributes'];
        }
      }
      return $smartDebitArray;
    }
    else {
      self::reportError($response);
      return false;
    }
  }

  /**
   * Retrieve Collection Report from Smart Debit
   * @param $dateOfCollection
   * @return array|bool
   */
  static function getCollectionReport( $dateOfCollection ) {
    if( empty($dateOfCollection)){
      CRM_Core_Session::setStatus(ts('Please select the collection date'), ts('Smart Debit'), 'error');
      return FALSE;
    }

    $userDetails = CRM_Core_Payment_Smartdebit::getProcessorDetails();
    $username    = CRM_Utils_Array::value('user_name', $userDetails);
    $password    = CRM_Utils_Array::value('password', $userDetails);
    $pslid       = CRM_Utils_Array::value('signature', $userDetails);

    $collections = array();
    $url = CRM_Smartdebit_Api::buildUrl($userDetails, '/api/get_successful_collection_report', "query[service_user][pslid]=$pslid&query[collection_date]=$dateOfCollection");
    $response    = CRM_Smartdebit_Api::requestPost($url, NULL, $username, $password);

    // Take action based upon the response status
    if ($response['success']) {
      if (!isset($response['Successes']['Success']) || !isset($response['Rejects'])) {
        $collections['error'] = $response['Summary'];
        return $collections;
      }
      // Cater for a single response
      if (isset($response['Successes']['Success']['@attributes'])) {
        $collections[] = $response['Successes']['Success']['@attributes'];
      }
      else {
        foreach ($response['Successes']['Success'] as $key => $value) {
          $collections[] = $value['@attributes'];
        }
      }
      return $collections;
    }
    else {
      $url = CRM_Utils_System::url('civicrm/smartdebit/syncsd', 'reset=1'); // DataSource Form
      self::reportError($response);
      CRM_Utils_System::redirect($url);
    }
    return FALSE;
  }

  /**
   * Get AUDDIS file from Smart Debit. $uri is retrieved using getAuddisList
   * @param null $uri
   * @return array|bool
   */
  static function getAuddisFile($fileId)
  {
    $userDetails = CRM_Core_Payment_Smartdebit::getProcessorDetails();
    $username = CRM_Utils_Array::value('user_name', $userDetails);
    $password = CRM_Utils_Array::value('password', $userDetails);
    $pslid = CRM_Utils_Array::value('signature', $userDetails);

    if (empty($fileId)) {
      CRM_Core_Error::debug_log_message('Smartdebit getSmartdebitAuddisFile: Must specify file ID!');
      return FALSE;
    }
    $url = CRM_Smartdebit_Api::buildUrl($userDetails, "/api/auddis/$fileId",
      "query[service_user][pslid]=$pslid");
    $responseAuddis = CRM_Smartdebit_Api::requestPost($url, NULL, $username, $password);
    $scrambled = str_replace(" ", "+", $responseAuddis['file']);
    $outputafterencode = base64_decode($scrambled);
    $auddisArray = json_decode(json_encode((array)simplexml_load_string($outputafterencode)), 1);
    $result = array();

    if ($auddisArray['Data']['MessagingAdvices']['MessagingAdvice']['@attributes']) {
      $result[0] = $auddisArray['Data']['MessagingAdvices']['MessagingAdvice']['@attributes'];
    } else {
      foreach ($auddisArray['Data']['MessagingAdvices']['MessagingAdvice'] as $key => $value) {
        $result[$key] = $value['@attributes'];
      }
    }
    if (isset($auddisArray['Data']['MessagingAdvices']['Header']['@attributes']['report-generation-date'])) {
      $result['auddis_date'] = $auddisArray['Data']['MessagingAdvices']['Header']['@attributes']['report-generation-date'];
    }
    return $result;
  }

  /**
   * Get ARUDD file from Smart Debit. $uri is retrieved using getAruddList
   * @param null $uri
   * @return array|bool
   */
  static function getAruddFile($fileId)
  {
    $userDetails = CRM_Core_Payment_Smartdebit::getProcessorDetails();
    $username = CRM_Utils_Array::value('user_name', $userDetails);
    $password = CRM_Utils_Array::value('password', $userDetails);
    $pslid = CRM_Utils_Array::value('signature', $userDetails);

    if (empty($fileId)) {
      CRM_Core_Error::debug_log_message('Smartdebit getSmartdebitAruddFile: Must specify file ID!');
      return FALSE;
    }

    $url = CRM_Smartdebit_Api::buildUrl($userDetails, "/api/arudd/$fileId",
      "query[service_user][pslid]=$pslid");
    $responseArudd = CRM_Smartdebit_Api::requestPost($url, NULL, $username, $password);
    $scrambled = str_replace(" ", "+", $responseArudd['file']);
    $outputafterencode = base64_decode($scrambled);
    $aruddArray = json_decode(json_encode((array)simplexml_load_string($outputafterencode)), 1);
    $result = array();

    if (isset($aruddArray['Data']['ARUDD']['Advice']['OriginatingAccountRecords']['OriginatingAccountRecord']['ReturnedDebitItem']['@attributes'])) {
      // Got a single result
      // FIXME: Check that this is correct (ie. results not in array at ReturnedDebitItem if single
      $result[0] = $aruddArray['Data']['ARUDD']['Advice']['OriginatingAccountRecords']['OriginatingAccountRecord']['ReturnedDebitItem']['@attributes'];
    } else {
      foreach ($aruddArray['Data']['ARUDD']['Advice']['OriginatingAccountRecords']['OriginatingAccountRecord']['ReturnedDebitItem'] as $key => $value) {
        $result[$key] = $value['@attributes'];
      }
    }
    $result['arudd_date'] = $aruddArray['Data']['ARUDD']['Header']['@attributes']['currentProcessingDate'];
    return $result;
  }

  /**
   * Encode POST params HTTP POST to Smartdebit
   * @param $params
   *
   * @return null|string
   */
  static function encodePostParams($params) {
    $post = NULL;
    foreach ($params as $key => $value) {
      if (!empty($value)) {
        if (!empty($post)) {
          $post .= '&';
        }
        $post .= $key . '=' . urlencode($value);
      }
    }
    return $post;
  }

  /**
   * Smartdebit API requires amount in pence.
   * @param $amount
   *
   * @return int|string
   */
  static function encodeAmount($amount) {
    if (empty($amount)) {
      return 0;
    }
    else {
      // Set amount in pence if not already set that way.
      // Format amount
      $amount = number_format($amount, 2, '.', '');
      $amount = (int) preg_replace('/[^\d]/', '', strval($amount));
      return $amount;
    }
  }

}