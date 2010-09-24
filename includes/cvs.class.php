<?php

//$Id$

/**
 * GenericCVSClient is a Generic CVS Client.
 *
 * This class provides functionality for connecting to a CVS PServer using PHP
 * Code :)
 *
 */
class GenericCVSClient {

  /**
   * Stores socket connection to CVS Server.
   * 
   * @var
   *  Resource of socket connected to CVS Server.
   */
  protected $link;
  /**
   *
   * @var
   *  Url string to connect.
   */
  protected $url;

  /**
   * Enables logging if set to TRUE.
   * 
   * @see Line#192
   *
   * @var
   *   Set this to TRUE if you want to see what CVS.Class is sending to PServer.
   *   you will also have to enable Line#190-191 for showing.
   */
//  protected $log;
//@wiki this feature is not mature enough.

  /**
   * constructor for CVS class.
   *
   *   Since CVS socket communication can be more that few seconds (if you are
   * downloading a 2MB file from server ;) like views on Drupal Contrib Modules),
   * we need to run script till it lasts and not by a time_limit. Some servers
   * don't allow to set these, so please verify it first.
   * 
   */
  function __construct() {
    set_time_limit(0);
  }

  /**
   * connect opens socket connection to CVS PServer.
   *
   *  Please check your server for outbound socket connection. 
   */
  protected function connect() {
    $errorno = null;
    $errorstr = null;
    if (is_array(parse_url($this->url))) {
      $this->link = fsockopen(parse_url($this->url, PHP_URL_HOST),
                      parse_url($this->url, PHP_URL_PORT),
                      $errorno,
                      $errorstr
      );

      if (!$this->link) {
        throw new Exception($errorstr, $errorno);
      }
    }
  }

  /**
   * closes connection.
   */
  protected function close() {
    fclose($this->link);
  }

  /**
   * authenticate authenticates user to CVS PServer. username and Password is
   * parsed from url string.
   *
   * @example
   *   pserver://anonymous:anonymous@cvs.drupal.org:2401/cvs/drupal-contrib
   *   first anonymous is username and second anonymous is password.
   */
  protected function authenticate() {
    $this->writeStream("BEGIN AUTH REQUEST");
    $this->writeStream(parse_url($this->url, PHP_URL_PATH));
    $this->writeStream(parse_url($this->url, PHP_URL_USER));
    $this->writeStream($this->_encrypt(parse_url($this->url, PHP_URL_PASS)));
    $this->writeStream("END AUTH REQUEST");
    $authenticated = $this->readStreamLine();
    if ($authenticated != "I LOVE YOU") {
      if ($authenticated == "I HATE YOU") {
        throw new Exception("Authentication Faliure.", 10081);
      } else {
        throw new Exception("Unknown Authentication Faliure.", 10082);
      }
    }
    $this->writeStream("Root " . parse_url($this->url, PHP_URL_PATH));
    $this->writeStream("gzip-file-contents 6");
  }

  /**
   * Setter function for CVS PServer url.
   * 
   * @param $url
   *    CVS connection URL
   *    eg. pserver://anonymous:anonymous@cvs.drupal.org:2401/cvs/drupal-contrib
   */
  protected function setUrl($url) {
    $this->url = $url;
  }

  /**
   * Getter function for CVS PServer url.
   * 
   * @return
   *     CVS PServer connection url.
   */
  protected function getUrl() {
    return $this->url;
  }

  /**
   * Reads whole response from server.
   * 
   * @return
   *    full response from CVS PServer.
   */
  protected function readAll() {
    $buffer = "";
    while ($r = $this->readStream(4096)) {
      $buffer .= $r;
    }
    return $buffer;
  }

  /**
   * fetches specified size of response from server.
   * 
   * @param $size
   *    The number of bytes needed to be fetched from server.
   *
   * @return
   *    response read from server.
   */
  protected function readStream($size = 4096) {
    return fread($this->link, $size);
  }

