<?php
require __DIR__ . '/vendor/autoload.php';

#insert your Ustream credentials here
define("USER_NAME", "<USER_NAME>");
define("PASSWORD", "<PASSWORD>");
#name of the video you want to upload, it has to be the same folder as the current script
define("FILE_NAME", "<FILE_NAME>");
define("FILE_EXTENSION", "<FILE_EXTENSION>");

echo "Ustream API basic usage:\n";

$client = new \GuzzleHttp\Client();
echo "--------------------------\n";
echo "----- Authentication --------\n";
echo "--------------------------\n";
$auth_token = login($client);

echo "\n\n--------------------------\n";
echo "----- List channels ------\n";
echo "--------------------------\n";
$channel_ids = getChannels($client, $auth_token);

echo "\n\n--------------------------\n";
echo "----- List videos   ------\n";
echo "--------------------------\n";
getVideoes($client, $auth_token, $channel_ids[0]);

echo "\n\n--------------------------\n";
echo "----- Upload a video  ----\n";
echo "--------------------------\n";
uploadVideo($client, $auth_token, $channel_ids[0]);

echo "\n\n--------------------------\n";
echo "----- Download a video ---\n";
echo "--------------------------\n";
downloadVideo($client, $auth_token);

echo "\n\n--------------------------\n";
echo "----- Request a downloadable video ----\n";
echo "--------------------------\n";
requestDownloadVersionOfVideo($client, $auth_token);



function login($client) {
    $res = $client->request('POST', 'https://www.ustream.tv/oauth2/token', [
        'headers' => [
            #insert your client_secret here
            'Authorization' => 'Basic <CLIENT_SECRET>'
        ],
        'form_params' => [
            #insert your client_id here
            'client_id' => '<CLIENT_ID>',
            'username' => USER_NAME,
            'password' => PASSWORD,
            'grant_type' => 'password',
            'device_name' => 'UstreamTesting',
            'scope' => 'offline+broadcaster',
            'token_type' => 'bearer'
            ]
    ]);
    if ($res->getStatusCode() == 200) {
        $response = json_decode($res->getBody());
        $auth_token = $response->access_token;
        printPretty($response);
    }
    return $auth_token;
}

function getChannels($client, $auth_token) {
    $res = $client->request('GET', 'https://api.ustream.tv/users/self/channels.json', [
        'headers' => [
            'Authorization' => 'Bearer ' . $auth_token
        ]
    ]);
    if ($res->getStatusCode() == 200) {
        $response = json_decode($res->getBody());
        $channel_ids = [];
        foreach($response->channels as $key => $value) {
            array_push($channel_ids, $response->channels->{$key}->id);
        }
        printPretty($response);
    }
    return $channel_ids;
}

function getVideoes($client, $auth_token, $channel_id) {
    $res = $client->request('GET', 'https://api.ustream.tv/channels/'.$channel_id.'/videos.json', [
        'headers' => [
            'Authorization' => 'Bearer ' . $auth_token
        ],
        'query' => [
            'filter' => [
                'protect' => 'private'
             ]
        ]
    ]);
    if ($res->getStatusCode() == 200) {
        $response = json_decode($res->getBody());
        printPretty($response);
    }
}

function uploadVideo($client, $auth_token, $channel_id) {
    # 1. call upload video endpoint to get the details and credentials for ftp connection
    $res = $client->request('POST', 'https://api.ustream.tv/channels/'.$channel_id.'/uploads.json', [
        'headers' => [
            'Authorization' => 'Bearer ' . $auth_token
        ],
        'query' => [
            'type' => 'videoupload-ftp'
        ],
 	'form_params' => [
            'title' => FILE_NAME,
	    'description' => FILE_NAME
        ]
    ]);
    if ($res->getStatusCode() == 201) {
        $response = json_decode($res->getBody());
        printPretty($response);
        $video_id = $response->videoId;
        $ftp_server = $response->protocol.'.'.$response->host;
        $ftp_user_name = $response->user;
        $ftp_user_pass = $response->password;
	$path = $response->path;
	$url = $response->url;
    }

    # 2. use ftp to upload the video file to the server
    $file = FILE_NAME.".".FILE_EXTENSION;
    $remote_file = substr($path,1).".".FILE_EXTENSION;

    $conn_id = ftp_connect($ftp_server);
    $login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);
    ftp_pasv($conn_id, true);
    if (ftp_put($conn_id, $remote_file, $file, FTP_BINARY)) {
	echo "Successfully uploaded $file\n";
    } else {
        echo "There was a problem while uploading $file\n";
    }
    ftp_close($conn_id);

    # 3. call file in place endpoint 
    $res = $client->request('PUT', 'https://api.ustream.tv/channels/'.$channel_id.'/uploads/'.$video_id.'.json', [
        'headers' => [
            'Authorization' => 'Bearer ' . $auth_token
        ],
        'form_params' => [
            'status' => 'ready'
        ]
    ]);
    echo "File in place - Response code: " . $res->getStatusCode()."\n";  
    $response = json_decode($res->getBody());
    printPretty($response);
}

function downloadVideo($client, $auth_token) {
    $res = $client->request('GET', 'https://api.ustream.tv/videos/<VIDEO ID>/downloadable/mp4.json', [
        'headers' => [
            'Authorization' => 'Bearer ' . $auth_token
        ]
    ]);
    $response = json_decode($res->getBody());
    printPretty($response);
}

function requestDownloadVersionOfVideo($client, $auth_token) {
    $res = $client->request('POST', 'https://api.ustream.tv/videos/<VIDEO ID>/downloadable/flv.json', [
        'headers' => [
            'Authorization' => 'Bearer ' . $auth_token
        ]
    ]);
    $response = json_decode($res->getBody());
    printPretty($response);
}

function printPretty($response) {
    echo json_encode($response, JSON_PRETTY_PRINT)."\n";
}
