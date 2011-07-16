<?php
include 'include/languages.inc';
if(!$conf=apc_fetch('ws2_config')) {
  include '/local/Web/ws2.conf';
  apc_store('ws2_config',$conf);
}
$raw = filter_input(INPUT_GET, 'q', FILTER_UNSAFE_RAW);
$q = urlencode($raw);
$r = isset($_REQUEST['results']) ? (int)$_REQUEST['results'] : 10;
$s = isset($_REQUEST['start']) ? (int)$_REQUEST['start'] : 1;
$l = isset($_REQUEST['lang']) ? htmlspecialchars($_REQUEST['lang'], ENT_QUOTES) : 'en';
$m = isset($_REQUEST['mirror']) ? htmlspecialchars($_REQUEST['mirror'], ENT_QUOTES) : '';
$sites = array( 'all'=>'php.net',
                'local'=>'www.php.net',
                '404'=>'www.php.net',
                'manual'=>"www.php.net/manual/$l",
                'news'=>'news.php.net',
                'bugs'=>'bugs.php.net',
                'pear'=>'pear.php.net',
                'pecl'=>'pecl.php.net',
                'talks'=>'talks.php.net',
              );

$market = 'en-us';
if(!empty($LANGUAGES_MAP[$l])) $market = $LANGUAGES_MAP[$l];

if(isset($sites[$_REQUEST['profile']])) {
    $scope = htmlspecialchars($_REQUEST['profile'], ENT_QUOTES);
    // If they are doing a manual search in a language we don't have a translation for, default to English
    if($scope == 'manual' && empty($ACTIVE_ONLINE_LANGUAGES[$l])) {
        $sites['manual'] = "www.php.net/manual/en";
    }
} else { 
    $scope = 'all';
}

$request =  "{$conf['svc']}?appid={$conf['appid']}&query=$q%20site:{$sites[$scope]}&version=2.2&Sources=Web&web.offset=$s&web.count=$r&market=$market";
$data = @file_get_contents($request);
list($version,$status_code,$msg) = explode(' ',$http_response_header[0], 3);
if($status_code==200) echo ws_bing_massage($data);
else echo serialize($http_response_header[0]);

function ws_bing_massage($data) {
    $results = json_decode($data, true);
    $rsp = $results['SearchResponse']['Web'];
    $set = $rsp['Results'];

    $massaged = array(
        'ResultSet' => array(
            'totalResultsAvailable' => $rsp['Total'],
            'totalResultsReturned' => count($set),
            'firstResultPosition' => $rsp['Offset'],
            'Result' => array(),
        ),
    );

    foreach ($set as $result) {
        $massaged['ResultSet']['Result'][] = array(
            'Title' => $result['Title'],
            'Summary' => $result['Description'],
            'Url' => $result['Url'],
            'ClickUrl' => $result['Url'],
            'MimeType' => NULL, // Not returned by Bing
            'ModificationDate' => strtotime($result['DateTime']),
            'Cache' => $result['CacheUrl']
        );
    }

    return serialize($massaged);
}

$dbh = new PDO('mysql:host=localhost;dbname=ws', $conf['db_user'], $conf['db_pw'], array(PDO::ATTR_PERSISTENT => true,
                                                                                         PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true));
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
try {
  $stmt = $dbh->prepare("INSERT INTO log (query,profile,mirror,lang) VALUES (:query,:profile,:mirror,:lang)");
  $stmt->execute(array(':query'=>$raw,':profile'=>$scope,':mirror'=>$m,':lang'=>$l));
} catch (PDOException $e) {
   
}
?>
