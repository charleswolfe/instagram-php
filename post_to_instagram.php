<?php

class InstagramUpload {
    protected $username;
    protected $password;

    function __construct($username, $password) {
      $this->username = $username;
      $this->password = $password;
      $this->auth();
    }

    private function send_request($url, $post, $post_data, $user_agent, $cookies) {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, 'https://i.instagram.com/api/v1/'.$url);
      curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      if ($post) {
          curl_setopt($ch, CURLOPT_POST, true);
          curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
      }
      if ($cookies) {
          curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
      } else {
          curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
      }
      $response = curl_exec($ch);
      $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      var_dump($response);
      curl_close($ch);

      if ($http != 200) {
        throw new Exception("Bad return [{$http}]");
      }
      if (empty($response)) {
         throw new Exception("Empty response received from the server while trying to configure the image");
      }
      if (strpos($response, "login_required")) {
          throw new Exception("You are not logged in. There's a chance that the account is banned");
      }
      $obj = @json_decode($response, true);
      $status = $obj['status'];
      if ($status != 'ok') {
        throw new Exception("Status isn't okay");
      }

      return $obj;
    }

    private function generate_guid() {
       return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
              mt_rand(0, 65535),
              mt_rand(0, 65535),
              mt_rand(0, 65535),
              mt_rand(16384, 20479),
              mt_rand(32768, 49151),
              mt_rand(0, 65535),
              mt_rand(0, 65535),
              mt_rand(0, 65535));
    }

    private function generate_user_agent() {
       $resolutions = ['720x1280', '320x480', '480x800', '1024x768', '1280x720', '768x1024', '480x320'];
       $versions = ['GT-N7000', 'SM-N9000', 'GT-I9220', 'GT-I9100'];
       $dpis = ['120', '160', '320', '240'];
       $ver = $versions[array_rand($versions)];
       $dpi = $dpis[array_rand($dpis)];
       $res = $resolutions[array_rand($resolutions)];
       return 'Instagram 4.'.mt_rand(1,2).'.'.mt_rand(0,2).' Android ('.mt_rand(10,11).'/'.mt_rand(1,3).'.'.mt_rand(3,5).'.'.mt_rand(0,5).'; '.$dpi.'; '.$res.'; samsung; '.$ver.'; '.$ver.'; smdkc210; en_US)';
    }

    private function generate_signature($data) {
       return hash_hmac('sha256', $data, 'b4a23f5e39b5929e0666ac5de94c89d1618a2916');
    }

    private function get_post_data($filename) {
      if (!$filename) {
          throw new Exception("The image doesn't exist " . $filename);
      }
      $post_data = ['device_timestamp' => time(),
                      'photo' => new CURLFile($filename)
                    ];
      return $post_data;
    }

    private function auth() {
      $this->agent = $this->generate_user_agent();
      $this->guid = $this->generate_guid();
      $this->device_id = "android-" . $this->guid;
      /* LOG IN */
      // Set all of the parameters in the string, and then sign it with their API key using SHA-256
      $data ='{"device_id":"'.$this->device_id.
        '","guid":"'.$this->guid.
        '","username":"'.$this->username.
        '","password":"'.$this->password.
        '","Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"}';
      $sig = $this->generate_signature($data);
      $data = 'signed_body='.$sig.'.'.urlencode($data).'&ig_sig_key_version=4';
      /* Maybe i should do list($http_response, $response) =   */
      $response = $this->send_request('accounts/login/', true, $data, $this->agent, false);
      return true;
    }

    public function post_image($filename, $caption='Do titles matter?') {
      $data = $this->get_post_data($filename);
      $post = $this->send_request('media/upload/', true, $data, $this->agent, true);

      // Now, configure the photo
      $caption = preg_replace("/\r|\n/", "", $caption);
      $media_id = $post['media_id'];
      $data = '{"device_id":"'.$this->device_id.
        '","guid":"'.$this->guid.
        '","media_id":"'.$media_id.
        '","caption":"'.trim($caption).
        '","device_timestamp":"'.time().
        '","source_type":"5","filter_type":"0","extra":"{}","Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"}';
      $sig = $this->generate_signature($data);
      $new_data = 'signed_body='.$sig.'.'.urlencode($data).'&ig_sig_key_version=4';
      $conf = $this->send_request('media/configure/', true, $new_data, $this->agent, true);

    }
}