  /**
   * reads a line upto delimiter or size, whichever comes first.
   *
   * @param $size
   *    The number of bytes needed to be fetched from server.
   * 
   * @param $delimiter
   *    An optional string delimiter.
   * @return
   *    response read from server.
   */
  protected function readStreamLine($size=512, $delimiter="\n") {
    return stream_get_line($this->link, $size, "\n");
    //@wiki stream_get_line is slow on getting line. I don't know why some say
    //      fread is better in reading than stream_get_line so trying this code
    //      that I got on PHP.net site :)
//    $current_cursor = ftell($this->link);
//    $content = fread($this->link, $size);
//    $position = strpos($content, $delimiter);
//    if ($position === FALSE) {
//        return $content;
//    } else {
//        fseek($fp, $current_cursor + $position + strlen($delimiter));
//        return substr($content, 0, $position);
//    }
//@wiki this code is broken :( response is not what i am expecting from this.
//      stream_get_line does my job. So, I am going to stick on that. May be
//      optimise this later ;)
  }

  /**
   * sends request to server.
   *
   * @param $string
   *    Request that needs to be sent to server. "\n" is already added to
   *    request before sending (each command to PServer is delimited by a \n,
   *    and \n\n can result in unknown command as second \n doesn't have a
   *    request, so please don't add a \n to this parameter.
   */
  protected function writeStream($string) {
    if ($this->link) {
//      if ($this->log)
//        echo $string . "\n";
//@wiki enable previous code and set $this->log = true if you want to debug
//      what cvs is sending.
      fwrite($this->link, $string . "\n");
    } else {
      throw new Exception("Not connected to CVS Server!");
    }
  }

  /**
   * _encrypt helper function.
   *
   * encrypts password.
   *
   * @see http://www.wandisco.com/techpubs/cvs-protocol.pdf
   *
   * @param $password
   *    password needed to be encoded.
   * @return
   *    encrypted password.
   */
  private function _encrypt($password) {
    $codes = array(
        0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19,
        20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 114, 120, 53, 79, 96,
        109, 72, 108, 70, 64, 76, 67, 116, 74, 68, 87, 111, 52, 75, 119, 49,
        34, 82, 81, 95, 65, 112, 86, 118, 110, 122, 105, 41, 57, 83, 43, 46,
        102, 40, 89, 38, 103, 45, 50, 42, 123, 91, 35, 125, 55, 54, 66, 124,
        126, 59, 47, 92, 71, 115, 78, 88, 107, 106, 56, 36, 121, 117, 104, 101,
        100, 69, 73, 99, 63, 94, 93, 39, 37, 61, 48, 58, 113, 32, 90, 44, 98,
        60, 51, 33, 97, 62, 77, 84, 80, 85, 223, 225, 216, 187, 166, 229, 189,
        222, 188, 141, 249, 148, 200, 184, 136, 248, 190, 199, 170, 181, 204,
        138, 232, 218, 183, 255, 234, 220, 247, 213, 203, 226, 193, 174, 172,
        228, 252, 217, 201, 131, 230, 197, 211, 145, 238, 161, 179, 160, 212,
        207, 221, 254, 173, 202, 146, 224, 151, 140, 196, 205, 130, 135, 133,
        143, 246, 192, 159, 244, 239, 185, 168, 215, 144, 139, 165, 180, 157,
        147, 186, 214, 176, 227, 231, 219, 169, 175, 156, 206, 198, 129, 164,
        150, 210, 154, 177, 134, 127, 182, 128, 158, 208, 162, 132, 167, 209,
        149, 241, 153, 251, 237, 236, 171, 195, 243, 233, 253, 240, 194, 250,
        191, 155, 142, 137, 245, 235, 163, 242, 178, 152
    );

    $return = "A";
    for ($i = 0; $i < strlen($password); $i++) {
      $return .= chr($codes[ord($password[$i])]);
    }

    return $return;
  }

}